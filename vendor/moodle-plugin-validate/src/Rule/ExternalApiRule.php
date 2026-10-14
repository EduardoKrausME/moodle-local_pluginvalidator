<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\PhpSourceInspector;
use EduardoKraus\MoodleStringValidate\ValidationContext;

/**
 * Class ExternalApiRule.
 */
final class ExternalApiRule implements RuleInterface {
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
        return 'external_api';
    }

    /**
     * Method validate.
     *
     * @param ValidationContext $context Parameter context.
     * @return array Return value.
     */
    public function validate(ValidationContext $context): array {
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
            if ($classname === null || $methodname === null) {
                continue;
            }

            $classfile = $this->inspector->resolveClassFile($context, $classname, $classpath);
            if ($classfile === null || !is_file($classfile)) {
                continue;
            }
            $classinfo = $this->inspector->classInfo($classfile, $classname);
            if ($classinfo === null) {
                continue;
            }

            $parent = strtolower(ltrim($classinfo['extends'], '\\'));
            if (!in_array($parent, ['external_api', 'core_external\\external_api'], true)) {
                $checks[] = $this->error($relative, $entry['line'], $classname, "Web service class {$classname} must extend external_api.");
            }

            $method = $classinfo['methods'][$methodname] ?? null;
            $parametersname = $methodname . '_parameters';
            $returnsname = $methodname . '_returns';
            $parameters = $classinfo['methods'][$parametersname] ?? null;
            $returns = $classinfo['methods'][$returnsname] ?? null;
            $key = $classname . '::' . $methodname;

            if ($parameters === null) {
                $checks[] = $this->error($relative, $entry['line'], $key . '_parameters', "External method {$key}() requires {$parametersname}().");
            } elseif (preg_match('/\bexternal_function_parameters\b/', $parameters['code']) !== 1) {
                $checks[] = $this->error($context->relative($classfile), $parameters['line'], $key . '_parameters', "{$classname}::{$parametersname}() must define parameters with external_function_parameters.");
            }

            if ($returns === null) {
                $checks[] = $this->error($relative, $entry['line'], $key . '_returns', "External method {$key}() requires {$returnsname}().");
            }

            if ($method !== null) {
                if (preg_match('/\bvalidate_parameters\s*\(/', $method['code']) !== 1) {
                    $checks[] = $this->error($context->relative($classfile), $method['line'], $key, "{$key}() must call validate_parameters().");
                } elseif (preg_match('/\b' . preg_quote($parametersname, '/') . '\s*\(/', $method['code']) !== 1) {
                    $checks[] = $this->error($context->relative($classfile), $method['line'], $key, "{$key}() must validate against {$parametersname}().");
                }

                if (preg_match('/\bvalidate_context\s*\(/', $method['code']) !== 1) {
                    $checks[] = $this->error($context->relative($classfile), $method['line'], $key . ':context', "{$key}() must call validate_context().");
                }
            }
        }

        return $checks;
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
