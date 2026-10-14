<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\ValidationContext;
use stdClass;

/**
 * Class InstallXmlRule.
 */
final class InstallXmlRule implements RuleInterface {
    private const TABLE_NAME_MAX_LENGTH = 53;
    private const FIELD_NAME_MAX_LENGTH = 63;

    /**
     * Legacy table names kept for backwards compatibility.
     *
     * @var array<string, string[]>
     */
    private const LEGACY_TABLE_PREFIX_EXCEPTIONS = [
        'proctoringpolicy_password' => [
            'local_kppassword_req',
            'local_kppassword_attempt',
        ],
    ];

    /**
     * Method name.
     *
     * @return string Return value.
     */
    public function name(): string {
        return 'installxml';
    }

    /**
     * Method validate.
     *
     * @param ValidationContext $context Parameter context.
     * @return array Return value.
     */
    public function validate(ValidationContext $context): array {
        $file = $context->pluginroot . '/db/install.xml';
        if (!is_file($file)) {
            return [];
        }

        $relative = $context->relative($file);
        $xml = file_get_contents($file);
        if ($xml === false) {
            return [$this->error($relative, 1, 'Unable to read db/install.xml.')];
        }

        [$root, $parseerror] = $this->parseXml($xml);
        if ($root === null) {
            return [$this->error(
                $relative,
                $parseerror['line'] ?? 1,
                'Invalid XML in db/install.xml: ' . ($parseerror['message'] ?? 'unknown XML parsing error'),
            )];
        }

        $checks = [new Check(
            true,
            $this->name(),
            $relative,
            1,
            '',
            'db/install.xml is well-formed XML.',
        )];

        if ($root->name !== 'XMLDB') {
            $checks[] = $this->error($relative, $root->line, "Root element must be <XMLDB>, found <{$root->name}>.");
            return $checks;
        }

        $tables = $this->tableMap($checks, $relative, $root);
        $expectedprefix = str_starts_with($context->component, 'mod_')
            ? substr($context->component, 4)
            : $context->component;

        foreach ($tables as $tablename => $table) {
            $this->validateTableName(
                $checks,
                $relative,
                $table,
                $tablename,
                $expectedprefix,
                $context->component,
                $context->ignores('table-prefix'),
            );
            $this->validateTable($checks, $relative, $table, $tablename);
        }

        if (str_starts_with($context->component, 'mod_')) {
            $this->validateActivityModule($checks, $context, $relative, $expectedprefix, $tables);
        }

        if (!$this->hasErrors($checks)) {
            $checks[] = new Check(
                true,
                $this->name(),
                $relative,
                1,
                '',
                'db/install.xml passed XMLDB structural validation.',
            );
        }

        return $checks;
    }

    /**
     * @param Check[] $checks
     * @return array<string, stdClass>
     */
    private function tableMap(array &$checks, string $relative, stdClass $root): array {
        $tables = [];
        foreach ($this->childrenNamed($root, 'TABLES') as $container) {
            foreach ($this->childrenNamed($container, 'TABLE') as $table) {
                $name = $this->attribute($table, 'NAME');
                if ($name === null || $name === '') {
                    $checks[] = $this->error($relative, $table->line, '<TABLE> is missing required NAME attribute.');
                    continue;
                }
                if (isset($tables[$name])) {
                    $checks[] = $this->error($relative, $table->line, "Duplicate table '{$name}' in db/install.xml.");
                    continue;
                }
                $tables[$name] = $table;
            }
        }
        return $tables;
    }

    /** @param Check[] $checks */
    private function validateTableName(
        array &$checks,
        string $relative,
        stdClass $table,
        string $tablename,
        string $expectedprefix,
        string $component,
        bool $ignoretableprefix,
    ): void {
        if (strlen($tablename) > self::TABLE_NAME_MAX_LENGTH) {
            $checks[] = $this->error(
                $relative,
                $table->line,
                "Table name '{$tablename}' is too long; Moodle XMLDB allows at most " . self::TABLE_NAME_MAX_LENGTH . ' characters.',
            );
        }
        if (preg_match('/^[a-z0-9_]+$/', $tablename) !== 1) {
            $checks[] = $this->error(
                $relative,
                $table->line,
                "Invalid table name '{$tablename}'; use lowercase a-z, 0-9 and underscore only.",
            );
        }
        $hasexpectedprefix = $tablename === $expectedprefix
            || str_starts_with($tablename, $expectedprefix . '_');

        if (
            !$ignoretableprefix
            && !$hasexpectedprefix
            && !$this->isLegacyTablePrefixException($component, $tablename)
        ) {
            $checks[] = $this->error(
                $relative,
                $table->line,
                "Table '{$tablename}' must use plugin table prefix '{$expectedprefix}'.",
            );
        }
    }

    /**
     * Method isLegacyTablePrefixException.
     *
     * @param string $component Parameter component.
     * @param string $tablename Parameter tablename.
     * @return bool Return value.
     */
    private function isLegacyTablePrefixException(string $component, string $tablename): bool {
        return in_array(
            $tablename,
            self::LEGACY_TABLE_PREFIX_EXCEPTIONS[$component] ?? [],
            true,
        );
    }

    /** @param Check[] $checks */
    private function validateTable(
        array &$checks,
        string $relative,
        stdClass $table,
        string $tablename,
    ): void {
        $fields = $this->fieldMap($checks, $relative, $table, $tablename);
        $keys = $this->keyDefinitions($checks, $relative, $table, $tablename, $fields);
        $this->validateIndexes($checks, $relative, $table, $tablename, $fields, $keys);
        $this->validateId($checks, $relative, $table, $tablename, $fields, $keys);
    }

    /**
     * @param Check[] $checks
     * @return array<string, stdClass>
     */
    private function fieldMap(array &$checks, string $relative, stdClass $table, string $tablename): array {
        $fields = [];
        foreach ($this->childrenNamed($table, 'FIELDS') as $container) {
            foreach ($this->childrenNamed($container, 'FIELD') as $field) {
                $name = $this->attribute($field, 'NAME');
                if ($name === null || $name === '') {
                    $checks[] = $this->error(
                        $relative,
                        $field->line,
                        "A <FIELD> in table '{$tablename}' is missing required NAME attribute.",
                    );
                    continue;
                }
                if (isset($fields[$name])) {
                    $checks[] = $this->error($relative, $field->line, "Duplicate field '{$name}' in table '{$tablename}'.");
                    continue;
                }
                $fields[$name] = $field;

                if (strlen($name) > self::FIELD_NAME_MAX_LENGTH) {
                    $checks[] = $this->error(
                        $relative,
                        $field->line,
                        "Field name '{$tablename}.{$name}' is too long; Moodle XMLDB allows at most "
                            . self::FIELD_NAME_MAX_LENGTH . ' characters.',
                    );
                }
                if (preg_match('/^[a-z0-9_]+$/', $name) !== 1) {
                    $checks[] = $this->error(
                        $relative,
                        $field->line,
                        "Invalid field name '{$tablename}.{$name}'; use lowercase a-z, 0-9 and underscore only.",
                    );
                }
                if ($this->attribute($field, 'NOTNULL') === 'true'
                    && array_key_exists('DEFAULT', $field->attributes)
                    && $this->attribute($field, 'DEFAULT') === '') {
                    $checks[] = $this->error(
                        $relative,
                        $field->line,
                        "Field '{$tablename}.{$name}' is NOTNULL but declares DEFAULT=\"\"; an empty default is not allowed.",
                    );
                }
            }
        }
        return $fields;
    }

    /**
     * @param Check[] $checks
     * @param array<string, stdClass> $fields
     * @return array<int, array{name: string, type: string, fields: string[]}>
     */
    private function keyDefinitions(
        array &$checks,
        string $relative,
        stdClass $table,
        string $tablename,
        array $fields,
    ): array {
        $keys = [];
        $names = [];
        foreach ($this->childrenNamed($table, 'KEYS') as $container) {
            foreach ($this->childrenNamed($container, 'KEY') as $key) {
                $name = $this->attribute($key, 'NAME') ?? '';
                $type = $this->attribute($key, 'TYPE') ?? '';
                $keyfields = $this->parseFieldList($this->attribute($key, 'FIELDS') ?? '');

                if ($name !== '' && isset($names[$name])) {
                    $checks[] = $this->error($relative, $key->line, "Duplicate key '{$name}' in table '{$tablename}'.");
                }
                if ($name !== '') {
                    $names[$name] = true;
                }
                foreach ($keyfields as $fieldname) {
                    if (!isset($fields[$fieldname])) {
                        $checks[] = $this->error(
                            $relative,
                            $key->line,
                            "Key '{$name}' in table '{$tablename}' references missing field '{$fieldname}'.",
                        );
                    }
                }
                $keys[] = ['name' => $name, 'type' => $type, 'fields' => $keyfields];
            }
        }
        return $keys;
    }

    /**
     * @param Check[] $checks
     * @param array<string, stdClass> $fields
     * @param array<int, array{name: string, type: string, fields: string[]}> $keys
     */
    private function validateIndexes(
        array &$checks,
        string $relative,
        stdClass $table,
        string $tablename,
        array $fields,
        array $keys,
    ): void {
        $names = [];
        $fieldsets = [];
        $keyfieldsets = [];
        foreach ($keys as $key) {
            if ($key['fields'] !== []) {
                $keyfieldsets[implode(',', $key['fields'])] = $key['name'];
            }
        }

        foreach ($this->childrenNamed($table, 'INDEXES') as $container) {
            foreach ($this->childrenNamed($container, 'INDEX') as $index) {
                $name = $this->attribute($index, 'NAME') ?? '';
                $indexfields = $this->parseFieldList($this->attribute($index, 'FIELDS') ?? '');

                if ($name !== '' && isset($names[$name])) {
                    $checks[] = $this->error($relative, $index->line, "Duplicate index '{$name}' in table '{$tablename}'.");
                }
                if ($name !== '') {
                    $names[$name] = true;
                }
                foreach ($indexfields as $fieldname) {
                    if (!isset($fields[$fieldname])) {
                        $checks[] = $this->error(
                            $relative,
                            $index->line,
                            "Index '{$name}' in table '{$tablename}' references missing field '{$fieldname}'.",
                        );
                    }
                }

                if ($indexfields === []) {
                    continue;
                }
                $fieldset = implode(',', $indexfields);
                if (isset($fieldsets[$fieldset])) {
                    $checks[] = $this->error(
                        $relative,
                        $index->line,
                        "Index '{$name}' duplicates index '{$fieldsets[$fieldset]}' on fields '{$fieldset}' in table '{$tablename}'.",
                    );
                }
                $fieldsets[$fieldset] = $name;

                if (isset($keyfieldsets[$fieldset])) {
                    $checks[] = $this->error(
                        $relative,
                        $index->line,
                        "Index '{$name}' on fields '{$fieldset}' collides with key '{$keyfieldsets[$fieldset]}' in table '{$tablename}'.",
                    );
                }
            }
        }
    }

    /**
     * @param Check[] $checks
     * @param array<string, stdClass> $fields
     * @param array<int, array{name: string, type: string, fields: string[]}> $keys
     */
    private function validateId(
        array &$checks,
        string $relative,
        stdClass $table,
        string $tablename,
        array $fields,
        array $keys,
    ): void {
        if (!isset($fields['id'])) {
            $checks[] = $this->error($relative, $table->line, "Table '{$tablename}' must define an 'id' field.");
            return;
        }

        $id = $fields['id'];
        if ($this->attribute($id, 'TYPE') !== 'int') {
            $checks[] = $this->error($relative, $id->line, "Field '{$tablename}.id' must use TYPE=\"int\".");
        }
        if ($this->attribute($id, 'NOTNULL') !== 'true') {
            $checks[] = $this->error($relative, $id->line, "Field '{$tablename}.id' must use NOTNULL=\"true\".");
        }
        if ($this->attribute($id, 'SEQUENCE') !== 'true') {
            $checks[] = $this->error($relative, $id->line, "Field '{$tablename}.id' must use SEQUENCE=\"true\" for autosequence.");
        }

        $primarykeys = array_values(array_filter(
            $keys,
            static fn(array $key): bool => $key['type'] === 'primary',
        ));
        if ($primarykeys === []) {
            $checks[] = $this->error($relative, $table->line, "Table '{$tablename}' must define a primary key on 'id'.");
            return;
        }
        foreach ($primarykeys as $primarykey) {
            if ($primarykey['fields'] === ['id']) {
                return;
            }
        }
        $checks[] = $this->error($relative, $table->line, "Table '{$tablename}' primary key must reference only the 'id' field.");
    }

    /**
     * @param Check[] $checks
     * @param array<string, stdClass> $tables
     */
    private function validateActivityModule(
        array &$checks,
        ValidationContext $context,
        string $relative,
        string $modname,
        array $tables,
    ): void {
        if (!isset($tables[$modname])) {
            $checks[] = $this->error(
                $relative,
                1,
                "Activity module '{$context->component}' must define its main table '{$modname}'.",
            );
            return;
        }

        $table = $tables[$modname];
        $fields = $this->fieldMapWithoutChecks($table);
        $required = ['id' => 'int', 'course' => 'int', 'name' => 'char', 'timemodified' => 'int'];
        foreach ($required as $fieldname => $type) {
            if (!isset($fields[$fieldname])) {
                $checks[] = $this->error(
                    $relative,
                    $table->line,
                    "Activity module main table '{$modname}' is missing required field '{$fieldname}'.",
                );
            } elseif ($this->attribute($fields[$fieldname], 'TYPE') !== $type) {
                $checks[] = $this->error(
                    $relative,
                    $fields[$fieldname]->line,
                    "Activity module field '{$modname}.{$fieldname}' must use TYPE=\"{$type}\".",
                );
            }
        }

        $hasintro = isset($fields['intro']);
        $hasintroformat = isset($fields['introformat']);
        if ($hasintro xor $hasintroformat) {
            $missing = $hasintro ? 'introformat' : 'intro';
            $checks[] = $this->error(
                $relative,
                $table->line,
                "Activity module main table '{$modname}' must define 'intro' and 'introformat' together; missing '{$missing}'.",
            );
        }
        if ($hasintro && $this->attribute($fields['intro'], 'TYPE') !== 'text') {
            $checks[] = $this->error($relative, $fields['intro']->line, "Activity module field '{$modname}.intro' must use TYPE=\"text\".");
        }
        if ($hasintroformat && $this->attribute($fields['introformat'], 'TYPE') !== 'int') {
            $checks[] = $this->error(
                $relative,
                $fields['introformat']->line,
                "Activity module field '{$modname}.introformat' must use TYPE=\"int\".",
            );
        }
    }

    /** @return array<string, stdClass> */
    private function fieldMapWithoutChecks(stdClass $table): array {
        $fields = [];
        foreach ($this->childrenNamed($table, 'FIELDS') as $container) {
            foreach ($this->childrenNamed($container, 'FIELD') as $field) {
                $name = $this->attribute($field, 'NAME');
                if ($name !== null && $name !== '' && !isset($fields[$name])) {
                    $fields[$name] = $field;
                }
            }
        }
        return $fields;
    }

    /** @return stdClass[] */
    private function childrenNamed(stdClass $element, string $name): array {
        return array_values(array_filter(
            $element->children,
            static fn(stdClass $child): bool => $child->name === $name,
        ));
    }

    /**
     * Method attribute.
     *
     * @param stdClass $element Parameter element.
     * @param string $name Parameter name.
     * @return ?string Return value.
     */
    private function attribute(stdClass $element, string $name): ?string {
        return array_key_exists($name, $element->attributes) ? $element->attributes[$name] : null;
    }

    /** @return string[] */
    private function parseFieldList(string $fields): array {
        return array_values(array_filter(
            array_map('trim', explode(',', $fields)),
            static fn(string $field): bool => $field !== '',
        ));
    }

    /** @param Check[] $checks */
    private function hasErrors(array $checks): bool {
        foreach ($checks as $check) {
            if ($check->isError()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Method error.
     *
     * @param string $file Parameter file.
     * @param int $line Parameter line.
     * @param string $message Parameter message.
     * @return Check Return value.
     */
    private function error(string $file, int $line, string $message): Check {
        return new Check(false, $this->name(), $file, max(1, $line), '', $message);
    }

    /**
     * XMLDB uses a very small XML subset. Parse that subset here so the rule
     * does not require Moodle, DOM, SimpleXML or XMLReader.
     *
     * @return array{0: ?stdClass, 1: ?array{line: int, message: string}}
     */
    private function parseXml(string $xml): array {
        if (str_starts_with($xml, "\xEF\xBB\xBF")) {
            $xml = substr($xml, 3);
        }
        if (trim($xml) === '') {
            return [null, ['line' => 1, 'message' => 'file is empty.']];
        }

        $root = null;
        $stack = [];
        $offset = 0;
        $length = strlen($xml);

        while ($offset < $length) {
            $lt = strpos($xml, '<', $offset);
            if ($lt === false) {
                if (trim(substr($xml, $offset)) !== '') {
                    return [null, ['line' => $this->lineAt($xml, $offset), 'message' => 'text found after the root element.']];
                }
                break;
            }

            $text = substr($xml, $offset, $lt - $offset);
            if (trim($text) !== '') {
                return [null, [
                    'line' => $this->lineAt($xml, $offset),
                    'message' => $stack === []
                        ? 'text found outside the root element.'
                        : 'text content is not allowed inside XMLDB elements.',
                ]];
            }

            if (substr($xml, $lt, 4) === '<!--') {
                $end = strpos($xml, '-->', $lt + 4);
                if ($end === false) {
                    return [null, ['line' => $this->lineAt($xml, $lt), 'message' => 'unclosed XML comment.']];
                }
                $offset = $end + 3;
                continue;
            }
            if (substr($xml, $lt, 2) === '<?') {
                $end = strpos($xml, '?>', $lt + 2);
                if ($end === false) {
                    return [null, ['line' => $this->lineAt($xml, $lt), 'message' => 'unclosed processing instruction.']];
                }
                if ($stack !== []) {
                    return [null, ['line' => $this->lineAt($xml, $lt), 'message' => 'processing instruction inside XMLDB element.']];
                }
                $offset = $end + 2;
                continue;
            }
            if (substr($xml, $lt, 2) === '<!') {
                return [null, ['line' => $this->lineAt($xml, $lt), 'message' => 'unsupported XML declaration.']];
            }

            if (substr($xml, $lt, 2) === '</') {
                $gt = strpos($xml, '>', $lt + 2);
                if ($gt === false) {
                    return [null, ['line' => $this->lineAt($xml, $lt), 'message' => 'unclosed closing tag.']];
                }
                $name = trim(substr($xml, $lt + 2, $gt - $lt - 2));
                if ($stack === []) {
                    return [null, ['line' => $this->lineAt($xml, $lt), 'message' => "unexpected closing tag </{$name}>."]];
                }
                $current = array_pop($stack);
                if ($current->name !== $name) {
                    return [null, [
                        'line' => $this->lineAt($xml, $lt),
                        'message' => "closing tag </{$name}> does not match <{$current->name}> opened on line {$current->line}.",
                    ]];
                }
                $offset = $gt + 1;
                continue;
            }

            $gt = $this->findTagEnd($xml, $lt + 1);
            if ($gt === null) {
                return [null, ['line' => $this->lineAt($xml, $lt), 'message' => 'unclosed start tag.']];
            }
            $raw = substr($xml, $lt + 1, $gt - $lt - 1);
            $selfclosing = preg_match('/\/\s*$/', $raw) === 1;
            if ($selfclosing) {
                $raw = preg_replace('/\/\s*$/', '', $raw) ?? $raw;
            }
            $raw = trim($raw);
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_.:-]*)/', $raw, $match) !== 1) {
                return [null, ['line' => $this->lineAt($xml, $lt), 'message' => 'invalid start tag.']];
            }

            $name = $match[1];
            [$attributes, $attributeerror] = $this->parseAttributes(
                substr($raw, strlen($name)),
                $this->lineAt($xml, $lt),
            );
            if ($attributeerror !== null) {
                return [null, $attributeerror];
            }

            $node = (object)[
                'name' => $name,
                'attributes' => $attributes,
                'children' => [],
                'line' => $this->lineAt($xml, $lt),
            ];
            if ($stack === []) {
                if ($root !== null) {
                    return [null, ['line' => $node->line, 'message' => 'multiple root elements are not allowed.']];
                }
                $root = $node;
            } else {
                $stack[count($stack) - 1]->children[] = $node;
            }
            if (!$selfclosing) {
                $stack[] = $node;
            }
            $offset = $gt + 1;
        }

        if ($stack !== []) {
            $current = $stack[count($stack) - 1];
            return [null, ['line' => $current->line, 'message' => "unclosed <{$current->name}> element."]];
        }
        if ($root === null) {
            return [null, ['line' => 1, 'message' => 'root element is missing.']];
        }
        return [$root, null];
    }

    /** @return array{0: array<string, string>, 1: ?array{line: int, message: string}} */
    private function parseAttributes(string $raw, int $line): array {
        $attributes = [];
        $offset = 0;
        $length = strlen($raw);
        while ($offset < $length) {
            if (preg_match('/\G\s+/A', $raw, $space, 0, $offset) === 1) {
                $offset += strlen($space[0]);
            }
            if ($offset >= $length) {
                break;
            }
            if (preg_match('/\G([A-Za-z_][A-Za-z0-9_.:-]*)\s*=\s*("[^"]*"|\'[^\']*\')/A', $raw, $match, 0, $offset) !== 1) {
                return [[], ['line' => $line, 'message' => 'invalid or unquoted XML attribute.']];
            }
            $name = $match[1];
            if (array_key_exists($name, $attributes)) {
                return [[], ['line' => $line, 'message' => "duplicate XML attribute '{$name}'."]];
            }
            $value = substr($match[2], 1, -1);
            if (preg_match('/&(?!amp;|lt;|gt;|quot;|apos;|#\d+;|#x[0-9A-Fa-f]+;)/', $value) === 1) {
                return [[], ['line' => $line, 'message' => "invalid XML entity in attribute '{$name}'."]];
            }
            $attributes[$name] = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $offset += strlen($match[0]);
        }
        return [$attributes, null];
    }

    /**
     * Method findTagEnd.
     *
     * @param string $xml Parameter xml.
     * @param int $offset Parameter offset.
     * @return ?int Return value.
     */
    private function findTagEnd(string $xml, int $offset): ?int {
        $quote = null;
        for ($index = $offset, $length = strlen($xml); $index < $length; $index++) {
            $char = $xml[$index];
            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '>') {
                return $index;
            }
        }
        return null;
    }

    /**
     * Method lineAt.
     *
     * @param string $xml Parameter xml.
     * @param int $offset Parameter offset.
     * @return int Return value.
     */
    private function lineAt(string $xml, int $offset): int {
        return substr_count($xml, "\n", 0, max(0, $offset)) + 1;
    }
}
