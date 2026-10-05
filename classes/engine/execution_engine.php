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
use Throwable;

/**
 * Runtime validation engine for installed plugins.
 *
 * This validator exercises code that static validation cannot cover safely:
 * activity backup execution, external function contracts and activity forms.
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class execution_engine implements validation_engine_interface {
    /**
     * Returns the stable engine identifier.
     *
     * @return string
     */
    public function get_id(): string {
        return 'execution';
    }

    /**
     * Returns the engine name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('engine_execution', 'local_pluginvalidator');
    }

    /**
     * Returns the engine description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('engine_execution_desc', 'local_pluginvalidator');
    }

    /**
     * Returns the native result format.
     *
     * @return string
     */
    public function get_result_format(): string {
        return validation_engine_interface::RESULT_STRUCTURED;
    }

    /**
     * Returns engine availability information.
     *
     * @return array
     */
    public function get_status(): array {
        return [
            'available' => true,
            'version' => get_string('enginebuiltin', 'local_pluginvalidator'),
            'source' => '',
            'path' => __FILE__,
            'updatable' => false,
        ];
    }

    /**
     * Built-in engines do not require installation.
     *
     * @return array
     */
    public function install_latest(): array {
        return [
            'version' => get_string('enginebuiltin', 'local_pluginvalidator'),
        ];
    }

    /**
     * Runs runtime checks against one installed plugin.
     *
     * @param array $plugin Plugin information.
     * @return array
     */
    public function validate(array $plugin): array {
        $groups = [];
        $rootdir = rtrim((string)$plugin['rootdir'], DIRECTORY_SEPARATOR);

        if (is_dir($rootdir . '/backup/moodle2')) {
            $this->validate_backup($plugin, $groups);
        }

        if (is_file($rootdir . '/db/services.php')) {
            $this->validate_services($plugin, $groups);
        }

        if (($plugin['type'] ?? '') === 'mod') {
            $this->validate_mod_form($plugin, $groups);
        }

        if ($groups === []) {
            $this->add_check(
                $groups,
                'execution',
                'ok',
                get_string('executionnotapplicable', 'local_pluginvalidator')
            );
        }

        return $this->build_result((string)$plugin['component'], $groups);
    }

    /**
     * Executes an activity backup when the plugin provides backup/moodle2.
     *
     * @param array $plugin Plugin information.
     * @param array $groups Result groups.
     */
    private function validate_backup(array $plugin, array &$groups): void {
        global $CFG, $DB, $USER;

        $rule = 'execution:backup';
        $file = 'backup/moodle2';

        if (($plugin['type'] ?? '') !== 'mod') {
            $this->add_check(
                $groups,
                $rule,
                'warning',
                get_string('executionbackupunsupported', 'local_pluginvalidator'),
                $file
            );
            return;
        }

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

        $records = $DB->get_records_sql(
            "SELECT cm.id
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE m.name = :modname
                AND cm.instance > 0
                AND cm.deletioninprogress = 0
           ORDER BY cm.id ASC",
            ['modname' => $plugin['name']],
            0,
            1
        );
        $record = reset($records);

        if (!$record) {
            $this->add_check(
                $groups,
                $rule,
                'warning',
                get_string('executionbackupnoinstance', 'local_pluginvalidator'),
                $file
            );
            return;
        }

        $backupcontroller = null;
        $backupbasepath = null;

        try {
            $backupcontroller = new \backup_controller(
                \backup::TYPE_1ACTIVITY,
                (int)$record->id,
                \backup::FORMAT_MOODLE,
                \backup::INTERACTIVE_NO,
                \backup::MODE_IMPORT,
                $USER->id
            );

            $backupbasepath = $backupcontroller->get_plan()->get_basepath();
            $backupcontroller->execute_plan();

            $this->add_check(
                $groups,
                $rule,
                'ok',
                get_string('executionbackupok', 'local_pluginvalidator', (int)$record->id),
                $file,
                'cmid=' . (int)$record->id
            );
        } catch (Throwable $e) {
            $this->add_check(
                $groups,
                $rule,
                'error',
                get_string('executionbackuperror', 'local_pluginvalidator', $e->getMessage()),
                $file,
                'cmid=' . (int)$record->id
            );
        } finally {
            if ($backupcontroller !== null) {
                try {
                    $backupcontroller->destroy();
                } catch (Throwable $ignored) {
                    // The validation result above is more useful than a cleanup-only failure.
                }
            }

            if ($backupbasepath && is_dir($backupbasepath)) {
                fulldelete($backupbasepath);
            }
        }
    }

    /**
     * Loads and validates all external function definitions from db/services.php.
     *
     * The function implementations are not called with invented arguments. Doing so
     * could execute writes, send messages or invoke external systems. Moodle's own
     * external_function_info() still executes the parameter and return-description
     * methods and validates that the callable implementation can be resolved.
     *
     * @param array $plugin Plugin information.
     * @param array $groups Result groups.
     */
    private function validate_services(array $plugin, array &$groups): void {
        global $CFG, $DB;

        require_once($CFG->libdir . '/externallib.php');

        $rule = 'execution:services';
        $servicesfile = rtrim((string)$plugin['rootdir'], DIRECTORY_SEPARATOR) . '/db/services.php';
        $functions = [];
        $services = [];

        try {
            include($servicesfile);
        } catch (Throwable $e) {
            $this->add_check(
                $groups,
                $rule,
                'error',
                get_string('executionservicesloaderror', 'local_pluginvalidator', $e->getMessage()),
                'db/services.php'
            );
            return;
        }

        if (!is_array($functions) || $functions === []) {
            $this->add_check(
                $groups,
                $rule,
                'error',
                get_string('executionservicesempty', 'local_pluginvalidator'),
                'db/services.php'
            );
            return;
        }

        foreach ($functions as $name => $definition) {
            $name = (string)$name;
            if (!is_array($definition)) {
                $this->add_check(
                    $groups,
                    $rule,
                    'error',
                    get_string('executionserviceinvalid', 'local_pluginvalidator', $name),
                    'db/services.php',
                    $name
                );
                continue;
            }

            $function = (object)$definition;
            $function->name = $name;
            $function->component = (string)$plugin['component'];

            try {
                $info = \external_api::external_function_info($function);

                $registered = $DB->get_record('external_functions', ['name' => $name], '*', IGNORE_MISSING);
                if (!$registered) {
                    throw new coding_exception(
                        get_string('executionserviceunregistered', 'local_pluginvalidator', $name)
                    );
                }
                if ((string)$registered->component !== (string)$plugin['component']) {
                    throw new coding_exception(
                        get_string('executionservicewrongcomponent', 'local_pluginvalidator', $name)
                    );
                }

                \external_api::external_function_info($registered);

                $this->add_check(
                    $groups,
                    $rule,
                    'ok',
                    get_string('executionserviceok', 'local_pluginvalidator', $name),
                    'db/services.php',
                    $info->classname . '::' . $info->methodname
                );
            } catch (Throwable $e) {
                $data = (object)[
                    'name' => $name,
                    'message' => $e->getMessage(),
                ];
                $this->add_check(
                    $groups,
                    $rule,
                    'error',
                    get_string('executionserviceerror', 'local_pluginvalidator', $data),
                    'db/services.php',
                    $name
                );
            }
        }
    }

    /**
     * Instantiates an activity mod_form using the same core preparation used by modedit.php.
     *
     * @param array $plugin Plugin information.
     * @param array $groups Result groups.
     */
    private function validate_mod_form(array $plugin, array &$groups): void {
        global $CFG, $COURSE, $DB;

        $rule = 'execution:mod_form';
        $formfile = rtrim((string)$plugin['rootdir'], DIRECTORY_SEPARATOR) . '/mod_form.php';

        if (!is_file($formfile)) {
            $this->add_check(
                $groups,
                $rule,
                'error',
                get_string('executionmodformmissing', 'local_pluginvalidator'),
                'mod_form.php'
            );
            return;
        }

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/course/moodleform_mod.php');

        $courses = $DB->get_records_sql(
            'SELECT * FROM {course} WHERE id <> :siteid ORDER BY id ASC',
            ['siteid' => SITEID],
            0,
            1
        );
        $course = reset($courses);
        if (!$course) {
            $course = $DB->get_record('course', ['id' => SITEID], '*', MUST_EXIST);
        }

        $oldcourse = $COURSE;

        try {
            $COURSE = $course;
            [, , $section, $cm, $data] = prepare_new_moduleinfo_data(
                $course,
                (string)$plugin['name'],
                0
            );

            require_once($formfile);

            $classname = 'mod_' . $plugin['name'] . '_mod_form';
            if (!class_exists($classname)) {
                throw new coding_exception(
                    get_string('executionmodformclassmissing', 'local_pluginvalidator', $classname)
                );
            }
            if (!is_subclass_of($classname, 'moodleform_mod')) {
                throw new coding_exception(
                    get_string('executionmodformclassinvalid', 'local_pluginvalidator', $classname)
                );
            }

            $mform = new $classname($data, $section->section, $cm, $course);
            $mform->set_data($data);

            $this->add_check(
                $groups,
                $rule,
                'ok',
                get_string('executionmodformok', 'local_pluginvalidator'),
                'mod_form.php',
                $classname
            );
        } catch (Throwable $e) {
            $this->add_check(
                $groups,
                $rule,
                'error',
                get_string('executionmodformerror', 'local_pluginvalidator', $e->getMessage()),
                'mod_form.php'
            );
        } finally {
            $COURSE = $oldcourse;
        }
    }

    /**
     * Adds one check to a structured result group.
     *
     * @param array $groups Result groups.
     * @param string $rule Rule identifier.
     * @param string $status Check status.
     * @param string $message Check message.
     * @param string $file Relative file path.
     * @param string $target Optional target.
     */
    private function add_check(
        array &$groups,
        string $rule,
        string $status,
        string $message,
        string $file = '',
        string $target = ''
    ): void {
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

        $groups[$rule]['summary']['total']++;
        if ($status === 'error') {
            $groups[$rule]['summary']['errors']++;
            $groups[$rule]['status'] = 'error';
        } else if ($status === 'warning') {
            $groups[$rule]['summary']['warnings']++;
            if ($groups[$rule]['status'] !== 'error') {
                $groups[$rule]['status'] = 'warning';
            }
        } else {
            $groups[$rule]['summary']['ok']++;
        }

        $groups[$rule]['checks'][] = [
            'status' => $status,
            'rule' => $rule,
            'file' => $file,
            'line' => 0,
            'key' => $rule,
            'target' => $target,
            'message' => $message,
        ];
    }

    /**
     * Builds the common structured result schema.
     *
     * @param string $component Plugin component.
     * @param array $groups Result groups.
     * @return array
     */
    private function build_result(string $component, array $groups): array {
        $summary = [
            'total' => 0,
            'ok' => 0,
            'warnings' => 0,
            'errors' => 0,
        ];

        foreach ($groups as $group) {
            $summary['total'] += $group['summary']['total'];
            $summary['ok'] += $group['summary']['ok'];
            $summary['warnings'] += $group['summary']['warnings'];
            $summary['errors'] += $group['summary']['errors'];
        }

        $status = 'ok';
        if ($summary['errors'] > 0) {
            $status = 'error';
        } else if ($summary['warnings'] > 0) {
            $status = 'warning';
        }

        return [
            'schema' => 1,
            'component' => $component,
            'engine' => $this->get_id(),
            'format' => $this->get_result_format(),
            'success' => $summary['errors'] === 0,
            'status' => $status,
            'summary' => $summary,
            'groups' => array_values($groups),
        ];
    }
}
