<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\ValidationContext;

/**
 * Class ModBackupRestoreRule.
 */
final class ModBackupRestoreRule implements RuleInterface {
    /**
     * Method name.
     *
     * @return string Return value.
     */
    public function name(): string {
        return "mod_backup_restore";
    }

    /**
     * Method validate.
     *
     * @param ValidationContext $context Parameter context.
     * @return array Return value.
     */
    public function validate(ValidationContext $context): array {
        if (!str_starts_with($context->component, "mod_")) {
            return [];
        }

        $pluginname = substr($context->component, 4);
        $feature = $this->backupFeatureState($context->pluginroot . "/lib.php", $pluginname);

        $required = [
            "backup/moodle2/backup_{$pluginname}_activity_task.class.php",
            "backup/moodle2/backup_{$pluginname}_stepslib.php",
            "backup/moodle2/restore_{$pluginname}_activity_task.class.php",
            "backup/moodle2/restore_{$pluginname}_stepslib.php",
        ];

        $existing = array_values(array_filter(
            $required,
            static fn(string $relative): bool => is_file($context->pluginroot . "/" . $relative),
        ));

        if ($feature["state"] !== true) {
            if ($existing !== []) {
                $message = $feature["declared"]
                    ? "Backup/restore files exist, but FEATURE_BACKUP_MOODLE2 is not statically verified as true."
                    : "Backup/restore files exist, but {$pluginname}_supports() does not explicitly enable FEATURE_BACKUP_MOODLE2.";

                return [Check::warning(
                    $this->name(),
                    "lib.php",
                    $feature["line"],
                    $message,
                    "supports:FEATURE_BACKUP_MOODLE2",
                )];
            }
            return [];
        }

        $checks = [new Check(
            true,
            $this->name(),
            "lib.php",
            $feature["line"],
            "supports:FEATURE_BACKUP_MOODLE2",
            "FEATURE_BACKUP_MOODLE2 is explicitly enabled; backup and restore implementation will be validated.",
        )];

        foreach ($required as $relative) {
            if (!is_file($context->pluginroot . "/" . $relative)) {
                $checks[] = new Check(
                    false,
                    $this->name(),
                    $relative,
                    1,
                    "file:{$relative}",
                    "FEATURE_BACKUP_MOODLE2 is enabled but required file {$relative} is missing.",
                );
                continue;
            }

            $checks[] = new Check(
                true,
                $this->name(),
                $relative,
                1,
                "file:{$relative}",
                "Required backup/restore file {$relative} exists.",
            );
        }

        $backuptask = "backup/moodle2/backup_{$pluginname}_activity_task.class.php";
        if (is_file($context->pluginroot . "/" . $backuptask)) {
            array_push($checks, ...$this->validateTaskFile(
                $context,
                $backuptask,
                "backup_{$pluginname}_activity_task",
                "backup_activity_task",
            ));
        }

        $restoretask = "backup/moodle2/restore_{$pluginname}_activity_task.class.php";
        if (is_file($context->pluginroot . "/" . $restoretask)) {
            array_push($checks, ...$this->validateTaskFile(
                $context,
                $restoretask,
                "restore_{$pluginname}_activity_task",
                "restore_activity_task",
            ));
        }

        $backupsteps = "backup/moodle2/backup_{$pluginname}_stepslib.php";
        if (is_file($context->pluginroot . "/" . $backupsteps)) {
            array_push($checks, ...$this->validateStepsFile(
                $context,
                $backupsteps,
                "backup_activity_structure_step",
            ));
        }

        $restoresteps = "backup/moodle2/restore_{$pluginname}_stepslib.php";
        if (is_file($context->pluginroot . "/" . $restoresteps)) {
            array_push($checks, ...$this->validateStepsFile(
                $context,
                $restoresteps,
                "restore_activity_structure_step",
            ));
        }

        return $checks;
    }

    /** @return array{state:?bool,line:int,declared:bool} */
    private function backupFeatureState(string $libfile, string $pluginname): array {
        if (!is_file($libfile)) {
            return ["state" => null, "line" => 1, "declared" => false];
        }

        $source = file_get_contents($libfile);
        if ($source === false) {
            return ["state" => null, "line" => 1, "declared" => false];
        }

        $supports = $this->extractFunction($source, $pluginname . "_supports");
        if ($supports === null) {
            return ["state" => null, "line" => 1, "declared" => false];
        }

        foreach ($this->extractSupportsFeatureReturns($supports["code"]) as $return) {
            if ($return["feature"] !== "FEATURE_BACKUP_MOODLE2") {
                continue;
            }

            $expression = strtolower($this->stripOuterParentheses(trim($return["expression"])));
            $line = $supports["line"] + substr_count(substr($supports["code"], 0, $return["offset"]), "\n");

            if ($expression === "true") {
                return ["state" => true, "line" => $line, "declared" => true];
            }
            if ($expression === "false" || $expression === "null") {
                return ["state" => false, "line" => $line, "declared" => true];
            }
            return ["state" => null, "line" => $line, "declared" => true];
        }

        return ["state" => null, "line" => $supports["line"], "declared" => false];
    }

    /** @return Check[] */
    private function validateTaskFile(
        ValidationContext $context,
        string $relative,
        string $classname,
        string $parentclass,
    ): array {
        $source = file_get_contents($context->pluginroot . "/" . $relative);
        if ($source === false) {
            return [];
        }

        $class = $this->extractClass($source, $classname);
        if ($class === null) {
            return [new Check(
                false,
                $this->name(),
                $relative,
                1,
                "class:{$classname}",
                "Expected class {$classname} was not found.",
            )];
        }

        $checks = [];
        if ($this->classExtends($class["code"], $parentclass)) {
            $checks[] = new Check(
                true,
                $this->name(),
                $relative,
                $class["line"],
                "class:{$classname}",
                "Class {$classname} extends {$parentclass}.",
            );
        } else {
            $checks[] = new Check(
                false,
                $this->name(),
                $relative,
                $class["line"],
                "class:{$classname}",
                "Class {$classname} must extend {$parentclass}.",
            );
        }

        $method = $this->extractFunction($class["code"], "define_my_steps");
        if ($method === null) {
            $checks[] = new Check(
                false,
                $this->name(),
                $relative,
                $class["line"],
                "method:{$classname}::define_my_steps",
                "Class {$classname} must implement define_my_steps().",
            );
            return $checks;
        }

        $methodline = $class["line"] + $method["line"] - 1;
        $checks[] = new Check(
            true,
            $this->name(),
            $relative,
            $methodline,
            "method:{$classname}::define_my_steps",
            "Class {$classname} implements define_my_steps().",
        );

        if (preg_match('/->add_step\s*\(/i', $method["code"]) === 1) {
            $checks[] = new Check(
                true,
                $this->name(),
                $relative,
                $methodline,
                "steps:{$classname}",
                "define_my_steps() in {$classname} adds at least one step.",
            );
        } else {
            $checks[] = new Check(
                false,
                $this->name(),
                $relative,
                $methodline,
                "steps:{$classname}",
                "define_my_steps() in {$classname} does not add any backup/restore step with add_step().",
            );
        }

        return $checks;
    }

    /** @return Check[] */
    private function validateStepsFile(
        ValidationContext $context,
        string $relative,
        string $parentclass,
    ): array {
        $source = file_get_contents($context->pluginroot . "/" . $relative);
        if ($source === false) {
            return [];
        }

        $classnames = $this->findClassesExtending($source, $parentclass);
        if ($classnames === []) {
            return [new Check(
                false,
                $this->name(),
                $relative,
                1,
                "structure:{$parentclass}",
                "No class extending {$parentclass} was found in {$relative}.",
            )];
        }

        $checks = [];
        foreach ($classnames as $classname) {
            $class = $this->extractClass($source, $classname);
            if ($class === null) {
                continue;
            }

            $checks[] = new Check(
                true,
                $this->name(),
                $relative,
                $class["line"],
                "class:{$classname}",
                "Class {$classname} extends {$parentclass}.",
            );

            $method = $this->extractFunction($class["code"], "define_structure");
            if ($method === null) {
                $checks[] = new Check(
                    false,
                    $this->name(),
                    $relative,
                    $class["line"],
                    "method:{$classname}::define_structure",
                    "Class {$classname} must implement define_structure().",
                );
                continue;
            }

            $methodline = $class["line"] + $method["line"] - 1;
            $checks[] = new Check(
                true,
                $this->name(),
                $relative,
                $methodline,
                "method:{$classname}::define_structure",
                "Class {$classname} implements define_structure().",
            );

            if (preg_match('/\bprepare_activity_structure\s*\(/i', $method["code"]) === 1) {
                $checks[] = new Check(
                    true,
                    $this->name(),
                    $relative,
                    $methodline,
                    "prepare:{$classname}",
                    "define_structure() in {$classname} calls prepare_activity_structure().",
                );
            } else {
                $checks[] = new Check(
                    false,
                    $this->name(),
                    $relative,
                    $methodline,
                    "prepare:{$classname}",
                    "define_structure() in {$classname} must return the structure through prepare_activity_structure().",
                );
            }
        }

        return $checks;
    }

    /** @return string[] */
    private function findClassesExtending(string $source, string $parentclass): array {
        if (!preg_match_all(
            '/\bclass\s+([A-Za-z_][A-Za-z0-9_]*)\s+extends\s+([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)/i',
            $source,
            $matches,
            PREG_SET_ORDER,
        )) {
            return [];
        }

        $classes = [];
        foreach ($matches as $match) {
            if ($this->classBasename($match[2]) === $parentclass) {
                $classes[] = $match[1];
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * Method classExtends.
     *
     * @param string $classcode Parameter classcode.
     * @param string $parentclass Parameter parentclass.
     * @return bool Return value.
     */
    private function classExtends(string $classcode, string $parentclass): bool {
        if (preg_match('/\bextends\s+([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)/i', $classcode, $match) !== 1) {
            return false;
        }
        return $this->classBasename($match[1]) === $parentclass;
    }

    /**
     * Method classBasename.
     *
     * @param string $classname Parameter classname.
     * @return string Return value.
     */
    private function classBasename(string $classname): string {
        $classname = ltrim($classname, "\\");
        $parts = explode("\\", $classname);
        return (string)end($parts);
    }

    /** @return array<int,array{feature:string,expression:string,offset:int}> */
    private function extractSupportsFeatureReturns(string $code): array {
        $results = [];

        if (preg_match_all('/\b(FEATURE_[A-Z0-9_]+)\s*=>\s*([^,}\n]+)/', $code, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $index => $featurematch) {
                $results[] = [
                    "feature" => $featurematch[0],
                    "expression" => trim($matches[2][$index][0]),
                    "offset" => $featurematch[1],
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
                    "feature" => $featurematch[0],
                    "expression" => trim($matches[2][$index][0]),
                    "offset" => $featurematch[1],
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
                    "feature" => $featurematch[0],
                    "expression" => trim($matches[2][$index][0]),
                    "offset" => $featurematch[1],
                ];
            }
        }

        return $results;
    }

    /**
     * Method stripOuterParentheses.
     *
     * @param string $expression Parameter expression.
     * @return string Return value.
     */
    private function stripOuterParentheses(string $expression): string {
        while (strlen($expression) >= 2 && $expression[0] === "(" && $expression[strlen($expression) - 1] === ")") {
            $expression = trim(substr($expression, 1, -1));
        }
        return $expression;
    }

    /** @return array{code:string,line:int}|null */
    private function extractClass(string $source, string $classname): ?array {
        $tokens = token_get_all($source);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_CLASS) {
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
                if ($token === "{") {
                    break;
                }
            }

            if ($name !== $classname) {
                continue;
            }

            $code = "";
            $depth = 0;
            $started = false;
            for ($j = $i; $j < $count; $j++) {
                $token = $tokens[$j];
                $text = is_array($token) ? $token[1] : $token;
                $code .= $text;

                if ($token === "{") {
                    $depth++;
                    $started = true;
                } elseif ($token === "}" && $started) {
                    $depth--;
                    if ($depth === 0) {
                        return ["code" => $code, "line" => $line];
                    }
                }
            }
        }

        return null;
    }

    /** @return array{code:string,line:int}|null */
    private function extractFunction(string $source, string $functionname): ?array {
        $lineoffset = 0;
        if (!str_contains(substr($source, 0, 64), "<?php")) {
            $source = "<?php\n" . $source;
            $lineoffset = -1;
        }

        $tokens = token_get_all($source);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
                continue;
            }

            $line = $tokens[$i][2] + $lineoffset;
            $name = null;
            for ($j = $i + 1; $j < $count; $j++) {
                $token = $tokens[$j];
                if (is_array($token) && $token[0] === T_STRING) {
                    $name = $token[1];
                    break;
                }
                if ($token === "{") {
                    break;
                }
            }

            if ($name !== $functionname) {
                continue;
            }

            $code = "";
            $depth = 0;
            $started = false;
            for ($j = $i; $j < $count; $j++) {
                $token = $tokens[$j];
                $text = is_array($token) ? $token[1] : $token;
                $code .= $text;

                if ($token === "{") {
                    $depth++;
                    $started = true;
                } elseif ($token === "}" && $started) {
                    $depth--;
                    if ($depth === 0) {
                        return ["code" => $code, "line" => $line];
                    }
                }
            }
        }

        return null;
    }
}
