<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\ValidationContext;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Class MustacheUrlRule.
 */
final class MustacheUrlRule implements RuleInterface {
    /**
     * Method name.
     *
     * @return string Return value.
     */
    public function name(): string {
        return 'mustacheurl';
    }

    /** @return Check[] */
    public function validate(ValidationContext $context): array {
        $checks = [];

        foreach ($this->mustacheFiles($context->pluginroot) as $file) {
            $contents = file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            $pattern = '/\b(href|src)\s*=\s*(["\'])\s*(\{\{(?!\{)[^{}\r\n]*\}\})\s*\2/i';
            if (!preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[3] as $index => $match) {
                $attribute = strtolower($matches[1][$index][0]);
                $expression = trim(substr($match[0], 2, -2));
                $checks[] = new Check(
                    false,
                    $this->name(),
                    $context->relative($file),
                    $this->lineFromOffset($contents, $match[1]),
                    $expression,
                    "Mustache {$attribute} values must use triple braces ({{{" . $expression . "}}}) so '&' is not escaped as '&amp;'.",
                );
            }
        }

        if ($checks === []) {
            return [new Check(
                true,
                $this->name(),
                '.',
                1,
                '',
                'Mustache href/src values use triple braces.',
            )];
        }

        return $checks;
    }

    /** @return string[] */
    private function mustacheFiles(string $root): array {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if (!$item->isFile() || strtolower($item->getExtension()) !== 'mustache') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1));
            if (str_starts_with($relative, 'vendor/') || str_starts_with($relative, '.git/')) {
                continue;
            }

            $files[] = $item->getPathname();
        }

        sort($files);
        return $files;
    }

    /**
     * Method lineFromOffset.
     *
     * @param string $contents Parameter contents.
     * @param int $offset Parameter offset.
     * @return int Return value.
     */
    private function lineFromOffset(string $contents, int $offset): int {
        return substr_count(substr($contents, 0, $offset), "\n") + 1;
    }
}
