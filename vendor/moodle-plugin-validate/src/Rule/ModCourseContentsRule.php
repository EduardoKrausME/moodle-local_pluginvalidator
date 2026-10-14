<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\ValidationContext;

/**
 * Class ModCourseContentsRule.
 */
final class ModCourseContentsRule implements RuleInterface {
    private const CACHED_CM_INFO_FIELDS = [
        'name' => 'string',
        'icon' => 'string',
        'iconcomponent' => 'string',
        'content' => 'string',
        'customdata' => 'mixed',
        'extraclasses' => 'string',
        'iconurl' => 'moodle_url',
        'onclick' => 'string',
    ];

    private const BOOLEAN_SUPPORT_FEATURES = [
        'FEATURE_GRADE_HAS_GRADE',
        'FEATURE_GRADE_HAS_PENALTY',
        'FEATURE_GRADE_OUTCOMES',
        'FEATURE_ADVANCED_GRADING',
        'FEATURE_CONTROLS_GRADE_VISIBILITY',
        'FEATURE_PLAGIARISM',
        'FEATURE_COMPLETION',
        'FEATURE_COMPLETION_TRACKS_VIEWS',
        'FEATURE_COMPLETION_HAS_RULES',
        'FEATURE_NO_VIEW_LINK',
        'FEATURE_IDNUMBER',
        'FEATURE_GROUPS',
        'FEATURE_GROUPINGS',
        'FEATURE_MOD_INTRO',
        'FEATURE_MODEDIT_DEFAULT_COMPLETION',
        'FEATURE_COMMENT',
        'FEATURE_RATE',
        'FEATURE_BACKUP_MOODLE2',
        'FEATURE_PUBLISHES_QUESTIONS',
        'FEATURE_CAN_DISPLAY',
        'FEATURE_CAN_UNINSTALL',
        'FEATURE_SHOW_DESCRIPTION',
        'FEATURE_USES_QUESTIONS',
        'FEATURE_QUICKCREATE',
    ];

    /**
     * Method name.
     *
     * @return string Return value.
     */
    public function name(): string {
        return 'mod_course_contents';
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

        $source = file_get_contents($file);
        if ($source === false) {
            return [];
        }

        $pluginname = substr($context->component, 4);
        $checks = [];

        $cminfofunction = $this->extractFunction($source, $pluginname . '_get_coursemodule_info');
        if ($cminfofunction !== null) {
            array_push($checks, ...$this->validateCourseModuleInfo($cminfofunction['code'], $cminfofunction['line']));
        }

        $supportsfunction = $this->extractFunction($source, $pluginname . '_supports');
        if ($supportsfunction !== null) {
            array_push($checks, ...$this->validateSupports($supportsfunction['code'], $supportsfunction['line']));
        }

        return $checks;
    }

    /** @return Check[] */
    private function validateCourseModuleInfo(string $code, int $startline): array {
        $returnedvariables = [];
        if (preg_match_all('/\breturn\s+(\$[A-Za-z_][A-Za-z0-9_]*)\s*;/i', $code, $matches)) {
            $returnedvariables = array_values(array_unique($matches[1]));
        }

        if ($returnedvariables === []) {
            return [];
        }

        $checks = [];
        foreach ($returnedvariables as $variable) {
            $quotedvariable = preg_quote($variable, '/');
            $type = null;
            if (preg_match('/' . $quotedvariable . '\s*=\s*new\s+(?:\\\\?core_course\\\\)?cached_cm_info\s*(?:\(|;)/i', $code)) {
                $type = 'cached_cm_info';
            } elseif (preg_match('/' . $quotedvariable . '\s*=\s*new\s+\\\\?stdClass\s*(?:\(|;)/i', $code)) {
                $type = 'stdClass';
            }

            if ($type === null) {
                continue;
            }

            $allowed = self::CACHED_CM_INFO_FIELDS;
            if ($type === 'stdClass') {
                $allowed = [
                    'name' => 'string',
                    'icon' => 'string',
                    'iconcomponent' => 'string',
                    'iconurl' => 'moodle_url',
                    'onclick' => 'string',
                    'extra' => 'string',
                ];
            }

            $pattern = '/' . $quotedvariable . '->([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*?);/s';
            if (!preg_match_all($pattern, $code, $assignments, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($assignments[1] as $index => $propertymatch) {
                $property = $propertymatch[0];
                $expression = trim($assignments[2][$index][0]);
                $offset = $propertymatch[1];
                $line = $startline + substr_count(substr($code, 0, $offset), "\n");

                if (!array_key_exists($property, $allowed)) {
                    $hint = $property === 'purpose'
                        ? ' Module purpose must be declared by the plugin supports callback with FEATURE_MOD_PURPOSE.'
                        : '';
                    $checks[] = new Check(
                        false,
                        $this->name(),
                        'lib.php',
                        $line,
                        $property,
                        "Property '{$property}' is not a valid {$type} return field for get_coursemodule_info().{$hint}",
                    );
                    continue;
                }

                $expected = $allowed[$property];
                $actual = $this->literalType($expression);
                if ($actual !== null && !$this->isCompatibleType($expected, $actual)) {
                    $checks[] = new Check(
                        false,
                        $this->name(),
                        'lib.php',
                        $line,
                        $property,
                        "Invalid literal type for get_coursemodule_info() field '{$property}': expected {$expected}, got {$actual}.",
                    );
                    continue;
                }

                $checks[] = new Check(
                    true,
                    $this->name(),
                    'lib.php',
                    $line,
                    $property,
                    "get_coursemodule_info() field '{$property}' is compatible with {$type}.",
                );
            }
        }

        return $checks;
    }

    /** @return Check[] */
    private function validateSupports(string $code, int $startline): array {
        $checks = [];
        $handled = [];

        $featurereturns = $this->extractSupportsFeatureReturns($code);
        foreach ($featurereturns as $return) {
            $feature = $return['feature'];
            $expression = trim($return['expression']);
            $line = $startline + substr_count(substr($code, 0, $return['offset']), "\n");
            $signature = $feature . ':' . $line;
            if (isset($handled[$signature])) {
                continue;
            }
            $handled[$signature] = true;

            $check = $this->validateSupportsFeatureReturn($feature, $expression, $line);
            if ($check !== null) {
                $checks[] = $check;
            }
        }

        $default = $this->extractSupportsDefaultReturn($code);
        if ($default !== null) {
            $expression = trim($default['expression']);
            $line = $startline + substr_count(substr($code, 0, $default['offset']), "\n");
            $normalized = strtolower($this->stripOuterParentheses($expression));
            if ($normalized === 'null') {
                $checks[] = new Check(
                    true,
                    $this->name(),
                    'lib.php',
                    $line,
                    'supports:default',
                    '[PLUGINNAME]_supports() returns null for unknown features.',
                );
            } elseif ($this->literalType($expression) !== null || $this->isKnownSupportConstant($expression)) {
                $checks[] = new Check(
                    false,
                    $this->name(),
                    'lib.php',
                    $line,
                    'supports:default',
                    "The default return of [PLUGINNAME]_supports() must be null; got {$expression}. " .
                        'A non-null default can return an invalid type for FEATURE_MOD_ARCHETYPE, FEATURE_MOD_PURPOSE or another feature.',
                );
            } else {
                $checks[] = Check::warning(
                    $this->name(),
                    'lib.php',
                    $line,
                    "Could not statically verify the default return '{$expression}' of [PLUGINNAME]_supports(); unknown features should return null.",
                    'supports:default',
                );
            }
        } else {
            $fallback = $this->extractSupportsFallbackReturn($code, $featurereturns);
            if ($fallback !== null) {
                $expression = trim($fallback['expression']);
                $line = $startline + substr_count(substr($code, 0, $fallback['offset']), "\n");
                $normalized = strtolower($this->stripOuterParentheses($expression));
                if ($normalized === 'null') {
                    $checks[] = new Check(
                        true,
                        $this->name(),
                        'lib.php',
                        $line,
                        'supports:fallback',
                        '[PLUGINNAME]_supports() returns null when no FEATURE_* branch matches.',
                    );
                } elseif ($this->literalType($expression) !== null || $this->isKnownSupportConstant($expression)) {
                    $checks[] = new Check(
                        false,
                        $this->name(),
                        'lib.php',
                        $line,
                        'supports:fallback',
                        "The fallback return of [PLUGINNAME]_supports() must be null; got {$expression}. " .
                            'A non-null fallback may return the wrong type for an unhandled FEATURE_* input.',
                    );
                } else {
                    $checks[] = Check::warning(
                        $this->name(),
                        'lib.php',
                        $line,
                        "Could not statically verify fallback return '{$expression}' of [PLUGINNAME]_supports(); unmatched features should return null.",
                        'supports:fallback',
                    );
                }
            }
        }

        return $checks;
    }

    /**
     * Method validateSupportsFeatureReturn.
     *
     * @param string $feature Parameter feature.
     * @param string $expression Parameter expression.
     * @param int $line Parameter line.
     * @return ?Check Return value.
     */
    private function validateSupportsFeatureReturn(string $feature, string $expression, int $line): ?Check {
        $key = 'supports:' . $feature;

        if ($feature === 'FEATURE_GROUPMEMBERSONLY') {
            return new Check(
                false,
                $this->name(),
                'lib.php',
                $line,
                $key,
                'FEATURE_GROUPMEMBERSONLY is deprecated and plugin_supports() rejects this feature.',
            );
        }

        if (in_array($feature, self::BOOLEAN_SUPPORT_FEATURES, true)) {
            $status = $this->validateBooleanSupportExpression($expression);
            if ($status === true) {
                return new Check(
                    true,
                    $this->name(),
                    'lib.php',
                    $line,
                    $key,
                    "{$feature} returns a boolean-compatible value.",
                );
            }
            if ($status === false) {
                $actual = $this->literalType($expression) ?? $expression;
                return new Check(
                    false,
                    $this->name(),
                    'lib.php',
                    $line,
                    $key,
                    "{$feature} must return bool or null; got {$actual}.",
                );
            }
            return Check::warning(
                $this->name(),
                'lib.php',
                $line,
                "Could not statically verify that {$feature} returns bool or null: {$expression}.",
                $key,
            );
        }

        if ($feature === 'FEATURE_MOD_ARCHETYPE') {
            $status = $this->validateArchetypeExpression($expression);
            if ($status === true) {
                return new Check(
                    true,
                    $this->name(),
                    'lib.php',
                    $line,
                    $key,
                    'FEATURE_MOD_ARCHETYPE returns a MOD_ARCHETYPE_* compatible value.',
                );
            }
            if ($status === false) {
                return new Check(
                    false,
                    $this->name(),
                    'lib.php',
                    $line,
                    $key,
                    "FEATURE_MOD_ARCHETYPE must return MOD_ARCHETYPE_*, an archetype integer 0..3, or null; got {$expression}.",
                );
            }
            return Check::warning(
                $this->name(),
                'lib.php',
                $line,
                "Could not statically verify FEATURE_MOD_ARCHETYPE return: {$expression}.",
                $key,
            );
        }

        if ($feature === 'FEATURE_MOD_PURPOSE') {
            $status = $this->validatePurposeExpression($expression);
            if ($status === true) {
                return new Check(
                    true,
                    $this->name(),
                    'lib.php',
                    $line,
                    $key,
                    'FEATURE_MOD_PURPOSE returns a MOD_PURPOSE_* value or alphabetic purpose string compatible with PARAM_ALPHA.',
                );
            }
            if ($status === false) {
                return new Check(
                    false,
                    $this->name(),
                    'lib.php',
                    $line,
                    $key,
                    "FEATURE_MOD_PURPOSE must return MOD_PURPOSE_*, an alphabetic purpose string, or null; got {$expression}.",
                );
            }
            return Check::warning(
                $this->name(),
                'lib.php',
                $line,
                "Could not statically verify FEATURE_MOD_PURPOSE return: {$expression}.",
                $key,
            );
        }

        if ($feature === 'FEATURE_MOD_OTHERPURPOSE') {
            $status = $this->validateOtherPurposeExpression($expression);
            if ($status === true) {
                return new Check(
                    true,
                    $this->name(),
                    'lib.php',
                    $line,
                    $key,
                    'FEATURE_MOD_OTHERPURPOSE returns a string-compatible value.',
                );
            }
            if ($status === false) {
                return new Check(
                    false,
                    $this->name(),
                    'lib.php',
                    $line,
                    $key,
                    "FEATURE_MOD_OTHERPURPOSE must return MOD_PURPOSE_*, a string, or null; got {$expression}.",
                );
            }
            return Check::warning(
                $this->name(),
                'lib.php',
                $line,
                "Could not statically verify FEATURE_MOD_OTHERPURPOSE return: {$expression}.",
                $key,
            );
        }

        if (str_starts_with($feature, 'FEATURE_')) {
            return Check::warning(
                $this->name(),
                'lib.php',
                $line,
                "Unknown Moodle feature {$feature}; its return contract is not defined by this validator version.",
                $key,
            );
        }

        return null;
    }

    /**
     * @return array<int,array{feature:string,expression:string,offset:int,expressionoffset:int}>
     */
    private function extractSupportsFeatureReturns(string $code): array {
        $results = [];

        if (preg_match_all('/\b(FEATURE_[A-Z0-9_]+)\s*=>\s*([^,}\n]+)/', $code, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $index => $featurematch) {
                $results[] = [
                    'feature' => $featurematch[0],
                    'expression' => trim($matches[2][$index][0]),
                    'offset' => $featurematch[1],
                    'expressionoffset' => $matches[2][$index][1],
                ];
            }
        }

        if (preg_match_all(
            '/\bcase\s+(FEATURE_[A-Z0-9_]+)\s*:\s*(?:(?!\bcase\b|\bdefault\b).)*?\breturn\s+([^;]+);/s',
            $code,
            $matches,
            PREG_OFFSET_CAPTURE,
        )) {
            foreach ($matches[1] as $index => $featurematch) {
                $results[] = [
                    'feature' => $featurematch[0],
                    'expression' => trim($matches[2][$index][0]),
                    'offset' => $featurematch[1],
                    'expressionoffset' => $matches[2][$index][1],
                ];
            }
        }

        if (preg_match_all(
            '/\bif\s*\([^)]*\b(FEATURE_[A-Z0-9_]+)\b[^)]*\)\s*\{?\s*return\s+([^;]+);/s',
            $code,
            $matches,
            PREG_OFFSET_CAPTURE,
        )) {
            foreach ($matches[1] as $index => $featurematch) {
                $results[] = [
                    'feature' => $featurematch[0],
                    'expression' => trim($matches[2][$index][0]),
                    'offset' => $featurematch[1],
                    'expressionoffset' => $matches[2][$index][1],
                ];
            }
        }

        return $results;
    }

    /** @return array{expression:string,offset:int}|null */
    private function extractSupportsDefaultReturn(string $code): ?array {
        $patterns = [
            '/\bdefault\s*=>\s*([^,}\n]+)/',
            '/\bdefault\s*:\s*(?:(?!\bcase\b).)*?\breturn\s+([^;]+);/s',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $code, $match, PREG_OFFSET_CAPTURE)) {
                return [
                    'expression' => trim($match[1][0]),
                    'offset' => $match[1][1],
                ];
            }
        }
        return null;
    }

    /**
     * @param array<int,array{feature:string,expression:string,offset:int,expressionoffset:int}> $featurereturns
     * @return array{expression:string,offset:int}|null
     */
    private function extractSupportsFallbackReturn(string $code, array $featurereturns): ?array {
        if (!preg_match_all('/\breturn\s+([^;]+);/', $code, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $explicitoffsets = [];
        foreach ($featurereturns as $return) {
            $explicitoffsets[$return['expressionoffset']] = true;
        }

        $last = $matches[1][count($matches[1]) - 1];
        if (isset($explicitoffsets[$last[1]])) {
            return null;
        }

        // A match expression is handled through its default arm rather than as a fallback return.
        $expression = trim($last[0]);
        if (preg_match('/^match\s*\(/', $expression)) {
            return null;
        }

        return [
            'expression' => $expression,
            'offset' => $last[1],
        ];
    }

    /** true=valid, false=invalid, null=unknown statically */
    private function validateBooleanSupportExpression(string $expression): ?bool {
        $expression = $this->stripOuterParentheses(trim($expression));
        if (preg_match('/^(true|false|null)$/i', $expression)) {
            return true;
        }

        $type = $this->literalType($expression);
        if ($type !== null) {
            return false;
        }

        if (preg_match('/^\(bool\)\s*/i', $expression) || str_starts_with($expression, '!')) {
            return true;
        }
        if (preg_match('/^(?:isset|empty|is_[A-Za-z0-9_]+)\s*\(/', $expression)) {
            return true;
        }
        if (preg_match('/(?:===|!==|==|!=|<=|>=|<|>|\binstanceof\b)/', $expression)) {
            return true;
        }

        return null;
    }

    /** true=valid, false=invalid, null=unknown statically */
    private function validateArchetypeExpression(string $expression): ?bool {
        $expression = $this->stripOuterParentheses(trim($expression));
        if (strcasecmp($expression, 'null') === 0) {
            return true;
        }
        if (preg_match('/^\\\\?(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*MOD_ARCHETYPE_[A-Z0-9_]+$/', $expression)) {
            return true;
        }
        if (preg_match('/^[+-]?\d+$/', $expression)) {
            $value = (int)$expression;
            return $value >= 0 && $value <= 3;
        }
        if ($this->literalType($expression) !== null) {
            return false;
        }
        return null;
    }

    /** true=valid, false=invalid, null=unknown statically */
    private function validatePurposeExpression(string $expression): ?bool {
        $expression = $this->stripOuterParentheses(trim($expression));
        if (strcasecmp($expression, 'null') === 0) {
            return true;
        }
        if (preg_match('/^\\\\?(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*MOD_PURPOSE_[A-Z0-9_]+$/', $expression)) {
            return true;
        }

        $string = $this->literalString($expression);
        if ($string !== null) {
            return $string !== '' && ctype_alpha($string);
        }
        if ($this->literalType($expression) !== null) {
            return false;
        }
        return null;
    }

    /** true=valid, false=invalid, null=unknown statically */
    private function validateOtherPurposeExpression(string $expression): ?bool {
        $expression = $this->stripOuterParentheses(trim($expression));
        if (strcasecmp($expression, 'null') === 0) {
            return true;
        }
        if (preg_match('/^\\\\?(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*MOD_PURPOSE_[A-Z0-9_]+$/', $expression)) {
            return true;
        }
        if ($this->literalString($expression) !== null) {
            return true;
        }
        if ($this->literalType($expression) !== null) {
            return false;
        }
        return null;
    }

    /**
     * Method isKnownSupportConstant.
     *
     * @param string $expression Parameter expression.
     * @return bool Return value.
     */
    private function isKnownSupportConstant(string $expression): bool {
        $expression = $this->stripOuterParentheses(trim($expression));
        return preg_match('/^(?:MOD_ARCHETYPE|MOD_PURPOSE)_[A-Z0-9_]+$/', $expression) === 1;
    }

    /**
     * Method literalType.
     *
     * @param string $expression Parameter expression.
     * @return ?string Return value.
     */
    private function literalType(string $expression): ?string {
        $expression = $this->stripOuterParentheses(trim($expression));
        if ($this->literalString($expression) !== null) {
            return 'string';
        }
        if (preg_match('/^(true|false)$/i', $expression)) {
            return 'bool';
        }
        if (preg_match('/^[+-]?\d+$/', $expression)) {
            return 'int';
        }
        if (preg_match('/^[+-]?(?:\d+\.\d*|\d*\.\d+)$/', $expression)) {
            return 'float';
        }
        if (preg_match('/^(null)$/i', $expression)) {
            return 'null';
        }
        if (str_starts_with($expression, '[') || preg_match('/^array\s*\(/i', $expression)) {
            return 'array';
        }
        if (preg_match('/^new\s+\\?moodle_url\b/i', $expression)) {
            return 'moodle_url';
        }
        return null;
    }

    /**
     * Method literalString.
     *
     * @param string $expression Parameter expression.
     * @return ?string Return value.
     */
    private function literalString(string $expression): ?string {
        if (strlen($expression) < 2) {
            return null;
        }
        $quote = $expression[0];
        if (($quote !== "'" && $quote !== '"') || $expression[strlen($expression) - 1] !== $quote) {
            return null;
        }

        $value = substr($expression, 1, -1);
        if ($quote === "'") {
            return str_replace(["\\\\", "\\'"], ["\\", "'"], $value);
        }
        return stripcslashes($value);
    }

    /**
     * Method isCompatibleType.
     *
     * @param string $expected Parameter expected.
     * @param string $actual Parameter actual.
     * @return bool Return value.
     */
    private function isCompatibleType(string $expected, string $actual): bool {
        if ($expected === 'mixed') {
            return true;
        }
        return $expected === $actual;
    }

    /**
     * Method stripOuterParentheses.
     *
     * @param string $expression Parameter expression.
     * @return string Return value.
     */
    private function stripOuterParentheses(string $expression): string {
        while (strlen($expression) >= 2 && $expression[0] === '(' && $expression[strlen($expression) - 1] === ')') {
            $expression = trim(substr($expression, 1, -1));
        }
        return $expression;
    }

    /** @return array{code:string,line:int}|null */
    private function extractFunction(string $source, string $functionname): ?array {
        $tokens = token_get_all($source);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
                continue;
            }

            $line = $tokens[$i][2];
            $name = null;
            for ($j = $i + 1; $j < $count; $j++) {
                $token = $tokens[$j];
                if (is_array($token) && $token[0] === T_STRING) {
                    $name = $token[1];
                    break;
                }
                if ($token === '{') {
                    break;
                }
            }

            if ($name !== $functionname) {
                continue;
            }

            $code = '';
            $depth = 0;
            $started = false;
            for ($j = $i; $j < $count; $j++) {
                $token = $tokens[$j];
                $text = is_array($token) ? $token[1] : $token;
                $code .= $text;

                if ($token === '{') {
                    $depth++;
                    $started = true;
                } elseif ($token === '}' && $started) {
                    $depth--;
                    if ($depth === 0) {
                        return ['code' => $code, 'line' => $line];
                    }
                }
            }
        }

        return null;
    }
}
