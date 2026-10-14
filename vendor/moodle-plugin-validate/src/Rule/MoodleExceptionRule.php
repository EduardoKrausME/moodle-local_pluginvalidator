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
 * Class MoodleExceptionRule.
 */
final class MoodleExceptionRule implements RuleInterface {
    /**
     * Method name.
     *
     * @return string Return value.
     */
    public function name(): string {
        return 'moodle_exception';
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
                    "used by moodle_exception('{$call['key']}', '{$call['component']}') in {$relative}.",
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
                "No statically detectable moodle_exception calls for component '{$context->component}' were found.",
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
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_NEW) {
                continue;
            }

            $classindex = $this->nextSignificant($tokens, $i + 1);
            if ($classindex === null) {
                continue;
            }

            $classname = $this->readClassName($tokens, $classindex);
            if ($classname === null || !$this->isMoodleExceptionClass($classname['name'])) {
                continue;
            }

            $open = $this->nextSignificant($tokens, $classname['end'] + 1);
            if ($open === null || $tokens[$open] !== '(') {
                continue;
            }

            $arguments = $this->extractArguments($tokens, $open);
            if ($arguments === null) {
                continue;
            }

            $errorcode = $this->literalArgument($arguments, 0, 'errorcode');
            $component = $this->literalArgument($arguments, 1, 'module');
            if ($errorcode === null || $component === null) {
                continue;
            }

            $calls[] = [
                'key' => $errorcode['value'],
                'component' => $component['value'],
                'line' => $errorcode['line'],
            ];
        }

        return $calls;
    }

    /**
     * @param array<int, array|string> $tokens
     * @return array{name: string, end: int}|null
     */
    private function readClassName(array $tokens, int $start): ?array {
        $token = $tokens[$start] ?? null;
        if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            return ['name' => $token[1], 'end' => $start];
        }

        // Fallback for token streams that expose namespace separators separately.
        $name = '';
        $end = $start - 1;
        for ($i = $start, $count = count($tokens); $i < $count; $i++) {
            $current = $tokens[$i];
            if (is_array($current) && $current[0] === T_STRING) {
                $name .= $current[1];
                $end = $i;
                continue;
            }
            if ((is_array($current) && defined('T_NS_SEPARATOR') && $current[0] === T_NS_SEPARATOR) || $current === '\\') {
                $name .= '\\';
                $end = $i;
                continue;
            }
            break;
        }

        return $name === '' ? null : ['name' => $name, 'end' => $end];
    }

    /**
     * Method isMoodleExceptionClass.
     *
     * @param string $classname Parameter classname.
     * @return bool Return value.
     */
    private function isMoodleExceptionClass(string $classname): bool {
        $classname = strtolower(ltrim($classname, '\\'));
        return $classname === 'moodle_exception'
            || $classname === 'core\\exception\\moodle_exception';
    }

    /**
     * @param array<int, array|string> $tokens
     * @return array<int, array<int, array|string>>|null
     */
    private function extractArguments(array $tokens, int $open): ?array {
        $arguments = [];
        $current = [];
        $depth = 1;

        for ($i = $open + 1, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token === '(' || $token === '[' || $token === '{') {
                $depth++;
                $current[] = $token;
                continue;
            }

            if ($token === ')' || $token === ']' || $token === '}') {
                $depth--;
                if ($depth === 0) {
                    if ($current !== [] || $arguments !== []) {
                        $arguments[] = $current;
                    }
                    return $arguments;
                }
                $current[] = $token;
                continue;
            }

            if ($token === ',' && $depth === 1) {
                $arguments[] = $current;
                $current = [];
                continue;
            }

            $current[] = $token;
        }

        return null;
    }

    /**
     * @param array<int, array<int, array|string>> $arguments
     * @return array{value: string, line: int}|null
     */
    private function literalArgument(array $arguments, int $position, string $name): ?array {
        foreach ($arguments as $index => $argument) {
            $significant = array_values(array_filter(
                $argument,
                static fn(array|string $token): bool => !is_array($token)
                    || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ));

            if ($significant === []) {
                continue;
            }

            $valueindex = 0;
            $named = false;
            if (
                count($significant) >= 3
                && is_array($significant[0])
                && $significant[0][0] === T_STRING
                && $significant[1] === ':'
            ) {
                $named = true;
                if (strtolower($significant[0][1]) !== $name) {
                    continue;
                }
                $valueindex = 2;
            } elseif ($index !== $position) {
                continue;
            }

            $value = $significant[$valueindex] ?? null;
            if (!$this->isLiteral($value)) {
                return null;
            }

            // A positional argument containing an expression is not statically safe.
            // Named arguments must also contain only the literal value after the name and colon.
            $expectedcount = $named ? 3 : 1;
            if (count($significant) !== $expectedcount) {
                return null;
            }

            return [
                'value' => $this->decodeLiteral($value[1]),
                'line' => $value[2],
            ];
        }

        return null;
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
     * @param array|string|null $token Parameter token.
     * @return bool Return value.
     */
    private function isLiteral(array|string|null $token): bool {
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
