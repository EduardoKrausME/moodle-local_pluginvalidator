<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate;

use RuntimeException;

/**
 * Class PhpArrayKeyExtractor.
 */
final class PhpArrayKeyExtractor {
    /**
     * Extracts top-level string keys from an array assigned to a PHP variable.
     *
     * @return array<int, array{key: string, line: int}>
     */
    public function extract(string $file, string $variablename): array {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$file}");
        }

        $tokens = token_get_all($contents);
        $count = count($tokens);
        $target = '$' . ltrim($variablename, '$');

        for ($i = 0; $i < $count; $i++) {
            if (!$this->isToken($tokens[$i], T_VARIABLE, $target)) {
                continue;
            }

            $equals = $this->nextSignificant($tokens, $i + 1);
            if ($equals === null || $tokens[$equals] !== '=') {
                continue;
            }

            $start = $this->nextSignificant($tokens, $equals + 1);
            if ($start === null) {
                continue;
            }

            if ($tokens[$start] === '[') {
                return $this->extractFromArray($tokens, $start);
            }

            if ($this->isToken($tokens[$start], T_ARRAY, 'array')) {
                $open = $this->nextSignificant($tokens, $start + 1);
                if ($open !== null && $tokens[$open] === '(') {
                    return $this->extractFromArray($tokens, $open);
                }
            }
        }

        return [];
    }

    /**
     * @param array<int, array|string> $tokens
     * @return array<int, array{key: string, line: int}>
     */
    private function extractFromArray(array $tokens, int $start): array {
        $results = [];
        $stack = [$tokens[$start]];
        $count = count($tokens);

        for ($i = $start + 1; $i < $count && $stack !== []; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                if (in_array($token, ['[', '(', '{'], true)) {
                    $stack[] = $token;
                    continue;
                }

                if (in_array($token, [']', ')', '}'], true)) {
                    array_pop($stack);
                    continue;
                }
            }

            if (count($stack) !== 1 || !is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            $arrow = $this->nextSignificant($tokens, $i + 1);
            if ($arrow === null || !$this->isTokenType($tokens[$arrow], T_DOUBLE_ARROW)) {
                continue;
            }

            $results[] = [
                'key' => $this->decodeLiteral($token[1]),
                'line' => $token[2],
            ];
        }

        return $results;
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
     * Method isTokenType.
     *
     * @param array|string $token Parameter token.
     * @param int $type Parameter type.
     * @return bool Return value.
     */
    private function isTokenType(array|string $token, int $type): bool {
        return is_array($token) && $token[0] === $type;
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
