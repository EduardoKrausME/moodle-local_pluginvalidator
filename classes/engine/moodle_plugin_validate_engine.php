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
use curl;
use EduardoKraus\MoodleStringValidate\Validator;

/**
 * EduardoKrausME/moodle-plugin-validate engine.
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_plugin_validate_engine implements validation_engine_interface {
    /** GitHub latest release endpoint. */
    private const RELEASE_API =
        'https://api.github.com/repos/EduardoKrausME/moodle-plugin-validate/releases/latest';

    /** Directory used to store the downloaded validator. */
    private const ENGINE_DIRECTORY = 'moodle-plugin-validate';

    public function get_id(): string {
        return 'moodle_plugin_validate';
    }

    public function get_name(): string {
        return get_string('engine_moodlepluginvalidate', 'local_pluginvalidator');
    }

    public function get_description(): string {
        return get_string('engine_moodlepluginvalidate_desc', 'local_pluginvalidator');
    }

    public function get_result_format(): string {
        return validation_engine_interface::RESULT_STRUCTURED;
    }

    public function get_status(): array {
        $root = $this->get_engine_root();
        if ($root === null) {
            return [
                'available' => false,
                'version' => '',
                'source' => '',
                'path' => '',
            ];
        }

        $downloaded = realpath($this->get_engine_directory());
        $active = realpath($root);
        $source = ($downloaded !== false && $downloaded === $active)
            ? get_string('enginesourcedownloaded', 'local_pluginvalidator')
            : get_string('enginesourcebundled', 'local_pluginvalidator');

        $version = (string)get_config(
            'local_pluginvalidator',
            'engine_' . $this->get_id() . '_version'
        );

        // Backward compatibility with versions that supported only one engine.
        if ($version === '') {
            $version = (string)get_config('local_pluginvalidator', 'engineversion');
        }

        return [
            'available' => true,
            'version' => $version,
            'source' => $source,
            'path' => $root,
        ];
    }

    public function install_latest(): array {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->libdir . '/filestorage/file_storage.php');

        $release = $this->get_latest_release();
        if (empty($release['tag_name']) || empty($release['zipball_url'])) {
            throw new coding_exception('Invalid moodle-plugin-validate release response.');
        }

        $directory = dirname($this->get_engine_directory());
        if (!is_dir($directory) && !mkdir($directory, $CFG->directorypermissions, true) && !is_dir($directory)) {
            throw new coding_exception('Unable to create validation engine directory.');
        }

        $suffix = bin2hex(random_bytes(8));
        $archive = $directory . '/.' . self::ENGINE_DIRECTORY . '-' . $suffix . '.zip';
        $extractdirectory = $directory . '/.' . self::ENGINE_DIRECTORY . '-' . $suffix;

        @unlink($archive);
        if (is_dir($extractdirectory)) {
            remove_dir($extractdirectory);
        }

        $download = new curl();
        $download->setHeader([
            'Accept: application/vnd.github+json',
            'User-Agent: Moodle-local_pluginvalidator',
            'X-GitHub-Api-Version: 2022-11-28',
        ]);
        $ok = $download->download_one($release['zipball_url'], null, [
            'filepath' => $archive,
            'timeout' => 300,
            'followlocation' => true,
            'maxredirs' => 5,
        ]);

        if ($ok !== true || !is_readable($archive)) {
            @unlink($archive);
            $message = is_string($ok) ? $ok : 'Download failed.';
            throw new coding_exception($message);
        }

        if (!mkdir($extractdirectory, $CFG->directorypermissions, true) && !is_dir($extractdirectory)) {
            @unlink($archive);
            throw new coding_exception('Unable to create temporary validation engine directory.');
        }

        $packer = get_file_packer('application/zip');
        $files = $packer->extract_to_pathname($archive, $extractdirectory);
        if (!$files) {
            @unlink($archive);
            remove_dir($extractdirectory);
            throw new coding_exception('Unable to extract the moodle-plugin-validate release.');
        }

        $sourceroot = $this->find_extracted_root($extractdirectory);
        if ($sourceroot === null) {
            @unlink($archive);
            remove_dir($extractdirectory);
            throw new coding_exception('The moodle-plugin-validate library was not found in the downloaded release.');
        }

        $target = $this->get_engine_directory();
        $backup = $directory . '/.' . self::ENGINE_DIRECTORY . '-previous';

        if (is_dir($backup)) {
            remove_dir($backup);
        }

        if (is_dir($target) && !@rename($target, $backup)) {
            @unlink($archive);
            remove_dir($extractdirectory);
            throw new coding_exception('Unable to replace the current validation engine.');
        }

        if (!@rename($sourceroot, $target)) {
            if (is_dir($backup)) {
                @rename($backup, $target);
            }
            @unlink($archive);
            remove_dir($extractdirectory);
            throw new coding_exception('Unable to activate the downloaded validation engine.');
        }

        if (is_dir($backup)) {
            remove_dir($backup);
        }
        if (is_dir($extractdirectory)) {
            remove_dir($extractdirectory);
        }
        @unlink($archive);

        $this->save_version((string)$release['tag_name']);

        return [
            'version' => (string)$release['tag_name'],
        ];
    }

    public function validate(array $plugin): array {
        $engineroot = $this->get_engine_root();
        if ($engineroot === null) {
            throw new coding_exception(get_string('enginenotinstalled', 'local_pluginvalidator'));
        }

        $autoload = $engineroot . '/autoload.php';
        if (!is_readable($autoload)) {
            throw new coding_exception('Unable to load the moodle-plugin-validate autoloader.');
        }

        require_once($autoload);

        try {
            $validator = new Validator();

            if (method_exists($validator, 'validateResult')) {
                $result = $validator->validateResult($plugin['rootdir'])->toArray();
            } else {
                $result = $this->normalise_legacy_checks(
                    $plugin['component'],
                    $validator->validateDetailed($plugin['rootdir'])
                );
            }

            $result['engine'] = $this->get_id();
            $result['format'] = $this->get_result_format();
            return $result;
        } catch (\Throwable $e) {
            return $this->runtime_error($plugin['component'], $e->getMessage());
        }
    }

    /**
     * Returns the active validation library root.
     *
     * @return string|null
     */
    private function get_engine_root(): ?string {
        $downloaded = $this->get_engine_directory();
        if ($this->is_engine_root($downloaded)) {
            return $downloaded;
        }

        $bundled = dirname(__DIR__, 2) . '/tools/' . self::ENGINE_DIRECTORY;
        if ($this->is_engine_root($bundled)) {
            return $bundled;
        }

        return null;
    }

    /**
     * Queries the latest release metadata.
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
            throw new coding_exception('Unable to query moodle-plugin-validate releases: ' . $curl->error);
        }

        $release = json_decode($response, true);
        if (!is_array($release)) {
            throw new coding_exception('Invalid moodle-plugin-validate release response.');
        }

        return $release;
    }

    /**
     * Finds the validator project root inside an extracted GitHub archive.
     *
     * @param string $directory Extracted archive directory.
     * @return string|null
     */
    private function find_extracted_root(string $directory): ?string {
        if ($this->is_engine_root($directory)) {
            return $directory;
        }

        $entries = glob($directory . '/*', GLOB_ONLYDIR);
        if ($entries === false) {
            return null;
        }

        foreach ($entries as $entry) {
            if ($this->is_engine_root($entry)) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Checks whether a directory is a loadable library root.
     *
     * @param string $directory Candidate engine root.
     * @return bool
     */
    private function is_engine_root(string $directory): bool {
        return is_readable($directory . '/autoload.php')
            && is_readable($directory . '/src/Validator.php');
    }

    /**
     * Returns downloaded engine directory in Moodle data.
     *
     * @return string
     */
    private function get_engine_directory(): string {
        global $CFG;
        return $CFG->dataroot . '/local_pluginvalidator/tools/' . self::ENGINE_DIRECTORY;
    }

    /**
     * Persists the installed engine version.
     *
     * @param string $version Version.
     */
    private function save_version(string $version): void {
        set_config('engine_' . $this->get_id() . '_version', $version, 'local_pluginvalidator');
        set_config('engine_' . $this->get_id() . '_updated', time(), 'local_pluginvalidator');

        // Keep the old key while older local_pluginvalidator versions may still read it.
        set_config('engineversion', $version, 'local_pluginvalidator');
    }

    /**
     * Converts old Check[] responses to the common structured schema.
     *
     * @param string $component Plugin component.
     * @param array $checks Validator checks.
     * @return array
     */
    private function normalise_legacy_checks(string $component, array $checks): array {
        $summary = [
            'total' => 0,
            'ok' => 0,
            'warnings' => 0,
            'errors' => 0,
        ];
        $groups = [];

        foreach ($checks as $check) {
            $rule = $check->rule;
            if ($rule === '' && str_starts_with($check->key, 'xmldb:')) {
                $rule = 'installxml';
            } else if ($rule === '') {
                $rule = 'general';
            }

            if ($check->isError()) {
                $status = 'error';
            } else if ($check->isWarning()) {
                $status = 'warning';
            } else {
                $status = 'ok';
            }

            if (!isset($groups[$rule])) {
                $groups[$rule] = [
                    'rule' => $rule,
                    'status' => 'ok',
                    'summary' => [
                        'total' => 0,
                        'ok' => 0,
                        'warnings' => 0,
                        'errors' => 0,
                    ],
                    'checks' => [],
                ];
            }

            $summary['total']++;
            $groups[$rule]['summary']['total']++;
            $groups[$rule]['checks'][] = [
                'status' => $status,
                'rule' => $rule,
                'file' => $check->file,
                'line' => $check->line,
                'key' => $check->key,
                'target' => $check->target(),
                'message' => $check->message,
                'languageString' => $check->languageString,
            ];

            if ($status === 'error') {
                $summary['errors']++;
                $groups[$rule]['summary']['errors']++;
            } else if ($status === 'warning') {
                $summary['warnings']++;
                $groups[$rule]['summary']['warnings']++;
            } else {
                $summary['ok']++;
                $groups[$rule]['summary']['ok']++;
            }
        }

        foreach ($groups as &$group) {
            if ($group['summary']['errors'] > 0) {
                $group['status'] = 'error';
            } else if ($group['summary']['warnings'] > 0) {
                $group['status'] = 'warning';
            }
        }
        unset($group);

        $status = 'ok';
        if ($summary['errors'] > 0) {
            $status = 'error';
        } else if ($summary['warnings'] > 0) {
            $status = 'warning';
        }

        return [
            'schema' => 1,
            'component' => $component,
            'success' => $summary['errors'] === 0,
            'status' => $status,
            'summary' => $summary,
            'groups' => array_values($groups),
        ];
    }

    /**
     * Builds a runtime failure using the common structured schema.
     *
     * @param string $component Plugin component.
     * @param string $message Error message.
     * @return array
     */
    private function runtime_error(string $component, string $message): array {
        return [
            'schema' => 1,
            'component' => $component,
            'engine' => $this->get_id(),
            'format' => $this->get_result_format(),
            'success' => false,
            'status' => 'error',
            'summary' => [
                'total' => 0,
                'ok' => 0,
                'warnings' => 0,
                'errors' => 1,
            ],
            'groups' => [],
            'runtimeError' => [
                'message' => $message,
            ],
        ];
    }
}
