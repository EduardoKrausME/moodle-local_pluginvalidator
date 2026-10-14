<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\PhpSourceInspector;
use EduardoKraus\MoodleStringValidate\ValidationContext;

/**
 * Class DbReferencesRule.
 */
final class DbReferencesRule implements RuleInterface {
    /**
     * Property inspector.
     *
     * @var PhpSourceInspector
     */
    private PhpSourceInspector $inspector;

    /**
     * Method __construct.
     *
     * @param ?PhpSourceInspector $inspector Parameter inspector.
     */
    public function __construct(?PhpSourceInspector $inspector = null) {
        $this->inspector = $inspector ?? new PhpSourceInspector();
    }

    /**
     * Method name.
     *
     * @return string Return value.
     */
    public function name(): string {
        return 'db_references';
    }

    /**
     * Method validate.
     *
     * @param ValidationContext $context Parameter context.
     * @return array Return value.
     */
    public function validate(ValidationContext $context): array {
        $checks = [];
        array_push($checks, ...$this->validateTasks($context));
        array_push($checks, ...$this->validateCallbacks($context, 'db/observers.php', 'observers'));
        array_push($checks, ...$this->validateCallbacks($context, 'db/hooks.php', 'callbacks'));
        array_push($checks, ...$this->validateServices($context));
        array_push($checks, ...$this->validateRenamedClasses($context));
        return $checks;
    }

    /** @return Check[] */
    private function validateTasks(ValidationContext $context): array {
        $file = $context->pluginroot . '/db/tasks.php';
        if (!is_file($file)) {
            return [];
        }

        $checks = [];
        $relative = $context->relative($file);
        foreach ($this->inspector->arrayEntries($file, 'tasks') as $entry) {
            $classname = $this->inspector->literalValue($entry['code'], 'classname');
            if ($classname === null) {
                $checks[] = $this->error($relative, $entry['line'], 'classname', "Scheduled task entry is missing a literal 'classname'.");
                continue;
            }

            $classinfo = $this->classInfo($context, $relative, $entry['line'], $classname, null, $checks);
            if ($classinfo === null) {
                continue;
            }

            $parent = strtolower(ltrim($classinfo['extends'], '\\'));
            if (!in_array($parent, ['scheduled_task', 'core\\task\\scheduled_task'], true)) {
                $checks[] = $this->error($relative, $entry['line'], $classname, "Scheduled task class {$classname} must extend core\\task\\scheduled_task.");
            }
        }
        return $checks;
    }

    /** @return Check[] */
    private function validateCallbacks(ValidationContext $context, string $path, string $variable): array {
        $file = $context->pluginroot . '/' . $path;
        if (!is_file($file)) {
            return [];
        }

        $checks = [];
        $relative = $context->relative($file);
        foreach ($this->inspector->arrayEntries($file, $variable) as $entry) {
            $callback = $this->inspector->callbackValue($entry['code']);
            if ($callback === null) {
                $checks[] = $this->error($relative, $entry['line'], 'callback', "Metadata entry in {$path} is missing a statically resolvable callback.");
                continue;
            }
            $this->classInfo($context, $relative, $entry['line'], $callback['class'], $callback['method'], $checks);
        }
        return $checks;
    }

    /** @return Check[] */
    private function validateServices(ValidationContext $context): array {
        $file = $context->pluginroot . '/db/services.php';
        if (!is_file($file)) {
            return [];
        }

        $checks = [];
        $relative = $context->relative($file);
        foreach ($this->inspector->arrayEntries($file, 'functions') as $entry) {
            $classname = $this->inspector->literalValue($entry['code'], 'classname');
            $methodname = $this->inspector->literalValue($entry['code'], 'methodname');
            $classpath = $this->inspector->literalValue($entry['code'], 'classpath');

            if ($classname === null) {
                $checks[] = $this->error($relative, $entry['line'], 'classname', "Web service function entry is missing a literal 'classname'.");
                continue;
            }
            if ($methodname === null) {
                $checks[] = $this->error($relative, $entry['line'], $classname, "Web service class {$classname} is missing a literal 'methodname'.");
                continue;
            }
            $this->classInfo($context, $relative, $entry['line'], $classname, $methodname, $checks, $classpath);
        }
        return $checks;
    }

    /** @return Check[] */
    private function validateRenamedClasses(ValidationContext $context): array {
        $file = $context->pluginroot . '/db/renamedclasses.php';
        if (!is_file($file)) {
            return [];
        }

        $source = $this->inspector->arraySource($file, 'renamedclasses');
        if ($source === null) {
            return [$this->error($context->relative($file), 1, 'renamedclasses', 'Unable to statically parse $renamedclasses.')];
        }

        $checks = [];
        foreach ($this->inspector->literalStringMap($source) as $oldclass => $newclass) {
            $this->classInfo($context, $context->relative($file), 1, $newclass, null, $checks, null, $oldclass);
        }
        return $checks;
    }

    /**
     * @param Check[] $checks
     * @return array{name:string,extends:string,line:int,methods:array<string,array{line:int,code:string}>}|null
     */
    private function classInfo(
        ValidationContext $context,
        string $metadatafile,
        int $line,
        string $classname,
        ?string $methodname,
        array &$checks,
        ?string $classpath = null,
        ?string $displaykey = null,
    ): ?array {
        $metadataabsolute = $context->pluginroot . '/' . ltrim($metadatafile, '/');
        $resolvedclassname = $this->inspector->resolveImportedClass($metadataabsolute, $classname);
        $classfile = $this->inspector->resolveClassFile($context, $resolvedclassname, $classpath);
        $key = $displaykey ?? $classname;
        if ($classfile === null || !is_file($classfile)) {
            $checks[] = $this->error($metadatafile, $line, $key, "Referenced class {$classname} cannot be resolved to an existing plugin PHP file.");
            return null;
        }

        $classinfo = $this->inspector->classInfo($classfile, $resolvedclassname);
        if ($classinfo === null) {
            $checks[] = $this->error($metadatafile, $line, $key, "Referenced class {$classname} was not found in " . $context->relative($classfile) . '.');
            return null;
        }

        $checks[] = $this->ok($metadatafile, $line, $key, "Referenced class {$classname} exists in " . $context->relative($classfile) . '.');

        if ($methodname !== null) {
            $methodkey = $classname . '::' . $methodname;
            if (!isset($classinfo['methods'][$methodname])) {
                $checks[] = $this->error($metadatafile, $line, $methodkey, "Referenced method {$methodkey}() does not exist.");
            } else {
                $checks[] = $this->ok($metadatafile, $line, $methodkey, "Referenced method {$methodkey}() exists.");
            }
        }

        return $classinfo;
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
