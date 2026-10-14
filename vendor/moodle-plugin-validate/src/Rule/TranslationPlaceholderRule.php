<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\ValidationContext;
use RuntimeException;

/**
 * Class TranslationPlaceholderRule.
 */
final class TranslationPlaceholderRule implements RuleInterface {
    /**
     * Method name.
     *
     * @return string Return value.
     */
    public function name(): string {
        return 'translationplaceholder';
    }

    /**
     * Method validate.
     *
     * @param ValidationContext $context Parameter context.
     * @return array Return value.
     */
    public function validate(ValidationContext $context): array {
        $langroot = $context->pluginroot . '/lang';
        if (!is_dir($langroot)) {
            return [];
        }

        $filename = basename($context->catalog->file());
        $englishfile = $langroot . '/en/' . $filename;
        if (!is_file($englishfile)) {
            return [new Check(
                false,
                $this->name(),
                $context->relative($englishfile),
                1,
                '',
                "Cannot compare translation placeholders because English language file lang/en/{$filename} is missing.",
            )];
        }

        $english = $this->extractStrings($englishfile);
        $directories = glob($langroot . '/*', GLOB_ONLYDIR) ?: [];
        sort($directories, SORT_STRING);

        $checks = [];
        foreach ($directories as $directory) {
            $language = basename($directory);
            if ($language === 'en') {
                continue;
            }

            $translationfile = $directory . '/' . $filename;
            if (!is_file($translationfile)) {
                continue;
            }

            $translation = $this->extractStrings($translationfile);
            $compared = 0;
            $errors = 0;

            foreach ($translation as $key => $translated) {
                if (!isset($english[$key])) {
                    continue;
                }
                $compared++;

                $expected = $this->placeholderCounts($english[$key]['placeholders']);
                $actual = $this->placeholderCounts($translated['placeholders']);
                if ($expected === $actual) {
                    continue;
                }

                $errors++;
                $missing = $this->subtractPlaceholders($expected, $actual);
                $unexpected = $this->subtractPlaceholders($actual, $expected);
                $details = [];
                if ($missing !== []) {
                    $details[] = 'missing ' . $this->formatPlaceholders($missing);
                }
                if ($unexpected !== []) {
                    $details[] = 'unexpected ' . $this->formatPlaceholders($unexpected);
                }

                $checks[] = new Check(
                    false,
                    $this->name(),
                    $context->relative($translationfile),
                    $translated['line'],
                    $key,
                    "Translation '{$language}' for \$string['{$key}'] does not preserve the English placeholders: "
                        . implode('; ', $details) . '.',
                );
            }

            if ($compared > 0 && $errors === 0) {
                $checks[] = new Check(
                    true,
                    $this->name(),
                    $context->relative($translationfile),
                    1,
                    '',
                    "Translation placeholders in lang/{$language}/{$filename} match English for {$compared} translated string"
                        . ($compared === 1 ? '.' : 's.'),
                );
            }
        }

        return $checks;
    }

    /**
     * @return array<string, array{line: int, placeholders: string[]}>
     */
    private function extractStrings(string $file): array {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$file}");
        }

        $tokens = token_get_all($contents);
        $count = count($tokens);
        $strings = [];

        for ($i = 0; $i < $count; $i++) {
            if (!$this->isToken($tokens[$i], T_VARIABLE, '$string')) {
                continue;
            }

            $open = $this->nextSignificant($tokens, $i + 1);
            if ($open === null || $tokens[$open] !== '[') {
                continue;
            }
            $keyindex = $this->nextSignificant($tokens, $open + 1);
            if ($keyindex === null || !is_array($tokens[$keyindex]) || $tokens[$keyindex][0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            $close = $this->nextSignificant($tokens, $keyindex + 1);
            $equals = $close === null ? null : $this->nextSignificant($tokens, $close + 1);
            if ($close === null || $tokens[$close] !== ']' || $equals === null || $tokens[$equals] !== '=') {
                continue;
            }

            $expression = '';
            $depth = 0;
            for ($j = $equals + 1; $j < $count; $j++) {
                $token = $tokens[$j];
                if (!is_array($token)) {
                    if ($token === ';' && $depth === 0) {
                        break;
                    }
                    if (in_array($token, ['(', '[', '{'], true)) {
                        $depth++;
                    } elseif (in_array($token, [')', ']', '}'], true) && $depth > 0) {
                        $depth--;
                    }
                    $expression .= $token;
                    continue;
                }

                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $expression .= $token[1];
            }

            $key = $this->decodeLiteral($tokens[$keyindex][1]);
            $strings[$key] = [
                'line' => $tokens[$keyindex][2],
                'placeholders' => $this->extractPlaceholders($expression),
            ];
        }

        return $strings;
    }

    /** @return string[] */
    private function extractPlaceholders(string $expression): array {
        preg_match_all('/\{\$a(?:->[^}]*)?\}/', $expression, $matches);
        return $matches[0] ?? [];
    }

    /**
     * @param string[] $placeholders
     * @return array<string, int>
     */
    private function placeholderCounts(array $placeholders): array {
        $counts = array_count_values($placeholders);
        ksort($counts, SORT_STRING);
        return $counts;
    }

    /**
     * @param array<string, int> $left
     * @param array<string, int> $right
     * @return array<string, int>
     */
    private function subtractPlaceholders(array $left, array $right): array {
        $result = [];
        foreach ($left as $placeholder => $count) {
            $difference = $count - ($right[$placeholder] ?? 0);
            if ($difference > 0) {
                $result[$placeholder] = $difference;
            }
        }
        return $result;
    }

    /** @param array<string, int> $placeholders */
    private function formatPlaceholders(array $placeholders): string {
        $formatted = [];
        foreach ($placeholders as $placeholder => $count) {
            $formatted[] = $count === 1 ? $placeholder : "{$placeholder} x{$count}";
        }
        return implode(', ', $formatted);
    }

    /** @param array<int, array|string> $tokens */
    private function nextSignificant(array $tokens, int $start): ?int {
        $count = count($tokens);
        for ($i = $start; $i < $count; $i++) {
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
     * Method isToken.
     *
     * @param array|string $token Parameter token.
     * @param int $type Parameter type.
     * @param string $value Parameter value.
     * @return bool Return value.
     */
    private function isToken(array|string $token, int $type, string $value): bool {
        return is_array($token) && $token[0] === $type && $token[1] === $value;
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
