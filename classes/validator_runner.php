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

namespace local_pluginvalidator;

use coding_exception;
use EduardoKraus\MoodleStringValidate\Validator;

/**
 * Runs validation against an installed plugin.
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validator_runner {
    /** @var engine_manager */
    private $engine;

    /**
     * Constructor.
     *
     * @param engine_manager $engine Engine manager.
     */
    public function __construct(engine_manager $engine) {
        $this->engine = $engine;
    }

    /**
     * Runs EduardoKrausME/moodle-plugin-validate and returns structured data.
     *
     * No CLI process is involved. New validator versions expose validateResult()
     * directly; the fallback keeps compatibility with older installed engines.
     *
     * @param array $plugin Plugin information.
     * @return array
     * @throws coding_exception
     */
    public function validate(array $plugin): array {
        $enginepath = $this->engine->get_engine_path();
        if ($enginepath === null) {
            throw new coding_exception(get_string('enginenotinstalled', 'local_pluginvalidator'));
        }

        $engineroot = dirname($enginepath, 2);
        $autoload = $engineroot . '/autoload.php';
        if (!is_readable($autoload)) {
            throw new coding_exception('Unable to load the moodle-plugin-validate autoloader.');
        }

        require_once($autoload);

        try {
            $validator = new Validator();

            if (method_exists($validator, 'validateResult')) {
                $validationresult = $validator->validateResult($plugin['rootdir']);
                $result = $validationresult->toArray();
            } else {
                $checks = $validator->validateDetailed($plugin['rootdir']);
                $result = $this->normalise_legacy_checks($plugin['component'], $checks);
            }

            $result['exitcode'] = $result['success'] ? 0 : 1;
            return $result;
        } catch (\Throwable $e) {
            return [
                'schema' => 1,
                'component' => $plugin['component'],
                'success' => false,
                'status' => 'error',
                'summary' => [
                    'total' => 0,
                    'ok' => 0,
                    'warnings' => 0,
                    'errors' => 1,
                ],
                'groups' => [],
                'runtimeerror' => true,
                'errormessage' => $e->getMessage(),
                'exitcode' => 2,
            ];
        }
    }

    /**
     * Converts old Check[] responses to the structured schema used by current engines.
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
}
