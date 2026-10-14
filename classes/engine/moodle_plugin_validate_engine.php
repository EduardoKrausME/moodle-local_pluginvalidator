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

use EduardoKraus\MoodleStringValidate\Validator;
use Throwable;

/**
 * EduardoKrausME/moodle-plugin-validate engine.
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_plugin_validate_engine implements validation_engine_interface {
    /** Reviewed library bundled with this plugin, never loaded from moodledata. */
    private const ENGINE_DIRECTORY = 'moodle-plugin-validate';

    /** Fixed version of the reviewed source committed to the plugin. */
    private const BUNDLED_VERSION = '1.2.6';

    /**
     * Returns the stable engine ID.
     *
     * @return string Engine identifier.
     */
    public function get_id(): string {
        return 'moodle_plugin_validate';
    }

    /**
     * Returns the localised engine name.
     *
     * @return string Engine name.
     */
    public function get_name(): string {
        return get_string('engine_moodlepluginvalidate', 'local_pluginvalidator');
    }

    /**
     * Returns the localised engine description.
     *
     * @return string Engine description.
     */
    public function get_description(): string {
        return get_string('engine_moodlepluginvalidate_desc', 'local_pluginvalidator');
    }

    /**
     * Returns the result format.
     *
     * @return string Structured result format.
     */
    public function get_result_format(): string {
        return validation_engine_interface::RESULT_STRUCTURED;
    }

    /**
     * This bundled dependency requires PHP 8.1. The independent runtime engine
     * remains available to Moodle installations running older versions of PHP.
     *
     * @return array Engine availability and version.
     */
    public function get_status(): array {
        $root = $this->get_engine_root();
        return [
            'available' => PHP_VERSION_ID >= 80100 && $this->is_engine_root($root),
            'version' => self::BUNDLED_VERSION,
            'source' => get_string('enginesourcebundled', 'local_pluginvalidator'),
            'path' => $root,
            'updatable' => false,
        ];
    }

    /**
     * No engine installation from the network is permitted.
     *
     * @return array
     * @throws \moodle_exception
     */
    public function install_latest(): array {
        throw new \moodle_exception('enginebundlednoupdate', 'local_pluginvalidator');
    }

    /**
     * Runs only the reviewed library supplied in the installed plugin package.
     *
     * @param array $plugin Plugin under validation.
     * @return array Structured validation result.
     */
    public function validate(array $plugin): array {
        if (PHP_VERSION_ID < 80100) {
            throw new \moodle_exception('enginephpversion', 'local_pluginvalidator');
        }

        $engineroot = $this->get_engine_root();
        if (!$this->is_engine_root($engineroot)) {
            throw new \moodle_exception('enginenotinstalled', 'local_pluginvalidator');
        }

        // Deliberately ignore any old executables under $CFG->dataroot.
        require_once($engineroot . '/autoload.php');

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
        } catch (Throwable $e) {
            return $this->runtime_error($plugin['component'], $e->getMessage());
        }
    }

    /**
     * Locates the bundled validator without consulting Moodle data files.
     *
     * @return string Absolute path to the library.
     */
    private function get_engine_root(): string {
        return dirname(__DIR__, 2) . '/vendor/' . self::ENGINE_DIRECTORY;
    }

    /**
     * Checks whether the bundled library contains its required source files.
     *
     * @param string $directory Library source directory.
     * @return bool Whether required library files are present.
     */
    private function is_engine_root(string $directory): bool {
        return is_readable($directory . '/autoload.php')
            && is_readable($directory . '/src/Validator.php');
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
            if ($rule === '' && strpos($check->key, 'xmldb:') === 0) {
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
