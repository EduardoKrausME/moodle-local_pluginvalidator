<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\PhpSourceInspector;
use EduardoKraus\MoodleStringValidate\ValidationContext;

/**
 * Class ModSupportsRule.
 */
final class ModSupportsRule implements RuleInterface {
    /**
     * Method name.
     *
     * @return string Return value.
     */
    public function name(): string {
        return 'mod_supports';
    }

    /**
     * Method validate.
     *
     * @param ValidationContext $context Parameter context.
     * @return array Return value.
     */
    public function validate(ValidationContext $context): array {
        if (!str_starts_with($context->component, 'mod_')) {
            return [];
        }

        $file = $context->pluginroot . '/lib.php';
        if (!is_file($file)) {
            return [];
        }

        $pluginname = substr($context->component, 4);
        $function = (new PhpSourceInspector())->functionInfo($file, $pluginname . '_supports');
        if ($function === null) {
            return [];
        }

        $relative = $context->relative($file);
        $checks = [];

        foreach ($this->featureReturns($function['code']) as $return) {
            $feature = $return['feature'];
            $expression = $this->normalise($return['expression']);
            $line = $function['line'] + substr_count(substr($function['code'], 0, $return['offset']), "\n");

            if ($feature === 'FEATURE_MOD_PURPOSE') {
                if ($expression === 'null' || preg_match('/^MOD_PURPOSE_[A-Z0-9_]+$/', $expression) === 1) {
                    $checks[] = $this->ok($relative, $line, $feature, "{$feature} returns a valid MOD_PURPOSE_* value.");
                } else {
                    $checks[] = $this->error($relative, $line, $feature, "{$feature} must return a MOD_PURPOSE_* constant or null; got '{$return['expression']}'.");
                }
                continue;
            }

            if ($feature === 'FEATURE_MOD_ARCHETYPE') {
                if ($expression === 'null' || preg_match('/^MOD_ARCHETYPE_[A-Z0-9_]+$/', $expression) === 1) {
                    $checks[] = $this->ok($relative, $line, $feature, "{$feature} returns a valid MOD_ARCHETYPE_* value.");
                } else {
                    $checks[] = $this->error($relative, $line, $feature, "{$feature} must return a MOD_ARCHETYPE_* constant or null; got '{$return['expression']}'.");
                }
                continue;
            }

            if (!in_array(strtolower($expression), ['true', 'false', 'null'], true)) {
                $checks[] = $this->error($relative, $line, $feature, "{$feature} must return true, false, or null; got '{$return['expression']}'.");
            }
        }

        $default = $this->defaultReturn($function['code']);
        if ($default === null) {
            $checks[] = $this->error($relative, $function['line'], 'default', "{$pluginname}_supports() must provide a fallback return of null.");
        } else {
            $line = $function['line'] + substr_count(substr($function['code'], 0, $default['offset']), "\n");
            if (strtolower($this->normalise($default['expression'])) !== 'null') {
                $checks[] = $this->error($relative, $line, 'default', "The default return of {$pluginname}_supports() must be null; got {$default['expression']}.");
            } else {
                $checks[] = $this->ok($relative, $line, 'default', "The default return of {$pluginname}_supports() is null.");
            }
        }

        return $checks;
    }

    /** @return array<int,array{feature:string,expression:string,offset:int}> */
    private function featureReturns(string $code): array {
        $results = [];

        if (preg_match_all('/\b(FEATURE_[A-Z0-9_]+)\s*=>\s*([^,}\n]+)/', $code, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $index => $featurematch) {
                $results[] = [
                    'feature' => $featurematch[0],
                    'expression' => trim($matches[2][$index][0]),
                    'offset' => $featurematch[1],
                ];
            }
        }

        if (preg_match_all('/\bcase\s+(FEATURE_[A-Z0-9_]+)\s*:\s*(?:(?!\bcase\b|\bdefault\b).)*?\breturn\s+([^;]+);/s', $code, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $index => $featurematch) {
                $results[] = [
                    'feature' => $featurematch[0],
                    'expression' => trim($matches[2][$index][0]),
                    'offset' => $featurematch[1],
                ];
            }
        }

        if (preg_match_all('/\bif\s*\([^)]*\b(FEATURE_[A-Z0-9_]+)\b[^)]*\)\s*\{?\s*return\s+([^;]+);/s', $code, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $index => $featurematch) {
                $results[] = [
                    'feature' => $featurematch[0],
                    'expression' => trim($matches[2][$index][0]),
                    'offset' => $featurematch[1],
                ];
            }
        }

        return $results;
    }

    /** @return array{expression:string,offset:int}|null */
    private function defaultReturn(string $code): ?array {
        if (preg_match('/\bdefault\s*=>\s*([^,}\n]+)/', $code, $match, PREG_OFFSET_CAPTURE) === 1) {
            return ['expression' => trim($match[1][0]), 'offset' => $match[0][1]];
        }
        if (preg_match('/\bdefault\s*:\s*(?:(?!\bcase\b).)*?\breturn\s+([^;]+);/s', $code, $match, PREG_OFFSET_CAPTURE) === 1) {
            return ['expression' => trim($match[1][0]), 'offset' => $match[0][1]];
        }
        if (preg_match_all('/\breturn\s+([^;]+);/', $code, $matches, PREG_OFFSET_CAPTURE)) {
            $last = array_key_last($matches[1]);
            if ($last !== null) {
                return ['expression' => trim($matches[1][$last][0]), 'offset' => $matches[0][$last][1]];
            }
        }
        return null;
    }

    /**
     * Method normalise.
     *
     * @param string $expression Parameter expression.
     * @return string Return value.
     */
    private function normalise(string $expression): string {
        $expression = trim($expression, " \t\n\r;");
        while (strlen($expression) >= 2 && $expression[0] === '(' && $expression[strlen($expression) - 1] === ')') {
            $expression = trim(substr($expression, 1, -1));
        }
        return $expression;
    }

    /**
     * Method ok.
     *
     * @param string $file Parameter file.
     * @param int $line Parameter line.
     * @param string $key Parameter key.
     * @param string $message Parameter message.
     * @return Check Return value.
     */
    private function ok(string $file, int $line, string $key, string $message): Check {
        return new Check(true, $this->name(), $file, max(1, $line), $key, $message);
    }

    /**
     * Method error.
     *
     * @param string $file Parameter file.
     * @param int $line Parameter line.
     * @param string $key Parameter key.
     * @param string $message Parameter message.
     * @return Check Return value.
     */
    private function error(string $file, int $line, string $key, string $message): Check {
        return new Check(false, $this->name(), $file, max(1, $line), $key, $message);
    }
}
