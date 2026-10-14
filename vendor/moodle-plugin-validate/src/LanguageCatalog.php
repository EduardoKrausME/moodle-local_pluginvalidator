<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate;

use RuntimeException;

/**
 * Class LanguageCatalog.
 */
final class LanguageCatalog {
    /** @var array<string, array{empty: bool, line: int}> */
    private array $strings = [];

    /**
     * Method __construct.
     *
     * @param string $file Parameter file.
     */
    public function __construct(private readonly string $file) {
        $this->parse();
    }

    /**
     * Method file.
     *
     * @return string Return value.
     */
    public function file(): string {
        return $this->file;
    }

    /**
     * Method has.
     *
     * @param string $key Parameter key.
     * @return bool Return value.
     */
    public function has(string $key): bool {
        return isset($this->strings[$key]);
    }

    /**
     * Method isEmpty.
     *
     * @param string $key Parameter key.
     * @return bool Return value.
     */
    public function isEmpty(string $key): bool {
        return $this->strings[$key]['empty'] ?? false;
    }

    /**
     * Method line.
     *
     * @param string $key Parameter key.
     * @return int Return value.
     */
    public function line(string $key): int {
        return $this->strings[$key]['line'] ?? 1;
    }

    /**
     * Method parse.
     *
     * @return void Return value.
     */
    private function parse(): void {
        $contents = file_get_contents($this->file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$this->file}");
        }

        $tokens = token_get_all($contents);
        $count = count($tokens);

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
            $valueindex = $equals === null ? null : $this->nextSignificant($tokens, $equals + 1);

            if ($close === null || $tokens[$close] !== ']' || $equals === null || $tokens[$equals] !== '=' || $valueindex === null) {
                continue;
            }

            $key = $this->decodeLiteral($tokens[$keyindex][1]);
            $value = $tokens[$valueindex];
            $empty = false;

            if (is_array($value) && $value[0] === T_CONSTANT_ENCAPSED_STRING) {
                $empty = trim($this->decodeLiteral($value[1])) === '';
            }

            $this->strings[$key] = [
                'empty' => $empty,
                'line' => $tokens[$keyindex][2],
            ];
        }
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
