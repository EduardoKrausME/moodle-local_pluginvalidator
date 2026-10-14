<?php

declare(strict_types=1);

namespace EduardoKraus\MoodleStringValidate\Rule;

use EduardoKraus\MoodleStringValidate\Check;
use EduardoKraus\MoodleStringValidate\ValidationContext;
use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Class AmdModulesRule.
 */
final class AmdModulesRule implements RuleInterface {
    private const IGNORED_DIRECTORIES = [
        '.git',
        'node_modules',
        'vendor',
        'thirdparty',
        'third_party',
    ];

    /**
     * Method name.
     *
     * @return string Return value.
     */
    public function name(): string {
        return 'amd';
    }

    /**
     * Method validate.
     *
     * @param ValidationContext $context Parameter context.
     * @return array Return value.
     */
    public function validate(ValidationContext $context): array {
        $checks = [];
        $sourceModules = $this->sourceModules($context->pluginroot);

        foreach ($this->doubleMinifiedBuildFiles($context->pluginroot) as $file) {
            $relative = $context->relative($file);
            $checks[] = new Check(
                false,
                $this->name(),
                $relative,
                1,
                $relative,
                "AMD build file '{$relative}' must not use the duplicated .min.min.js suffix.",
            );
        }

        foreach ($sourceModules as $module => $sourcefile) {
            $relative = $context->relative($sourcefile);
            $buildrelative = 'amd/build/' . $module . '.min.js';
            $moduleid = $context->component . '/' . $module;
            if (!is_file($context->pluginroot . '/' . $buildrelative)) {
                $checks[] = new Check(
                    false,
                    $this->name(),
                    $relative,
                    1,
                    $moduleid,
                    "AMD source module '{$moduleid}' is missing compiled build '{$buildrelative}'.",
                );
                continue;
            }

            $checks[] = new Check(
                true,
                $this->name(),
                $relative,
                1,
                $moduleid,
                "AMD source module '{$moduleid}' has compiled build '{$buildrelative}'.",
            );
        }

        foreach ($this->references($context) as $reference) {
            $moduleid = $reference['module'];
            $module = substr($moduleid, strlen($context->component) + 1);
            $sourcerelative = 'amd/src/' . $module . '.js';
            $buildrelative = 'amd/build/' . $module . '.min.js';
            $sourceexists = is_file($context->pluginroot . '/' . $sourcerelative);
            $buildexists = is_file($context->pluginroot . '/' . $buildrelative);

            if (!$sourceexists && !$buildexists) {
                $checks[] = new Check(
                    false,
                    $this->name(),
                    $reference['file'],
                    $reference['line'],
                    $moduleid,
                    "AMD reference '{$moduleid}' does not resolve to '{$sourcerelative}' or '{$buildrelative}'.",
                );
                continue;
            }

            $checks[] = new Check(
                true,
                $this->name(),
                $reference['file'],
                $reference['line'],
                $moduleid,
                "AMD reference '{$moduleid}' resolves to a module in this plugin.",
            );
        }

        if ($checks === []) {
            return [new Check(
                true,
                $this->name(),
                '.',
                1,
                '',
                'No AMD source modules, invalid build names, or current-plugin AMD references require validation.',
            )];
        }

        return $checks;
    }

    /** @return array<string, string> */
    private function sourceModules(string $root): array {
        $src = $root . '/amd/src';
        if (!is_dir($src)) {
            return [];
        }

        $modules = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if (!$item->isFile() || strtolower($item->getExtension()) !== 'js') {
                continue;
            }
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen(rtrim($src, DIRECTORY_SEPARATOR)) + 1));
            $modules[substr($relative, 0, -3)] = $item->getPathname();
        }

        ksort($modules);
        return $modules;
    }

    /** @return string[] */
    private function doubleMinifiedBuildFiles(string $root): array {
        $build = $root . '/amd/build';
        if (!is_dir($build)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($build, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isFile() && str_ends_with(strtolower($item->getFilename()), '.min.min.js')) {
                $files[] = $item->getPathname();
            }
        }

        sort($files);
        return $files;
    }

    /** @return array<int, array{module: string, file: string, line: int}> */
    private function references(ValidationContext $context): array {
        $references = [];
        foreach ($this->referenceFiles($context->pluginroot) as $file) {
            $contents = file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $found = $extension === 'php'
                ? $this->phpReferences($contents, $context->component)
                : $this->mustacheReferences($contents, $context->component);

            foreach ($found as $reference) {
                $references[] = [
                    'module' => $reference['module'],
                    'file' => $context->relative($file),
                    'line' => $reference['line'],
                ];
            }
        }

        return $references;
    }

    /** @return string[] */
    private function referenceFiles(string $root): array {
        $files = [];
        $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator(
            $directory,
            static function (SplFileInfo $item): bool {
                if ($item->isDir()) {
                    return !in_array($item->getFilename(), self::IGNORED_DIRECTORIES, true);
                }
                return true;
            },
        );
        $iterator = new RecursiveIteratorIterator($filter);
        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $extension = strtolower($item->getExtension());
            if ($extension !== 'php' && $extension !== 'mustache') {
                continue;
            }
            $files[] = $item->getPathname();
        }

        sort($files);
        return $files;
    }

    /** @return array<int, array{module: string, line: int}> */
    private function phpReferences(string $contents, string $component): array {
        $searchable = $this->stripPhpComments($contents);
        $pattern = '/\\bjs_call_amd\\s*\\(\\s*([\'"])([^\'"]+)\\1/';
        if (!preg_match_all($pattern, $searchable, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $references = [];
        foreach ($matches[2] as $match) {
            $module = $match[0];
            if (!str_starts_with($module, $component . '/')) {
                continue;
            }
            $references[] = [
                'module' => $module,
                'line' => $this->lineFromOffset($contents, $match[1]),
            ];
        }
        return $references;
    }

    /** @return array<int, array{module: string, line: int}> */
    private function mustacheReferences(string $contents, string $component): array {
        if (!preg_match_all('/{{#js}}(.*?){{\\/js}}/s', $contents, $blocks, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $references = [];
        $modulepattern = '/([\'"])(' . preg_quote($component, '/') . '\\/[A-Za-z0-9_.\\/-]+)\\1/';
        foreach ($blocks[1] as $blockmatch) {
            $block = $blockmatch[0];
            $blockoffset = $blockmatch[1];
            if (!preg_match_all('/\\brequire\\s*\\(\\s*\\[([^\\]]*)\\]/s', $block, $requires, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($requires[1] as $requirematch) {
                $arguments = $requirematch[0];
                $argumentoffset = $requirematch[1];
                if (!preg_match_all($modulepattern, $arguments, $modules, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                foreach ($modules[2] as $modulematch) {
                    $offset = $blockoffset + $argumentoffset + $modulematch[1];
                    $references[] = [
                        'module' => $modulematch[0],
                        'line' => $this->lineFromOffset($contents, $offset),
                    ];
                }
            }
        }
        return $references;
    }

    /**
     * Method stripPhpComments.
     *
     * @param string $contents Parameter contents.
     * @return string Return value.
     */
    private function stripPhpComments(string $contents): string {
        $result = '';
        foreach (token_get_all($contents) as $token) {
            if (!is_array($token)) {
                $result .= $token;
                continue;
            }
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                $result .= preg_replace('/[^\\r\\n]/', ' ', $token[1]);
                continue;
            }
            $result .= $token[1];
        }
        return $result;
    }

    /**
     * Method lineFromOffset.
     *
     * @param string $contents Parameter contents.
     * @param int $offset Parameter offset.
     * @return int Return value.
     */
    private function lineFromOffset(string $contents, int $offset): int {
        return substr_count(substr($contents, 0, $offset), "\n") + 1;
    }
}
