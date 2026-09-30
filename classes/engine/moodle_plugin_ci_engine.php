<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_pluginvalidator\engine;

use coding_exception;
use core_component;
use curl;
use MoodlePluginCI\Bridge\MoodlePlugin;
use MoodlePluginCI\PluginValidate\Plugin;
use MoodlePluginCI\PluginValidate\PluginValidate;
use MoodlePluginCI\PluginValidate\Requirements\RequirementsResolver;
use PHP_CodeSniffer\Runner;
use Symfony\Component\Finder\Finder;

/**
 * Moodle HQ moodle-plugin-ci validation engine.
 *
 * The official PHAR is loaded as a PHP library. The adapter executes, in order:
 * savepoints, validate and phpcs. No moodle-plugin-ci CLI process is started.
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_plugin_ci_engine implements validation_engine_interface {
    /** GitHub latest release endpoint. */
    private const RELEASE_API =
        'https://api.github.com/repos/moodlehq/moodle-plugin-ci/releases/latest';

    /** Official release asset. */
    private const ASSET_NAME = 'moodle-plugin-ci.phar';

    /** Directory used to store the downloaded engine. */
    private const ENGINE_DIRECTORY = 'moodle-plugin-ci';

    /** PHPCS sniffs intentionally excluded from this validator. */
    private const PHPCS_EXCLUDE =
        'Universal.WhiteSpace.CommaSpacing,'
        . 'PSR12.Classes.ClassInstantiation,'
        . 'PSR12.Classes.OpeningBraceSpace,'
        . 'Universal.Lists.DisallowLongListSyntax,'
        . 'PSR2.Methods.FunctionCallSignature,'
        . 'PSR12.ControlStructures.ControlStructureSpacing,'
        . 'Squiz.WhiteSpace.ControlStructureSpacing,'
        . 'PSR2.ControlStructures.SwitchDeclaration,'
        . 'Squiz.Functions.MultiLineFunctionDeclaration,'
        . 'PSR2.Classes.ClassDeclaration,'
        . 'Universal.OOStructures.AlphabeticExtendsImplements';

    public function get_id(): string {
        return 'moodle_plugin_ci';
    }

    public function get_name(): string {
        return get_string('engine_moodlepluginci', 'local_pluginvalidator');
    }

    public function get_description(): string {
        return get_string('engine_moodlepluginci_desc', 'local_pluginvalidator');
    }

    public function get_result_format(): string {
        return validation_engine_interface::RESULT_TEXT;
    }

    public function get_status(): array {
        $path = $this->get_engine_path();
        if ($path === null) {
            return [
                'available' => false,
                'version' => '',
                'source' => '',
                'path' => '',
            ];
        }

        $downloaded = realpath($this->get_downloaded_path());
        $active = realpath($path);
        $source = ($downloaded !== false && $downloaded === $active)
            ? get_string('enginesourcedownloaded', 'local_pluginvalidator')
            : get_string('enginesourcebundled', 'local_pluginvalidator');

        return [
            'available' => true,
            'version' => (string)get_config(
                'local_pluginvalidator',
                'engine_' . $this->get_id() . '_version'
            ),
            'source' => $source,
            'path' => $path,
        ];
    }

    public function install_latest(): array {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        $release = $this->get_latest_release();
        $asset = null;

        foreach ($release['assets'] ?? [] as $candidate) {
            if (($candidate['name'] ?? '') === self::ASSET_NAME) {
                $asset = $candidate;
                break;
            }
        }

        if ($asset === null || empty($asset['browser_download_url'])) {
            throw new coding_exception(
                'The latest moodle-plugin-ci release does not contain ' . self::ASSET_NAME . '.'
            );
        }

        $directory = $this->get_engine_directory();
        if (!is_dir($directory) && !mkdir($directory, $CFG->directorypermissions, true) && !is_dir($directory)) {
            throw new coding_exception('Unable to create moodle-plugin-ci engine directory.');
        }

        $target = $this->get_downloaded_path();
        $temporary = $directory . '/.moodle-plugin-ci-' . bin2hex(random_bytes(8)) . '.phar';

        $download = new curl();
        $download->setHeader([
            'User-Agent: Moodle-local_pluginvalidator',
        ]);
        $ok = $download->download_one($asset['browser_download_url'], null, [
            'filepath' => $temporary,
            'timeout' => 300,
            'followlocation' => true,
            'maxredirs' => 5,
        ]);

        if ($ok !== true || !is_readable($temporary) || filesize($temporary) === 0) {
            @unlink($temporary);
            $message = is_string($ok) ? $ok : 'Download failed.';
            throw new coding_exception($message);
        }

        if (!$this->is_engine_path($temporary)) {
            @unlink($temporary);
            throw new coding_exception('Downloaded moodle-plugin-ci PHAR is not a loadable library.');
        }

        $backup = $target . '.previous';
        @unlink($backup);

        if (is_file($target) && !@rename($target, $backup)) {
            @unlink($temporary);
            throw new coding_exception('Unable to replace the current moodle-plugin-ci engine.');
        }

        if (!@rename($temporary, $target)) {
            if (is_file($backup)) {
                @rename($backup, $target);
            }
            @unlink($temporary);
            throw new coding_exception('Unable to activate the downloaded moodle-plugin-ci engine.');
        }

        @unlink($backup);

        $version = (string)($release['tag_name'] ?? '');
        set_config('engine_' . $this->get_id() . '_version', $version, 'local_pluginvalidator');
        set_config('engine_' . $this->get_id() . '_updated', time(), 'local_pluginvalidator');

        return [
            'version' => $version,
        ];
    }

    public function validate(array $plugin): array {
        $enginepath = $this->get_engine_path();
        if ($enginepath === null) {
            throw new coding_exception(get_string('enginenotinstalled', 'local_pluginvalidator'));
        }

        try {
            $this->load_library($enginepath);

            $plugin['rootdir'] = $this->resolve_plugin_root($plugin);

            $steps = [
                $this->run_savepoints($plugin),
                $this->run_validate($plugin),
                $this->run_phpcs($plugin, $enginepath),
            ];

            $success = true;
            $output = [];

            foreach ($steps as $step) {
                $output[] = '$ ' . $step['command'];
                if ($step['output'] !== '') {
                    $output[] = $step['output'];
                }
                $output[] = '';

                if (!$step['success']) {
                    $success = false;
                }
            }

            return [
                'schema' => 1,
                'engine' => $this->get_id(),
                'format' => $this->get_result_format(),
                'component' => $plugin['component'],
                'success' => $success,
                'status' => $success ? 'ok' : 'error',
                'output' => trim(implode("\n", $output)),
            ];
        } catch (\Throwable $e) {
            return [
                'schema' => 1,
                'engine' => $this->get_id(),
                'format' => $this->get_result_format(),
                'component' => $plugin['component'],
                'success' => false,
                'status' => 'error',
                'output' => '',
                'runtimeError' => [
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Executes the moodle-plugin-ci savepoints logic directly in PHP.
     *
     * @param array $plugin Plugin information.
     * @return array{command: string, success: bool, output: string}
     */
    private function run_savepoints(array $plugin): array {
        $rootupgrade = rtrim($plugin['rootdir'], DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'upgrade.php';

        // Keep the same gate used by moodle-plugin-ci SavePointsCommand.
        if (!is_file($rootupgrade)) {
            return [
                'command' => 'moodle-plugin-ci savepoints',
                'success' => true,
                'output' => 'No relevant files found to process, free pass!'
                    . "\nChecked: " . $rootupgrade,
            ];
        }

        $files = $this->find_upgrade_files($plugin['rootdir']);

        // The root upgrade.php is mandatory here. Seed it explicitly so symlinked
        // plugin roots or filesystem iterator differences cannot hide it.
        $files[$rootupgrade] = $rootupgrade;
        $files = array_values($files);
        sort($files);

        $success = true;
        $output = [];

        foreach ($files as $file) {
            $relative = $this->relative_path($plugin['rootdir'], $file);
            $output[] = '  - ' . $relative . ':';

            $contents = file_get_contents($file);
            if ($contents === false) {
                $output[] = '    + ERROR: unable to read upgrade.php';
                $success = false;
                continue;
            }

            $functionregexp = '\\s*function\\s+xmldb_[a-zA-Z0-9_]+?_upgrade\\s*\\(.*?version.*?\\)'
                . '(?::\\sbool)?\\s*(?=\\{)';
            $returnregexp = '\\s*return true;';
            $savepointregexp =
                'upgrade_(main|mod|block|plugin)_savepoint\\s*?\\(\\s*?true\\s*?,\\s*?([0-9.]{8,13})\\s*?.*?\\);';
            $ifregexp = 'if\\s+?\\(\\s*?\\$oldversion\\s*?<\\s*?([0-9.]{8,13}).*?\\)\\s*?';

            $functioncount = preg_match_all('@' . $functionregexp . '@is', $contents, $matches);
            if (!$functioncount) {
                $output[] = '    + ERROR: upgrade function not found';
                $success = false;
                continue;
            }

            if ($functioncount !== 1) {
                $output[] = '    + ERROR: multiple upgrade functions detected';
                $success = false;
                continue;
            }

            $versionfile = dirname(dirname($file)) . '/version.php';
            $moodle23andup = false;
            if (is_file($versionfile)) {
                $versioncontents = file_get_contents($versionfile);
                $moodle23andup = $versioncontents !== false
                    && preg_match('/^\\s*\\$branch\\s*=/m', $versioncontents) === 1;
            }

            if ($moodle23andup) {
                $returncount = preg_match_all('@' . $returnregexp . '@is', $contents, $matches);
                if (!$returncount) {
                    $output[] = "    + ERROR: 'return true;' not found";
                    $success = false;
                    continue;
                }

                if ($returncount !== 1) {
                    $output[] = "    + ERROR: multiple 'return true;' detected";
                    $success = false;
                    continue;
                }
            }

            $sanitised = $this->replace_unsafe_string_literals($contents);

            if (!preg_match_all(
                '@' . $functionregexp . '.*?(\\{(?>(?>[^{}]+)|(?1))*\\})@is',
                $sanitised,
                $functionmatches
            )) {
                $output[] = '    + NOTE: cannot find upgrade function contents';
                continue;
            }

            $body = trim(trim($functionmatches[1][0], '{}'));

            $ifcount = preg_match_all('@' . $ifregexp . '@is', $body, $ifmatches);
            $savepointcount = preg_match_all('@' . $savepointregexp . '@is', $body, $savepointmatches);

            if ($ifcount > 0 || $savepointcount > 0) {
                if ($ifcount !== $savepointcount) {
                    if ($ifcount < $savepointcount) {
                        $output[] = "    + WARN: Detected fewer 'if' blocks ({$ifcount}) than 'savepoint' calls "
                            . "({$savepointcount}). Repeated savepoints?";
                    } else {
                        $output[] = "    + ERROR: Detected more 'if' blocks ({$ifcount}) than 'savepoint' calls "
                            . "({$savepointcount})";
                    }
                    $success = false;
                } else {
                    $output[] = "    + found {$ifcount} matching 'if' blocks and 'savepoint' calls";
                }
            }

            if ($savepointcount > 0) {
                foreach (array_count_values($savepointmatches[2]) as $version => $count) {
                    if ($count > 1) {
                        $output[] = "    + ERROR: Detected multiple 'savepoint' calls for version {$version}";
                        $success = false;
                    }
                }
            }

            if (!preg_match_all(
                '@(' . $ifregexp . '(\\{(?>(?>[^{}]+)|(?3))*\\}))@is',
                $body,
                $blockmatches
            )) {
                $output[] = "    + NOTE: cannot find 'if' blocks within the upgrade function";
                continue;
            }

            $versions = $blockmatches[2];
            $blocks = $blockmatches[3];

            $previousversion = 0;
            $versionerror = false;
            foreach ($versions as $version) {
                if (!$previousversion) {
                    $previousversion = $version;
                    continue;
                }

                if (((float)$version * 100) < ((float)$previousversion * 100)) {
                    $output[] = "    + ERROR: Wrong order in versions: {$previousversion} and {$version}";
                    $success = false;
                    $versionerror = true;
                }
                $previousversion = $version;
            }

            if (!$versionerror) {
                $output[] = '    + versions in upgrade blocks properly ordered';
            }

            $mismatch = false;
            foreach ($versions as $index => $version) {
                $count = preg_match_all('@' . $savepointregexp . '@is', $blocks[$index], $matches);
                if ($count === 0) {
                    $output[] = "    + ERROR: version {$version} is missing corresponding savepoint call";
                    $success = false;
                    $mismatch = true;
                } else if ($count > 1) {
                    $output[] = "    + ERROR: version {$version} has more than one savepoint call";
                    $success = false;
                    $mismatch = true;
                } else if ($version !== $matches[2][0]) {
                    $output[] = "    + ERROR: version {$version} has wrong savepoint call with version {$matches[2][0]}";
                    $success = false;
                    $mismatch = true;
                }
            }

            if (!$mismatch) {
                $output[] = '    + versions in savepoint calls properly matching upgrade blocks';
            }

            if (is_file($versionfile)) {
                $versioncontents = file_get_contents($versionfile);
                if ($versioncontents !== false
                    && preg_match('/^\\s*\\$(module|plugin)->version\\s*=\\s*([\\d.]+)/m', $versioncontents, $versionmatches) === 1
                ) {
                    foreach ($versions as $version) {
                        if (((float)$versionmatches[2] * 100) < ((float)$version * 100)) {
                            $output[] = "    + ERROR: version {$version} is higher than that defined in "
                                . $this->relative_path($plugin['rootdir'], $versionfile) . ' file';
                            $success = false;
                        }
                    }
                }
            }
        }

        return [
            'command' => 'moodle-plugin-ci savepoints',
            'success' => $success,
            'output' => trim(implode("\n", $output)),
        ];
    }

    /**
     * Executes moodle-plugin-ci validate directly through its PHP classes.
     *
     * @param array $plugin Plugin information.
     * @return array{command: string, success: bool, output: string}
     */
    private function run_validate(array $plugin): array {
        global $CFG;

        $ciplugin = new Plugin(
            $plugin['component'],
            $plugin['type'],
            $plugin['name'],
            $plugin['rootdir']
        );

        $resolver = new RequirementsResolver();
        $requirements = $resolver->resolveRequirements(
            $ciplugin,
            (int)$CFG->branch
        );

        $validator = new PluginValidate($ciplugin, $requirements);
        $validator->verifyRequirements();

        $messages = [];
        foreach ($validator->messages as $message) {
            $messages[] = $this->strip_console_markup((string)$message);
        }

        return [
            'command' => 'moodle-plugin-ci validate',
            'success' => $validator->isValid,
            'output' => trim(implode("\n", $messages)),
        ];
    }

    /**
     * Executes Moodle PHPCS directly in the current PHP process.
     *
     * @param array $plugin Plugin information.
     * @param string $enginepath moodle-plugin-ci PHAR path.
     * @return array{command: string, success: bool, output: string}
     */
    private function run_phpcs(array $plugin, string $enginepath): array {
        $ciplugin = new MoodlePlugin($plugin['rootdir']);
        $ciplugin->context = 'phpcs';

        $files = $ciplugin->getFiles(Finder::create()->name('*.php'));
        if ($files === []) {
            return [
                'command' => $this->phpcs_command_label(),
                'success' => true,
                'output' => 'No relevant files found to process, free pass!',
            ];
        }

        $pharroot = 'phar://' . $enginepath . '/vendor';
        $moodlecs = $pharroot . '/moodlehq/moodle-cs';
        $phpcsextra = $pharroot . '/phpcsstandards/phpcsextra';

        if (!is_readable($moodlecs . '/moodle/ruleset.xml')) {
            return [
                'command' => $this->phpcs_command_label(),
                'success' => false,
                'output' => 'ERROR: Moodle coding standard was not found inside the moodle-plugin-ci PHAR.',
            ];
        }

        if (!is_readable($phpcsextra . '/Universal/ruleset.xml')
            || !is_readable($phpcsextra . '/NormalizedArrays/ruleset.xml')
        ) {
            return [
                'command' => $this->phpcs_command_label(),
                'success' => false,
                'output' => 'ERROR: PHPCSExtra standards required by moodle-cs were not found inside the moodle-plugin-ci PHAR.',
            ];
        }

        $installedpaths = implode(',', [
            $moodlecs,
            $phpcsextra,
        ]);

        $arguments = [
            'phpcs',
            '--runtime-set',
            'installed_paths',
            $installedpaths,
            '--standard=moodle',
            '--extensions=php',
            '-p',
            '-w',
            '-s',
            '--no-cache',
            '--exclude=' . self::PHPCS_EXCLUDE,
            '--no-colors',
            '--report-full',
            '--report-width=132',
            '--encoding=utf-8',
            '--runtime-set',
            'moodleTodoCommentRegex',
            '',
            '--runtime-set',
            'moodleLicenseRegex',
            '',
        ];

        foreach ($files as $file) {
            $arguments[] = $file;
        }

        $oldargv = $_SERVER['argv'] ?? null;
        $oldcwd = getcwd();
        $_SERVER['argv'] = $arguments;

        if ($oldcwd !== false) {
            chdir($plugin['rootdir']);
        }

        ob_start();
        try {
            $runner = new Runner();
            $runner->runPHPCS();
            $output = (string)ob_get_clean();

            $errors = (int)($runner->reporter->totalErrors ?? 0);
            $warnings = (int)($runner->reporter->totalWarnings ?? 0);
            $success = $errors === 0 && $warnings <= 0;
        } catch (\Throwable $e) {
            $buffer = (string)ob_get_clean();
            $output = trim($buffer . "\nERROR: " . $e->getMessage());
            $success = false;
        } finally {
            if ($oldargv === null) {
                unset($_SERVER['argv']);
            } else {
                $_SERVER['argv'] = $oldargv;
            }

            if ($oldcwd !== false) {
                chdir($oldcwd);
            }
        }

        return [
            'command' => $this->phpcs_command_label(),
            'success' => $success,
            'output' => trim($output),
        ];
    }

    /**
     * Returns the PHPCS command label shown in the textual result.
     *
     * @return string
     */
    private function phpcs_command_label(): string {
        return 'moodle-plugin-ci phpcs --max-warnings 0 --exclude="' . self::PHPCS_EXCLUDE . '"';
    }

    /**
     * Finds all db/upgrade.php files below the plugin root.
     *
     * @param string $root Plugin root.
     * @return string[]
     */
    private function find_upgrade_files(string $root): array {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            if (!$item->isFile() || $item->getFilename() !== 'upgrade.php') {
                continue;
            }

            $path = str_replace('\\', '/', $item->getPathname());
            if (!str_ends_with($path, '/db/upgrade.php') || str_contains($path, '/.git/')) {
                continue;
            }

            $files[$item->getPathname()] = $item->getPathname();
        }

        return $files;
    }

    /**
     * Replaces string literals containing bracket characters as the upstream script does.
     *
     * @param string $contents PHP source.
     * @return string
     */
    private function replace_unsafe_string_literals(string $contents): string {
        $regexp = '(["\'])(?:\\\\\1|.)*?\1';
        $discarded = [];

        preg_match_all('@' . $regexp . '@', $contents, $matches);
        foreach (array_unique($matches[0]) as $string) {
            if (preg_match('@[\\[\\(\\{\\<\\>\\}\\)\\]]@', $string)) {
                $replacement = "'<%&%" . (string)(count($discarded) + 1) . "%&%>'";
                $discarded[$replacement] = $string;
            }
        }

        if ($discarded !== []) {
            $contents = str_replace(array_values($discarded), array_keys($discarded), $contents);
        }

        return $contents;
    }

    /**
     * Returns a path relative to the plugin root.
     *
     * @param string $root Plugin root.
     * @param string $path Absolute path.
     * @return string
     */
    private function relative_path(string $root, string $path): string {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $path = str_replace('\\', '/', $path);

        if (str_starts_with($path, $root . '/')) {
            return substr($path, strlen($root) + 1);
        }

        return $path;
    }

    /**
     * Resolves the installed plugin directory from Moodle and the repository data.
     *
     * @param array $plugin Plugin information.
     * @return string
     */
    private function resolve_plugin_root(array $plugin): string {
        $candidates = [];

        if (!empty($plugin['rootdir'])) {
            $candidates[] = (string)$plugin['rootdir'];
        }

        $componentdir = core_component::get_component_directory($plugin['component']);
        if (is_string($componentdir) && $componentdir !== '') {
            $candidates[] = $componentdir;
        }

        foreach (array_unique($candidates) as $candidate) {
            $realpath = realpath($candidate);
            if ($realpath !== false && is_dir($realpath)) {
                return $realpath;
            }

            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        throw new coding_exception(
            'Unable to resolve the installed directory for ' . $plugin['component']
            . '. Candidates: ' . implode(', ', $candidates)
        );
    }

    /**
     * Loads moodle-plugin-ci classes from the official PHAR without running its CLI.
     *
     * @param string $path PHAR path.
     */
    private function load_library(string $path): void {
        if (class_exists(PluginValidate::class) && class_exists(Runner::class)) {
            return;
        }

        $pharroot = 'phar://' . $path;
        $composerautoload = $pharroot . '/vendor/autoload.php';

        if (!is_readable($composerautoload)) {
            throw new coding_exception(
                'Unable to find the moodle-plugin-ci Composer autoloader inside the PHAR.'
            );
        }

        require_once($composerautoload);

        if (!class_exists(PluginValidate::class)) {
            throw new coding_exception(
                'Unable to load ' . PluginValidate::class . ' from the moodle-plugin-ci PHAR.'
            );
        }

        if (!class_exists(Runner::class)) {
            $phpcsautoload = $pharroot . '/vendor/squizlabs/php_codesniffer/autoload.php';

            if (!is_readable($phpcsautoload)) {
                throw new coding_exception(
                    'Unable to find the PHP_CodeSniffer autoloader inside the moodle-plugin-ci PHAR.'
                );
            }

            require_once($phpcsautoload);
        }

        if (!class_exists(Runner::class)) {
            throw new coding_exception(
                'Unable to load ' . Runner::class . ' from the PHP_CodeSniffer bundled in moodle-plugin-ci.'
            );
        }
    }

    /**
     * Queries latest release metadata.
     *
     * @return array
     */
    private function get_latest_release(): array {
        $curl = new curl();
        $curl->setHeader([
            'Accept: application/vnd.github+json',
            'User-Agent: Moodle-local_pluginvalidator',
            'X-GitHub-Api-Version: 2022-11-28',
        ]);
        $response = $curl->get(self::RELEASE_API, null, ['CURLOPT_TIMEOUT' => 30]);

        if ($curl->get_errno()) {
            throw new coding_exception('Unable to query moodle-plugin-ci releases: ' . $curl->error);
        }

        $release = json_decode($response, true);
        if (!is_array($release)) {
            throw new coding_exception('Invalid moodle-plugin-ci release response.');
        }

        return $release;
    }

    /**
     * Returns active engine path.
     *
     * @return string|null
     */
    private function get_engine_path(): ?string {
        $downloaded = $this->get_downloaded_path();
        if ($this->is_engine_path($downloaded)) {
            return $downloaded;
        }

        $bundled = dirname(__DIR__, 2)
            . '/tools/' . self::ENGINE_DIRECTORY . '/' . self::ASSET_NAME;
        if ($this->is_engine_path($bundled)) {
            return $bundled;
        }

        return null;
    }

    /**
     * Checks whether a PHAR contains the autoloader required by this adapter.
     *
     * @param string $path PHAR path.
     * @return bool
     */
    private function is_engine_path(string $path): bool {
        if (!is_readable($path) || !is_file($path) || filesize($path) === 0) {
            return false;
        }

        try {
            return is_readable('phar://' . $path . '/vendor/autoload.php');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Removes Symfony Console decoration markup while preserving upstream text.
     *
     * @param string $message Console message.
     * @return string
     */
    private function strip_console_markup(string $message): string {
        $clean = preg_replace('/<[^>]+>/', '', $message);
        return trim($clean ?? $message);
    }

    /**
     * Returns engine directory.
     *
     * @return string
     */
    private function get_engine_directory(): string {
        global $CFG;
        return $CFG->dataroot . '/local_pluginvalidator/tools/' . self::ENGINE_DIRECTORY;
    }

    /**
     * Returns downloaded PHAR path.
     *
     * @return string
     */
    private function get_downloaded_path(): string {
        return $this->get_engine_directory() . '/' . self::ASSET_NAME;
    }
}
