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
 * Class LegacyAjaxRule.
 */
final class LegacyAjaxRule implements RuleInterface {
    /**
     * Method name.
     *
     * @return string Return value.
     */
    public function name(): string {
        return 'ajax';
    }

    /**
     * Method validate.
     *
     * @param ValidationContext $context Parameter context.
     * @return array Return value.
     */
    public function validate(ValidationContext $context): array {
        $warnings = [];

        foreach ($this->files($context->pluginroot, 'php') as $file) {
            $relative = $context->relative($file);
            if ($this->skip($relative)) {
                continue;
            }
            $contents = file_get_contents($file);
            if ($contents === false || str_contains($contents, '$_FILES')) {
                continue;
            }
            if (preg_match("/define\s*\(\s*['\"]AJAX_SCRIPT['\"]\s*,\s*true\s*\)/i", $contents, $match, PREG_OFFSET_CAPTURE)) {
                $warnings[] = Check::warning(
                    $this->name(),
                    $relative,
                    $this->lineFromOffset($contents, $match[0][1]),
                    "Legacy AJAX_SCRIPT endpoint detected in {$relative}. Prefer External Services + core/ajax when this is not a multipart upload endpoint.",
                );
            }
        }

        foreach ($this->files($context->pluginroot, 'js') as $file) {
            $relative = $context->relative($file);
            if ($this->skipJs($relative)) {
                continue;
            }
            $contents = file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            if (preg_match_all('/\$\.ajax\s*\(\s*\{.*?url\s*:\s*([\'\"`])[^\n]*?\.php(?:\?[^\n]*?)?\1.*?\}\s*\)/si', $contents, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $warnings[] = Check::warning(
                        $this->name(),
                        $relative,
                        $this->lineFromOffset($contents, $match[1]),
                        "Direct jQuery $.ajax() call to a PHP endpoint detected in {$relative}. Prefer core/ajax with an External Service when possible.",
                    );
                }
            }
        }

        if ($warnings === []) {
            return [new Check(true, $this->name(), '.', 1, '', 'No legacy direct AJAX endpoints were detected.')];
        }

        return $warnings;
    }

    /** @return string[] */
    private function files(string $root, string $extension): array {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isFile() && strtolower($item->getExtension()) === $extension) {
                $files[] = $item->getPathname();
            }
        }
        sort($files);
        return $files;
    }

    /**
     * Method skip.
     *
     * @param string $relative Parameter relative.
     * @return bool Return value.
     */
    private function skip(string $relative): bool {
        return str_starts_with($relative, 'vendor/') || str_starts_with($relative, '.git/');
    }

    /**
     * Method skipJs.
     *
     * @param string $relative Parameter relative.
     * @return bool Return value.
     */
    private function skipJs(string $relative): bool {
        return $this->skip($relative)
            || str_contains($relative, '/amd/build/')
            || str_starts_with($relative, 'amd/build/')
            || str_ends_with($relative, '.min.js');
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
