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
 * Class JavascriptHtmlRule.
 */
final class JavascriptHtmlRule implements RuleInterface {
    private const MIN_HTML_LENGTH = 200;

    /**
     * Method name.
     *
     * @return string Return value.
     */
    public function name(): string {
        return 'javascript';
    }

    /**
     * Method validate.
     *
     * @param ValidationContext $context Parameter context.
     * @return array Return value.
     */
    public function validate(ValidationContext $context): array {
        $warnings = [];

        foreach ($this->javascriptFiles($context->pluginroot) as $file) {
            $relative = $context->relative($file);
            $contents = file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            $startpatterns = [
                '/\\binnerHTML\\s*=\\s*([`\'\"])/',
                '/\\.html\\s*\\(\\s*([`\'\"])/',
                '/\\binsertAdjacentHTML\\s*\\(\\s*[^,]+,\\s*([`\'\"])/',
            ];

            foreach ($startpatterns as $pattern) {
                if (!preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                foreach ($matches[1] as $quoteinfo) {
                    $quote = $quoteinfo[0];
                    $quoteoffset = $quoteinfo[1];
                    $endoffset = $this->findClosingQuote($contents, $quoteoffset + 1, $quote);
                    if ($endoffset === null) {
                        continue;
                    }
                    $html = substr($contents, $quoteoffset + 1, $endoffset - $quoteoffset - 1);
                    if (strlen($html) < self::MIN_HTML_LENGTH || !$this->looksLikeHtml($html)) {
                        continue;
                    }
                    $warnings[] = Check::warning(
                        $this->name(),
                        $relative,
                        $this->lineFromOffset($contents, $quoteoffset),
                        'Large inline HTML fragment (' . strlen($html) . " bytes) detected in JavaScript. Consider moving the markup to a Mustache template.",
                    );
                }
            }
        }

        if ($warnings === []) {
            return [new Check(true, $this->name(), '.', 1, '', 'No large inline HTML fragments were detected in JavaScript sources.')];
        }

        return $warnings;
    }

    /** @return string[] */
    private function javascriptFiles(string $root): array {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if (!$item->isFile() || strtolower($item->getExtension()) !== 'js') {
                continue;
            }
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1));
            if (str_starts_with($relative, 'vendor/') || str_starts_with($relative, '.git/') || str_starts_with($relative, 'amd/build/') || str_ends_with($relative, '.min.js')) {
                continue;
            }
            $files[] = $item->getPathname();
        }
        sort($files);
        return $files;
    }

    /**
     * Method findClosingQuote.
     *
     * @param string $contents Parameter contents.
     * @param int $start Parameter start.
     * @param string $quote Parameter quote.
     * @return ?int Return value.
     */
    private function findClosingQuote(string $contents, int $start, string $quote): ?int {
        $length = strlen($contents);
        $escaped = false;
        for ($i = $start; $i < $length; $i++) {
            $char = $contents[$i];
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $escaped = true;
                continue;
            }
            if ($char === $quote) {
                return $i;
            }
        }
        return null;
    }

    /**
     * Method looksLikeHtml.
     *
     * @param string $value Parameter value.
     * @return bool Return value.
     */
    private function looksLikeHtml(string $value): bool {
        return preg_match('/<\/?[a-z][^>]*>/i', $value) === 1;
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
