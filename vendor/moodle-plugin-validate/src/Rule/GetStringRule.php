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
 * Class GetStringRule.
 */
final class GetStringRule implements RuleInterface {
    /**
     * Method name.
     *
     * @return string Return value.
     */
    public function name(): string {
        return 'get_string';
    }

    /**
     * Method validate.
     *
     * @param ValidationContext $context Parameter context.
     * @return array Return value.
     */
    public function validate(ValidationContext $context): array {
        $checks = [];
        $found = 0;

        foreach ($this->phpFiles($context->pluginroot) as $file) {
            $relative = $context->relative($file);
            if ($this->shouldSkip($relative)) {
                continue;
            }

            $contents = file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            foreach ($this->extractCalls($contents) as $call) {
                if ($call['component'] !== $context->component) {
                    continue;
                }

                $found++;
                $checks[] = $context->checkRequiredString(
                    $this->name(),
                    $call['key'],
                    $file,
                    $call['line'],
                    "used by get_string('{$call['key']}', '{$call['component']}') in {$relative}.",
                );
            }
        }

        if ($found === 0) {
            $checks[] = new Check(
                true,
                $this->name(),
                '.',
                1,
                '',
                "No statically detectable get_string() calls for component '{$context->component}' were found.",
            );
        }

        return $checks;
    }

    /** @return string[] */
    private function phpFiles(string $root): array {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isFile() && strtolower($item->getExtension()) === 'php') {
                $files[] = $item->getPathname();
            }
        }
        sort($files);
        return $files;
    }

    /**
     * Method shouldSkip.
     *
     * @param string $relative Parameter relative.
     * @return bool Return value.
     */
    private function shouldSkip(string $relative): bool {
        $relative = str_replace('\\', '/', $relative);
        return str_starts_with($relative, 'vendor/')
            || str_starts_with($relative, '.git/')
            || str_starts_with($relative, 'lang/')
            || str_starts_with($relative, 'tests/fixtures/');
    }

    /**
     * @return array<int, array{key: string, component: string, line: int}>
     */
    private function extractCalls(string $contents): array {
        $tokens = token_get_all($contents);
        $count = count($tokens);
        $calls = [];

        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING || strtolower($tokens[$i][1]) !== 'get_string') {
                continue;
            }

            $open = $this->nextSignificant($tokens, $i + 1);
            if ($open === null || $tokens[$open] !== '(') {
                continue;
            }

            $keyindex = $this->nextSignificant($tokens, $open + 1);
            if ($keyindex === null || !$this->isLiteral($tokens[$keyindex])) {
                continue;
            }

            $comma = $this->nextSignificant($tokens, $keyindex + 1);
            if ($comma === null || $tokens[$comma] !== ',') {
                // One-argument get_string() resolves against core, not the current plugin.
                continue;
            }

            $componentindex = $this->nextSignificant($tokens, $comma + 1);
            if ($componentindex === null || !$this->isLiteral($tokens[$componentindex])) {
                continue;
            }

            $calls[] = [
                'key' => $this->decodeLiteral($tokens[$keyindex][1]),
                'component' => $this->decodeLiteral($tokens[$componentindex][1]),
                'line' => $tokens[$keyindex][2],
            ];
        }

        return $calls;
    }

    /** @param array<int, array|string> $tokens */
    private function nextSignificant(array $tokens, int $start): ?int {
        for ($i = $start, $count = count($tokens); $i < $count; $i++) {
            if (!is_array($tokens[$i])) {
                return $i;
            }
            if (!in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $i;
            }
        }
        return null;
    }

    /**
     * Method isLiteral.
     *
     * @param array|string $token Parameter token.
     * @return bool Return value.
     */
    private function isLiteral(array|string $token): bool {
        return is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING;
    }

    /**
     * Method decodeLiteral.
     *
     * @param string $literal Parameter literal.
     * @return string Return value.
     */
    private function decodeLiteral(string $literal): string {
        $quote = $literal[0] ?? '';
        $value = substr($literal, 1, -1);
        if ($quote === "'") {
            return str_replace(["\\\\", "\\'"], ["\\", "'"], $value);
        }
        return stripcslashes($value);
    }
}
