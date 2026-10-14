<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\PhpPluginMetadata;
use EduardoKraus\MoodleStringValidate\ValidationContext;
use FilesystemIterator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use stdClass;

/**
 * Class SubpluginRule.
 */
final class SubpluginRule implements RuleInterface {
    /**
     * Method name.
     *
     * @return string Return value.
     */
    public function name(): string {
        return 'subplugin';
    }

    /**
     * Method validate.
     *
     * @param ValidationContext $context Parameter context.
     * @return array Return value.
     */
    public function validate(ValidationContext $context): array {
        $file = $context->pluginroot . '/db/subplugins.json';
        if (!is_file($file)) {
            return $this->validateMissingFile($context);
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            return [new Check(false, $this->name(), 'db/subplugins.json', 1, '', 'Unable to read db/subplugins.json.')];
        }

        try {
            $data = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return [new Check(
                false,
                $this->name(),
                'db/subplugins.json',
                1,
                '',
                'Invalid JSON in db/subplugins.json: ' . $exception->getMessage(),
            )];
        }

        if (!$data instanceof stdClass) {
            return [new Check(false, $this->name(), 'db/subplugins.json', 1, '', 'db/subplugins.json must contain a JSON object.')];
        }

        $checks = [new Check(true, $this->name(), 'db/subplugins.json', 1, '', 'db/subplugins.json exists and contains valid JSON.')];

        $allowedtags = ['plugintypes', 'subplugintypes'];
        foreach (array_keys(get_object_vars($data)) as $tag) {
            if (!in_array($tag, $allowedtags, true)) {
                $checks[] = new Check(
                    false,
                    $this->name(),
                    'db/subplugins.json',
                    $this->findJsonKeyLine($contents, (string) $tag),
                    '',
                    "Unknown top-level tag '{$tag}' in db/subplugins.json. Supported tags are 'plugintypes' and 'subplugintypes'.",
                );
            }
        }

        $sections = [];
        foreach ($allowedtags as $section) {
            if (!property_exists($data, $section)) {
                continue;
            }
            if (!$data->{$section} instanceof stdClass) {
                $checks[] = new Check(
                    false,
                    $this->name(),
                    'db/subplugins.json',
                    $this->findJsonKeyLine($contents, $section),
                    '',
                    "The '{$section}' tag must be a JSON object.",
                );
                continue;
            }
            $sections[$section] = get_object_vars($data->{$section});
            if ($sections[$section] === []) {
                $checks[] = new Check(
                    false,
                    $this->name(),
                    'db/subplugins.json',
                    $this->findJsonKeyLine($contents, $section),
                    '',
                    "The '{$section}' tag must not be empty when declared.",
                );
            } else {
                $checks[] = new Check(
                    true,
                    $this->name(),
                    'db/subplugins.json',
                    $this->findJsonKeyLine($contents, $section),
                    '',
                    "The '{$section}' tag is correctly configured as a non-empty JSON object.",
                );
            }
        }

        if ($sections === []) {
            $checks[] = new Check(
                false,
                $this->name(),
                'db/subplugins.json',
                1,
                '',
                "db/subplugins.json must define at least one of the 'subplugintypes' or 'plugintypes' tags.",
            );
            return $checks;
        }

        foreach ($sections as $section => $entries) {
            foreach ($entries as $type => $path) {
                $line = $this->findJsonKeyLine($contents, (string) $type);
                if (!$this->validType((string) $type)) {
                    $checks[] = new Check(false, $this->name(), 'db/subplugins.json', $line, '', "Invalid subplugin type '{$type}'. Use lowercase letters, numbers, and underscores only.");
                    continue;
                }
                if (!is_string($path) || !$this->validPath($path)) {
                    $checks[] = new Check(false, $this->name(), 'db/subplugins.json', $line, '', "Invalid path for subplugin type '{$type}' in '{$section}'. Paths must be non-empty relative paths using '/'.");
                    continue;
                }
                $checks[] = new Check(true, $this->name(), 'db/subplugins.json', $line, '', "Subplugin type '{$type}' has a valid '{$section}' path '{$path}'.");
            }
        }

        if (isset($sections['plugintypes'], $sections['subplugintypes'])) {
            array_push($checks, ...$this->validateSectionParity($context, $contents, $sections['plugintypes'], $sections['subplugintypes']));
        }

        $types = $this->mergedTypes($sections, $contents);
        foreach ($types as $type => $line) {
            foreach (['subplugintype_' . $type, 'subplugintype_' . $type . '_plural'] as $key) {
                $checks[] = $context->checkRequiredString(
                    $this->name(),
                    $key,
                    $file,
                    $line,
                    "required by subplugin type '{$type}' in db/subplugins.json.",
                );
            }
            $checks[] = $this->validatePlugininfoClass($context, $type);
        }

        $localpaths = $this->resolveLocalPaths($context, $sections);
        foreach ($localpaths as $type => $relativepath) {
            array_push($checks, ...$this->validateSubpluginDirectory($context, $type, $relativepath));
        }

        return $checks;
    }

    /** @return Check[] */
    private function validateMissingFile(ValidationContext $context): array {
        foreach (['db/sub-plugins.json', 'db/sub_plugins.json', 'subplugins.json', 'sub-plugins.json'] as $wrongpath) {
            if (is_file($context->pluginroot . '/' . $wrongpath)) {
                return [new Check(
                    false,
                    $this->name(),
                    $wrongpath,
                    1,
                    '',
                    "Found '{$wrongpath}', but Moodle expects the canonical file name 'db/subplugins.json'.",
                )];
            }
        }

        $nested = $this->findNestedMoodlePlugins($context->pluginroot);
        if ($nested !== []) {
            return [new Check(
                false,
                $this->name(),
                'db/subplugins.json',
                1,
                '',
                'Nested Moodle plugins were detected (' . implode(', ', $nested) . ") but db/subplugins.json is missing.",
            )];
        }

        return [new Check(
            true,
            $this->name(),
            'db/subplugins.json',
            1,
            '',
            'db/subplugins.json is not present and no nested Moodle subplugins were detected.',
        )];
    }

    /** @return Check[] */
    private function validateSectionParity(ValidationContext $context, string $contents, array $plugintypes, array $subplugintypes): array {
        $checks = [];
        $pluginKeys = array_keys($plugintypes);
        $subpluginKeys = array_keys($subplugintypes);
        sort($pluginKeys);
        sort($subpluginKeys);

        if ($pluginKeys !== $subpluginKeys) {
            $checks[] = new Check(
                false,
                $this->name(),
                'db/subplugins.json',
                1,
                '',
                "The 'plugintypes' and 'subplugintypes' tags must contain the same subplugin type keys when both are declared.",
            );
            return $checks;
        }

        $checks[] = new Check(true, $this->name(), 'db/subplugins.json', 1, '', "The 'plugintypes' and 'subplugintypes' tags contain the same type keys.");
        $parentpath = $this->parentMoodlePath($context->component);

        foreach ($subpluginKeys as $type) {
            $relative = $subplugintypes[$type];
            $legacy = $plugintypes[$type];
            if (!is_string($relative) || !is_string($legacy) || !$this->validPath($relative) || !$this->validPath($legacy)) {
                continue;
            }

            if ($parentpath !== null) {
                $expected = $parentpath . '/' . $relative;
                if ($legacy !== $expected) {
                    $checks[] = new Check(
                        false,
                        $this->name(),
                        'db/subplugins.json',
                        $this->findJsonKeyLine($contents, (string) $type),
                        '',
                        "Subplugin type '{$type}' has inconsistent paths. Expected plugintypes path '{$expected}' for subplugintypes path '{$relative}', got '{$legacy}'.",
                    );
                    continue;
                }
            } elseif (!str_ends_with($legacy, '/' . $relative) && $legacy !== $relative) {
                $checks[] = new Check(
                    false,
                    $this->name(),
                    'db/subplugins.json',
                    $this->findJsonKeyLine($contents, (string) $type),
                    '',
                    "Subplugin type '{$type}' has inconsistent plugintypes/subplugintypes paths '{$legacy}' and '{$relative}'.",
                );
                continue;
            }

            $checks[] = new Check(true, $this->name(), 'db/subplugins.json', $this->findJsonKeyLine($contents, (string) $type), '', "Subplugin type '{$type}' has matching legacy and modern paths.");
        }

        return $checks;
    }

    /** @return Check[] */
    private function validateSubpluginDirectory(ValidationContext $context, string $type, string $relativepath): array {
        $directory = $context->pluginroot . '/' . $relativepath;
        if (!is_dir($directory)) {
            return [new Check(false, $this->name(), $relativepath, 1, '', "Subplugin type '{$type}' points to missing directory '{$relativepath}'.")];
        }

        $checks = [new Check(true, $this->name(), $relativepath, 1, '', "Subplugin type '{$type}' directory exists: '{$relativepath}'.")];
        $entries = scandir($directory) ?: [];
        $subplugins = 0;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }
            $subroot = $directory . '/' . $entry;
            if (!is_dir($subroot)) {
                continue;
            }
            $subplugins++;
            array_push($checks, ...$this->validateSubplugin($context, $type, $entry, $subroot));
        }

        $checks[] = new Check(true, $this->name(), $relativepath, 1, '', "Subplugin type '{$type}' contains {$subplugins} bundled subplugin" . ($subplugins === 1 ? '' : 's') . '.');
        return $checks;
    }

    /** @return Check[] */
    private function validateSubplugin(ValidationContext $context, string $type, string $name, string $subroot): array {
        $versionfile = $subroot . '/version.php';
        $relativeversion = $context->relative($versionfile);
        if (!is_file($versionfile)) {
            return [new Check(false, $this->name(), $context->relative($subroot) . '/version.php', 1, '', "Subplugin directory '{$name}' is missing version.php.")];
        }

        $checks = [new Check(true, $this->name(), $relativeversion, 1, '', "Subplugin '{$name}' has version.php.")];
        $metadata = new PhpPluginMetadata($versionfile);
        $expectedcomponent = $type . '_' . $name;
        $component = $metadata->component();
        if ($component !== $expectedcomponent) {
            $checks[] = new Check(false, $this->name(), $relativeversion, $metadata->lineFor('$plugin->component'), '', "Subplugin '{$name}' component must be '{$expectedcomponent}', got '" . ($component ?? '[missing]') . "'.");
        } else {
            $checks[] = new Check(true, $this->name(), $relativeversion, $metadata->lineFor('$plugin->component'), '', "Subplugin '{$name}' correctly identifies itself as '{$expectedcomponent}'.");
        }

        $version = $metadata->version();
        if ($version === null || $version <= 0) {
            $checks[] = new Check(false, $this->name(), $relativeversion, 1, '', "Subplugin '{$expectedcomponent}' has missing or invalid \$plugin->version.");
        } else {
            $checks[] = new Check(true, $this->name(), $relativeversion, $metadata->lineFor('$plugin->version'), '', "Subplugin '{$expectedcomponent}' version is configured: {$version}.");
        }

        $dependencies = $metadata->dependencies();
        if (!array_key_exists($context->component, $dependencies)) {
            $checks[] = new Check(false, $this->name(), $relativeversion, $metadata->lineFor('$plugin->dependencies'), '', "Subplugin '{$expectedcomponent}' must declare a dependency on parent plugin '{$context->component}' in \$plugin->dependencies.");
            return $checks;
        }

        $required = $dependencies[$context->component];
        $checks[] = new Check(true, $this->name(), $relativeversion, $metadata->lineFor($context->component), '', "Subplugin '{$expectedcomponent}' declares dependency on parent '{$context->component}'.");

        $parentversionfile = $context->pluginroot . '/version.php';
        if (is_file($parentversionfile)) {
            $parentversion = (new PhpPluginMetadata($parentversionfile))->version();
            if ($parentversion !== null && $required !== 'ANY_VERSION' && is_numeric($required)) {
                if ((float) $required > (float) $parentversion) {
                    $checks[] = new Check(false, $this->name(), $relativeversion, $metadata->lineFor($context->component), '', "Subplugin '{$expectedcomponent}' requires parent '{$context->component}' version {$required}, but the bundled parent version is {$parentversion}.");
                } else {
                    $checks[] = new Check(true, $this->name(), $relativeversion, $metadata->lineFor($context->component), '', "Subplugin '{$expectedcomponent}' parent dependency version {$required} is compatible with bundled parent version {$parentversion}.");
                }
            } elseif ($required === 'ANY_VERSION') {
                $checks[] = new Check(true, $this->name(), $relativeversion, $metadata->lineFor($context->component), '', "Subplugin '{$expectedcomponent}' accepts ANY_VERSION of parent '{$context->component}'.");
            }
        }

        return $checks;
    }

    /**
     * Method validatePlugininfoClass.
     *
     * @param ValidationContext $context Parameter context.
     * @param string $type Parameter type.
     * @return Check Return value.
     */
    private function validatePlugininfoClass(ValidationContext $context, string $type): Check {
        $relativefile = 'classes/plugininfo/' . $type . '.php';
        $file = $context->pluginroot . '/' . $relativefile;
        $expectednamespace = $context->component . '\\plugininfo';
        $expectedclass = '\\' . $expectednamespace . '\\' . $type;

        if (!is_file($file)) {
            return new Check(
                false,
                $this->name(),
                $relativefile,
                1,
                '',
                'Subplugin type "' . $type . '" should define class "' . $expectedclass . '"',
            );
        }

        $source = file_get_contents($file);
        if ($source === false || !$this->declaresClass($source, $expectednamespace, $type)) {
            return new Check(
                false,
                $this->name(),
                $relativefile,
                1,
                '',
                'Subplugin type "' . $type . '" should define class "' . $expectedclass . '"',
            );
        }

        return new Check(
            true,
            $this->name(),
            $relativefile,
            1,
            '',
            'Subplugin type "' . $type . '" defines class "' . $expectedclass . '".',
        );
    }

    /**
     * Method declaresClass.
     *
     * @param string $source Parameter source.
     * @param string $expectednamespace Parameter expectednamespace.
     * @param string $expectedclass Parameter expectedclass.
     * @return bool Return value.
     */
    private function declaresClass(string $source, string $expectednamespace, string $expectedclass): bool {
        $tokens = token_get_all($source);
        $namespace = '';
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = '';
                for ($index++; $index < $count; $index++) {
                    $part = $tokens[$index];
                    if (is_string($part) && ($part === ';' || $part === '{')) {
                        break;
                    }
                    if (is_array($part) && in_array($part[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                        $namespace .= $part[1];
                    }
                }
                continue;
            }

            if ($token[0] !== T_CLASS) {
                continue;
            }

            $previous = $index - 1;
            while ($previous >= 0 && is_array($tokens[$previous]) && $tokens[$previous][0] === T_WHITESPACE) {
                $previous--;
            }
            if ($previous >= 0 && is_array($tokens[$previous]) && $tokens[$previous][0] === T_NEW) {
                continue;
            }

            for ($classindex = $index + 1; $classindex < $count; $classindex++) {
                $classtoken = $tokens[$classindex];
                if (is_array($classtoken) && $classtoken[0] === T_WHITESPACE) {
                    continue;
                }
                if (is_array($classtoken) && $classtoken[0] === T_STRING) {
                    if ($namespace === $expectednamespace && $classtoken[1] === $expectedclass) {
                        return true;
                    }
                }
                break;
            }
        }

        return false;
    }

    /** @return array<string, int> */
    private function mergedTypes(array $sections, string $contents): array {
        $types = [];
        foreach ($sections as $entries) {
            foreach ($entries as $type => $unused) {
                if ($this->validType((string) $type)) {
                    $types[(string) $type] = $this->findJsonKeyLine($contents, (string) $type);
                }
            }
        }
        return $types;
    }

    /** @return array<string, string> */
    private function resolveLocalPaths(ValidationContext $context, array $sections): array {
        $paths = [];
        if (isset($sections['subplugintypes'])) {
            foreach ($sections['subplugintypes'] as $type => $path) {
                if ($this->validType((string) $type) && is_string($path) && $this->validPath($path)) {
                    $paths[(string) $type] = $path;
                }
            }
            return $paths;
        }

        $parentpath = $this->parentMoodlePath($context->component);
        if ($parentpath === null || !isset($sections['plugintypes'])) {
            return [];
        }
        foreach ($sections['plugintypes'] as $type => $path) {
            if (!is_string($path) || !$this->validPath($path)) {
                continue;
            }
            $prefix = $parentpath . '/';
            if (str_starts_with($path, $prefix)) {
                $paths[(string) $type] = substr($path, strlen($prefix));
            }
        }
        return $paths;
    }

    /**
     * Method validType.
     *
     * @param string $type Parameter type.
     * @return bool Return value.
     */
    private function validType(string $type): bool {
        return (bool) preg_match('/^[a-z][a-z0-9_]*$/', $type);
    }

    /**
     * Method validPath.
     *
     * @param string $path Parameter path.
     * @return bool Return value.
     */
    private function validPath(string $path): bool {
        if ($path === '' || $path[0] === '/' || str_ends_with($path, '/') || str_contains($path, '\\')) {
            return false;
        }
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return false;
            }
        }
        return true;
    }

    /**
     * Method parentMoodlePath.
     *
     * @param string $component Parameter component.
     * @return ?string Return value.
     */
    private function parentMoodlePath(string $component): ?string {
        $parts = explode('_', $component, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$type, $name] = $parts;
        return match ($type) {
            'local' => 'local/' . $name,
            'mod' => 'mod/' . $name,
            'tool' => 'admin/tool/' . $name,
            'editor' => 'lib/editor/' . $name,
            default => null,
        };
    }

    /** @return string[] */
    private function findNestedMoodlePlugins(string $root): array {
        $found = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $iterator->setMaxDepth(3);
        foreach ($iterator as $item) {
            if (!$item->isFile() || $item->getFilename() !== 'version.php' || $item->getPathname() === $root . '/version.php') {
                continue;
            }
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
            if (preg_match('#^(vendor|node_modules|tests?|\.github)/#', $relative)) {
                continue;
            }
            $metadata = new PhpPluginMetadata($item->getPathname());
            if ($metadata->component() !== null) {
                $found[] = dirname($relative);
            }
        }
        sort($found);
        return array_values(array_unique($found));
    }

    /**
     * Method findJsonKeyLine.
     *
     * @param string $contents Parameter contents.
     * @param string $key Parameter key.
     * @return int Return value.
     */
    private function findJsonKeyLine(string $contents, string $key): int {
        $lines = preg_split('/\R/', $contents) ?: [];
        foreach ($lines as $index => $line) {
            if (preg_match('/[\"\']' . preg_quote($key, '/') . '[\"\']\s*:/', $line)) {
                return $index + 1;
            }
        }
        return 1;
    }
}
