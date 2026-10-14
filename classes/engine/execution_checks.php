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
use local_pluginvalidator\moodle_class_loader;
use ReflectionFunction;
use ReflectionMethod;
use Throwable;

/**
 * Additional runtime checks used by the execution validation engine.
 *
 * Checks execute read-only/idempotent code whenever Moodle provides enough real
 * context. Potentially destructive operations are limited to contract validation.
 * Temporary records created by round-trip checks are removed in finally blocks.
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class execution_checks {
    /** Check was really invoked. */
    public const STATE_EXECUTED = 'executed';

    /** Structure/signature was validated, but execution was intentionally skipped. */
    public const STATE_CONTRACT = 'contract';

    /** The check does not apply to this plugin or no safe runtime context exists. */
    public const STATE_NOT_APPLICABLE = 'not_applicable';

    /**
     * Runs the expanded runtime validation set.
     *
     * @param array $plugin Plugin information.
     * @return array<string, array>
     */
    public function validate(array $plugin): array {
        $groups = [];

        $this->validate_lib_callbacks($plugin, $groups);
        $this->validate_backup_restore($plugin, $groups);
        $this->validate_scheduled_tasks($plugin, $groups);
        $this->validate_adhoc_tasks($plugin, $groups);
        $this->validate_event_observers($plugin, $groups);
        $this->validate_hooks($plugin, $groups);
        $this->validate_admin_settings($plugin, $groups);
        $this->validate_privacy($plugin, $groups);
        $this->validate_block($plugin, $groups);
        $this->validate_filter($plugin, $groups);
        $this->validate_grade($plugin, $groups);
        $this->validate_completion($plugin, $groups);
        $this->validate_renderer($plugin, $groups);
        $this->validate_course_format($plugin, $groups);
        $this->validate_auth($plugin, $groups);
        $this->validate_enrol($plugin, $groups);
        $this->validate_repository($plugin, $groups);
        $this->validate_qtype($plugin, $groups);

        return $groups;
    }

    /**
     * Loads lib.php and executes safe standard callbacks with real Moodle objects.
     *
     * @param array $plugin Plugin information.
     * @param array $groups Result groups.
     */
    private function validate_lib_callbacks(array $plugin, array &$groups): void {
        $root = $this->root($plugin);
        $file = $root . '/lib.php';
        $rule = 'execution:lib_callbacks';
        if (!is_file($file)) {
            return;
        }

        try {
            require_once($file);
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                get_string('runtimecheck01', 'local_pluginvalidator', (object)['p1' => $e->getMessage()]), 'lib.php');
            return;
        }

        $prefixes = array_unique(array_filter([
            (string)($plugin['name'] ?? ''),
            (string)($plugin['component'] ?? ''),
        ]));
        $module = $this->get_module_runtime($plugin);
        $callbacks = [];
        foreach ($prefixes as $prefix) {
            $callbacks += [
                $prefix . '_supports' => 'safe',
                $prefix . '_get_coursemodule_info' => 'safe',
                $prefix . '_get_file_areas' => 'safe',
                $prefix . '_pluginfile' => 'contract',
                $prefix . '_extend_navigation' => 'contract',
                $prefix . '_extend_settings_navigation' => 'contract',
                $prefix . '_add_instance' => 'contract',
                $prefix . '_update_instance' => 'contract',
                $prefix . '_delete_instance' => 'contract',
                $prefix . '_reset_userdata' => 'contract',
            ];
        }

        $found = false;
        foreach ($callbacks as $function => $mode) {
            if (!function_exists($function)) {
                continue;
            }
            $found = true;

            try {
                $reflection = new ReflectionFunction($function);
                if (!$reflection->isUserDefined()) {
                    throw new coding_exception(get_string('runtimeexception01', 'local_pluginvalidator'));
                }

                if ($mode === 'contract') {
                    $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                        get_string('runtimecheck02', 'local_pluginvalidator', (object)['p1' => $function]),
                        'lib.php', $function);
                    continue;
                }

                if (substr($function, -strlen('_supports')) === '_supports') {
                    $features = array_filter([
                        defined('FEATURE_COMPLETION_HAS_RULES') ? FEATURE_COMPLETION_HAS_RULES : null,
                        defined('FEATURE_GRADE_HAS_GRADE') ? FEATURE_GRADE_HAS_GRADE : null,
                        defined('FEATURE_MOD_INTRO') ? FEATURE_MOD_INTRO : null,
                        defined('FEATURE_BACKUP_MOODLE2') ? FEATURE_BACKUP_MOODLE2 : null,
                    ], static fn($value) => $value !== null);
                    foreach (array_unique($features) as $feature) {
                        $function($feature);
                    }
                    $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                        get_string('runtimecheck03', 'local_pluginvalidator', (object)['p1' => $function]), 'lib.php', $function);
                    continue;
                }

                if ($module === null) {
                    $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                        get_string('runtimecheck04', 'local_pluginvalidator', (object)['p1' => $function]),
                        'lib.php', $function);
                    continue;
                }

                if (substr($function, -strlen('_get_coursemodule_info')) === '_get_coursemodule_info') {
                    $function($module['cm']);
                    $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                        get_string('runtimecheck05', 'local_pluginvalidator', (object)['p1' => $function, 'p2' => $module['cm']->id]), 'lib.php', $function);
                    continue;
                }

                if (substr($function, -strlen('_get_file_areas')) === '_get_file_areas') {
                    $areas = $function($module['course'], $module['cm'], $module['context']);
                    if (!is_array($areas)) {
                        throw new coding_exception(get_string('runtimeexception02', 'local_pluginvalidator'));
                    }
                    $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                        get_string('runtimecheck06', 'local_pluginvalidator', (object)['p1' => $function, 'p2' => count($areas)]), 'lib.php', $function);
                    $this->validate_file_storage($plugin, $module, $areas, $groups);
                }
            } catch (Throwable $e) {
                $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                    get_string('runtimecheck07', 'local_pluginvalidator', (object)['p1' => $function, 'p2' => $e->getMessage()]), 'lib.php', $function);
            }
        }

        if (!$found) {
            $this->add_check($groups, $rule, 'ok', self::STATE_NOT_APPLICABLE,
                get_string('runtimecheck08', 'local_pluginvalidator'),
                'lib.php');
        }
    }

    /**
     * Writes, reads and removes a tiny File API record in one declared file area.
     *
     * @param array $plugin Plugin information.
     * @param array $module Module runtime context.
     * @param array $areas Declared file areas.
     * @param array $groups Result groups.
     */
    private function validate_file_storage(array $plugin, array $module, array $areas, array &$groups): void {
        if ($areas === []) {
            return;
        }
        $rule = 'execution:file_api';
        $filearea = (string)array_key_first($areas);
        if ($filearea === '') {
            return;
        }

        $storedfile = null;
        try {
            $fs = get_file_storage();
            $filename = 'pluginvalidator-' . bin2hex(random_bytes(6)) . '.txt';
            $record = [
                'contextid' => $module['context']->id,
                'component' => (string)$plugin['component'],
                'filearea' => $filearea,
                'itemid' => 0,
                'filepath' => '/',
                'filename' => $filename,
            ];
            $storedfile = $fs->create_file_from_string($record, 'pluginvalidator');
            $loaded = $fs->get_file(
                $record['contextid'], $record['component'], $record['filearea'], 0, '/', $filename
            );
            if (!$loaded || $loaded->get_content() !== 'pluginvalidator') {
                throw new coding_exception(get_string('runtimeexception03', 'local_pluginvalidator'));
            }
            $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                get_string('runtimecheck09', 'local_pluginvalidator', (object)['p1' => $filearea]), 'lib.php', $filearea);
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                get_string('runtimecheck10', 'local_pluginvalidator', (object)['p1' => $e->getMessage()]), 'lib.php', $filearea);
        } finally {
            if ($storedfile) {
                try {
                    $storedfile->delete();
                } catch (Throwable $ignored) {
                    // Cleanup failure must not hide the primary validation result.
                    unset($ignored);
                }
            }
        }
    }

    /**
     * Executes a real backup + restore round-trip and removes the restored module.
     *
     * @param array $plugin Plugin information.
     * @param array $groups Result groups.
     */
    private function validate_backup_restore(array $plugin, array &$groups): void {
        global $CFG, $USER;

        $root = $this->root($plugin);
        if (!is_dir($root . '/backup/moodle2')) {
            return;
        }

        $rule = 'execution:backup_restore';
        if (($plugin['type'] ?? '') !== 'mod') {
            $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                get_string('runtimecheck11', 'local_pluginvalidator'),
                'backup/moodle2');
            return;
        }

        $module = $this->get_module_runtime($plugin);
        if ($module === null) {
            $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                get_string('runtimecheck12', 'local_pluginvalidator'),
                'backup/moodle2');
            return;
        }

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $backupcontroller = null;
        $restorecontroller = null;
        $backupbasepath = null;
        $newcmid = 0;

        try {
            $backupcontroller = new \backup_controller(
                \backup::TYPE_1ACTIVITY,
                (int)$module['cm']->id,
                \backup::FORMAT_MOODLE,
                \backup::INTERACTIVE_NO,
                \backup::MODE_GENERAL,
                $USER->id
            );
            $this->configure_activity_backup($backupcontroller);
            $backupid = $backupcontroller->get_backupid();
            $backupbasepath = $backupcontroller->get_plan()->get_basepath();
            $backupcontroller->execute_plan();
            $results = $backupcontroller->get_results();
            $backupfile = $results['backup_destination'] ?? null;
            if (!$backupfile) {
                throw new coding_exception(get_string('runtimeexception04', 'local_pluginvalidator'));
            }

            $backupcontroller->destroy();
            $backupcontroller = null;

            // Restore controller expects an extracted Moodle backup in the temp directory.
            // Depending on the backup execution path, execute_plan() may leave only the...
            // generated .mbz available, so mirror Moodle core tests and extract it first.
            if (!file_exists($backupbasepath . '/moodle_backup.xml')) {
                $backupfile->extract_to_pathname(
                    get_file_packer('application/vnd.moodle.backup'),
                    $backupbasepath
                );
            }

            $restorecontroller = new \restore_controller(
                $backupid,
                (int)$module['course']->id,
                \backup::INTERACTIVE_NO,
                \backup::MODE_GENERAL,
                $USER->id,
                \backup::TARGET_CURRENT_ADDING
            );

            if (!$restorecontroller->execute_precheck()) {
                throw new coding_exception(get_string('runtimeexception05', 'local_pluginvalidator'));
            }
            $restorecontroller->execute_plan();
            $newcmid = $this->find_restored_module_id($restorecontroller, (int)$module['context']->id);
            if (!$newcmid) {
                throw new coding_exception(get_string('runtimeexception06', 'local_pluginvalidator'));
            }

            $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                get_string('runtimecheck13', 'local_pluginvalidator', (object)['p1' => $module['cm']->id, 'p2' => $newcmid]),
                'backup/moodle2', 'cmid=' . (int)$module['cm']->id);
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                get_string('runtimecheck14', 'local_pluginvalidator', (object)['p1' => $this->format_exception($e)]), 'backup/moodle2',
                'cmid=' . (int)$module['cm']->id);
        } finally {
            if ($restorecontroller) {
                try {
                    if (!$newcmid) {
                        $newcmid = $this->find_restored_module_id($restorecontroller, (int)$module['context']->id);
                    }
                    $restorecontroller->destroy();
                } catch (Throwable $ignored) {
                    // Continue cleanup.
                    unset($ignored);
                }
            }
            if ($newcmid) {
                try {
                    if (class_exists('\\core_courseformat\\formatactions')) {
                        \core_courseformat\formatactions::cm((int)$module['course']->id)->delete($newcmid);
                    } else {
                        course_delete_module($newcmid);
                    }
                } catch (Throwable $ignored) {
                    // The failed cleanup should not replace the actual validation result.
                    unset($ignored);
                }
            }
            if ($backupcontroller) {
                try {
                    $backupcontroller->destroy();
                } catch (Throwable $ignored) {
                    // Continue cleanup.
                    unset($ignored);
                }
            }
            if ($backupbasepath && is_dir($backupbasepath)) {
                fulldelete($backupbasepath);
            }
        }
    }

    /**

     * Validates scheduled task declarations without calling execute().

     */
    private function validate_scheduled_tasks(array $plugin, array &$groups): void {
        $file = $this->root($plugin) . '/db/tasks.php';
        if (!is_file($file)) {
            return;
        }
        $rule = 'execution:scheduled_tasks';
        try {
            $tasks = $this->load_array_file($file, 'tasks');
            if ($tasks === []) {
                throw new coding_exception(get_string('runtimeexception07', 'local_pluginvalidator'));
            }
            foreach ($tasks as $task) {
                $classname = (string)($task['classname'] ?? '');
                if ($classname === '' || !moodle_class_loader::load_symbol($classname)) {
                    throw new coding_exception(get_string('runtimeexception08', 'local_pluginvalidator', (object)['p1' => $classname]));
                }
                if (!is_subclass_of($classname, \core\task\scheduled_task::class)) {
                    throw new coding_exception(get_string('runtimeexception09', 'local_pluginvalidator', (object)['p1' => $classname]));
                }
                $instance = new $classname();
                $name = $instance->get_name();
                if (!is_string($name) || $name === '') {
                    throw new coding_exception(get_string('runtimeexception10', 'local_pluginvalidator', (object)['p1' => $classname]));
                }
                $method = new ReflectionMethod($classname, 'execute');
                if (!$method->isPublic() || $method->getNumberOfRequiredParameters() !== 0) {
                    throw new coding_exception(get_string('runtimeexception11', 'local_pluginvalidator', (object)['p1' => $classname]));
                }
                $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                    get_string('runtimecheck15', 'local_pluginvalidator', (object)['p1' => $classname]),
                    'db/tasks.php', $classname);
            }
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                get_string('runtimecheck16', 'local_pluginvalidator', (object)['p1' => $e->getMessage()]), 'db/tasks.php');
        }
    }

    /**

     * Discovers adhoc tasks and validates custom data serialization without executing them.

     */
    private function validate_adhoc_tasks(array $plugin, array &$groups): void {
        $classes = $this->discover_component_classes($plugin, 'task');
        $rule = 'execution:adhoc_tasks';
        $found = false;
        foreach ($classes as $classname) {
            try {
                if (!moodle_class_loader::load_symbol($classname)
                        || !is_subclass_of($classname, \core\task\adhoc_task::class)) {
                    continue;
                }
                $found = true;
                $task = new $classname();
                $task->set_custom_data(['pluginvalidator' => true]);
                $data = $task->get_custom_data();
                if (!is_object($data) || empty($data->pluginvalidator)) {
                    throw new coding_exception(get_string('runtimeexception12', 'local_pluginvalidator'));
                }
                $method = new ReflectionMethod($classname, 'execute');
                if (!$method->isPublic() || $method->getNumberOfRequiredParameters() !== 0) {
                    throw new coding_exception(get_string('runtimeexception13', 'local_pluginvalidator'));
                }
                $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                    get_string('runtimecheck17', 'local_pluginvalidator', (object)['p1' => $classname]),
                    $this->class_file($plugin, $classname), $classname);
            } catch (Throwable $e) {
                $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                    get_string('runtimecheck18', 'local_pluginvalidator', (object)['p1' => $classname, 'p2' => $e->getMessage()]),
                    $this->class_file($plugin, $classname), $classname);
            }
        }
        if (!$found && is_dir($this->root($plugin) . '/classes/task')) {
            $this->add_check($groups, $rule, 'ok', self::STATE_NOT_APPLICABLE,
                get_string('runtimecheck19', 'local_pluginvalidator'), 'classes/task');
        }
    }

    /**

     * Validates db/events.php observers and callback signatures.

     */
    private function validate_event_observers(array $plugin, array &$groups): void {
        $file = $this->root($plugin) . '/db/events.php';
        if (!is_file($file)) {
            return;
        }

        $rule = 'execution:events';
        $currenttarget = '';
        $currentdetails = [
            [
                'label' => get_string('runtimeeventdiag01', 'local_pluginvalidator'),
                'value' => (string)$plugin['component'],
            ],
            [
                'label' => get_string('runtimeeventdiag02', 'local_pluginvalidator'),
                'value' => 'db/events.php',
            ],
        ];

        try {
            $observers = $this->load_array_file($file, 'observers');
            if ($observers === []) {
                throw new coding_exception(get_string('runtimeexception14', 'local_pluginvalidator'));
            }

            $position = 0;
            foreach ($observers as $observer) {
                $position++;
                $eventname = ltrim((string)($observer['eventname'] ?? ''), '\\');
                $callback = $observer['callback'] ?? null;
                $currenttarget = $eventname;
                $eventcomponent = '';

                if ($eventname !== '') {
                    $separator = strpos($eventname, '\\');
                    if ($separator !== false) {
                        $eventcomponent = substr($eventname, 0, $separator);
                    }
                }

                $isexternal = $eventcomponent !== ''
                    && $eventcomponent !== (string)$plugin['component'];

                $eventresolution = [];
                $eventclassexists = $eventname !== ''
                    && moodle_class_loader::load_symbol($eventname, $eventresolution);
                $componentdir = (string)($eventresolution['componentdir'] ?? '');
                $eventautoloaded = !empty($eventresolution['autoloaded']);
                $eventclassfile = (string)($eventresolution['file'] ?? '');
                $eventclassfileexists = !empty($eventresolution['fileexists']);
                $eventfallbackloaded = !empty($eventresolution['directloaded']);

                $eventisvalid = $eventclassexists
                    && is_subclass_of($eventname, \core\event\base::class);

                $yes = get_string('runtimestate01', 'local_pluginvalidator');
                $no = get_string('runtimestate02', 'local_pluginvalidator');
                $unknown = get_string('runtimestate03', 'local_pluginvalidator');
                $notchecked = get_string('runtimestate04', 'local_pluginvalidator');
                $notattempted = get_string('runtimestate05', 'local_pluginvalidator');
                $unknownvalue = get_string('runtimestate06', 'local_pluginvalidator');
                $emptyvalue = get_string('runtimestate07', 'local_pluginvalidator');
                $notresolved = get_string('runtimestate08', 'local_pluginvalidator');

                $currentdetails = [
                    [
                        'label' => get_string('runtimeeventdiag01', 'local_pluginvalidator'),
                        'value' => (string)$plugin['component'],
                    ],
                    [
                        'label' => get_string('runtimeeventdiag02', 'local_pluginvalidator'),
                        'value' => 'db/events.php',
                    ],
                    [
                        'label' => get_string('runtimeeventdiag03', 'local_pluginvalidator'),
                        'value' => '#' . $position,
                    ],
                    [
                        'label' => get_string('runtimeeventdiag04', 'local_pluginvalidator'),
                        'value' => $eventname !== '' ? $eventname : $emptyvalue,
                    ],
                    [
                        'label' => get_string('runtimeeventdiag05', 'local_pluginvalidator'),
                        'value' => $eventcomponent !== '' ? $eventcomponent : $unknownvalue,
                    ],
                    [
                        'label' => get_string('runtimeeventdiag06', 'local_pluginvalidator'),
                        'value' => $this->callback_name($callback),
                    ],
                    [
                        'label' => get_string('runtimeeventdiag07', 'local_pluginvalidator'),
                        'value' => $isexternal ? $yes : $no,
                    ],
                    [
                        'label' => get_string('runtimeeventdiag08', 'local_pluginvalidator'),
                        'value' => $eventcomponent === ''
                            ? $unknown
                            : ($componentdir !== '' ? $yes : $no),
                    ],
                    [
                        'label' => get_string('runtimeeventdiag09', 'local_pluginvalidator'),
                        'value' => $componentdir !== '' ? $componentdir : $notresolved,
                    ],
                    [
                        'label' => get_string('runtimeeventdiag10', 'local_pluginvalidator'),
                        'value' => $eventautoloaded ? $yes : $no,
                    ],
                    [
                        'label' => get_string('runtimeeventdiag11', 'local_pluginvalidator'),
                        'value' => $eventclassfile !== '' ? $eventclassfile : $notresolved,
                    ],
                    [
                        'label' => get_string('runtimeeventdiag12', 'local_pluginvalidator'),
                        'value' => $eventclassfile !== '' ? ($eventclassfileexists ? $yes : $no) : $notchecked,
                    ],
                    [
                        'label' => get_string('runtimeeventdiag13', 'local_pluginvalidator'),
                        'value' => $eventclassfileexists ? ($eventfallbackloaded ? $yes : $no) : $notattempted,
                    ],
                    [
                        'label' => get_string('runtimeeventdiag14', 'local_pluginvalidator'),
                        'value' => $eventclassexists ? $yes : $no,
                    ],
                    [
                        'label' => get_string('runtimeeventdiag15', 'local_pluginvalidator'),
                        'value' => !$eventclassexists ? $notchecked : ($eventisvalid ? $yes : $no),
                    ],
                ];

                if (!empty($observer['includefile'])) {
                    require_once($this->root($plugin) . '/' . ltrim((string)$observer['includefile'], '/'));
                }

                if ($eventname === '') {
                    throw new coding_exception(get_string('runtimeexception15', 'local_pluginvalidator', (object)['p1' => $position]));
                }

                if (!$eventclassexists) {
                    $message = get_string('runtimeeventmissing', 'local_pluginvalidator', (object)[
                        'component' => $plugin['component'],
                        'position' => $position,
                        'event' => $eventname,
                    ]);
                    if ($isexternal) {
                        $message .= get_string('runtimeeventexternal', 'local_pluginvalidator', $eventcomponent);
                    }
                    throw new coding_exception($message);
                }

                if (!$eventisvalid) {
                    throw new coding_exception(get_string('runtimeexception17', 'local_pluginvalidator', (object)['p1' => $position, 'p2' => $eventname]));
                }

                $this->validate_callable($callback, 1);
                $this->add_check(
                    $groups,
                    $rule,
                    'ok',
                    self::STATE_CONTRACT,
                    get_string('runtimecheck20', 'local_pluginvalidator', (object)['p1' => $eventname]),
                    'db/events.php',
                    $this->callback_name($callback)
                );
            }
        } catch (Throwable $e) {
            $diagnostics = $this->exception_diagnostics($e, $currentdetails);

            $this->add_check(
                $groups,
                $rule,
                'error',
                self::STATE_EXECUTED,
                get_string('runtimecheck21', 'local_pluginvalidator', (object)['p1' => $e->getMessage()]),
                'db/events.php',
                $currenttarget,
                $diagnostics
            );
        }
    }

    /**

     * Validates hook registrations and hook callback signatures.

     */
    private function validate_hooks(array $plugin, array &$groups): void {
        $file = $this->root($plugin) . '/db/hooks.php';
        if (!is_file($file)) {
            return;
        }
        $rule = 'execution:hooks';
        try {
            $callbacks = $this->load_array_file($file, 'callbacks');
            if ($callbacks === []) {
                throw new coding_exception(get_string('runtimeexception18', 'local_pluginvalidator'));
            }
            foreach ($callbacks as $definition) {
                $hook = ltrim((string)($definition['hook'] ?? ''), '\\');
                $callback = $definition['callback'] ?? null;
                if ($hook === '' || !moodle_class_loader::load_symbol($hook)) {
                    throw new coding_exception(get_string('runtimeexception19', 'local_pluginvalidator', (object)['p1' => $hook]));
                }
                $this->validate_callable($callback, 1);
                $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                    get_string('runtimecheck22', 'local_pluginvalidator', (object)['p1' => $hook, 'p2' => $this->callback_name($callback)]),
                    'db/hooks.php', $this->callback_name($callback));
            }
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                get_string('runtimecheck23', 'local_pluginvalidator', (object)['p1' => $e->getMessage()]), 'db/hooks.php');
        }
    }

    /**

     * Includes settings.php with a fresh administration tree.

     */
    private function validate_admin_settings(array $plugin, array &$groups): void {
        global $CFG;
        $file = $this->root($plugin) . '/settings.php';
        if (!is_file($file)) {
            return;
        }
        $rule = 'execution:admin_settings';
        try {
            require_once($CFG->libdir . '/adminlib.php');
            $adminroot = admin_get_root(true, true);
            if (!$adminroot instanceof \admin_root) {
                throw new coding_exception(get_string('runtimeexception20', 'local_pluginvalidator'));
            }
            $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                get_string('runtimecheck24', 'local_pluginvalidator'), 'settings.php');
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                get_string('runtimecheck25', 'local_pluginvalidator', (object)['p1' => $e->getMessage()]), 'settings.php');
        }
    }

    /**

     * Executes privacy metadata providers when safe and validates null providers.

     */
    private function validate_privacy(array $plugin, array &$groups): void {
        $file = $this->root($plugin) . '/classes/privacy/provider.php';
        if (!is_file($file)) {
            return;
        }
        $rule = 'execution:privacy';
        $classname = (string)$plugin['component'] . '\\privacy\\provider';
        try {
            if (!moodle_class_loader::load_symbol($classname)) {
                throw new coding_exception(get_string('runtimeexception21', 'local_pluginvalidator', (object)['p1' => $classname]));
            }
            if (is_subclass_of($classname, \core_privacy\local\metadata\provider::class)) {
                $collection = new \core_privacy\local\metadata\collection((string)$plugin['component']);
                $result = $classname::get_metadata($collection);
                if (!$result instanceof \core_privacy\local\metadata\collection) {
                    throw new coding_exception(get_string('runtimeexception22', 'local_pluginvalidator'));
                }
                $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                    get_string('runtimecheck26', 'local_pluginvalidator', (object)['p1' => $classname]), 'classes/privacy/provider.php', $classname);
                return;
            }
            if (is_subclass_of($classname, \core_privacy\local\metadata\null_provider::class)) {
                $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                    get_string('runtimecheck27', 'local_pluginvalidator', (object)['p1' => $classname]),
                    'classes/privacy/provider.php', $classname);
                return;
            }
            throw new coding_exception(get_string('runtimeexception23', 'local_pluginvalidator'));
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                get_string('runtimecheck28', 'local_pluginvalidator', (object)['p1' => $e->getMessage()]), 'classes/privacy/provider.php', $classname);
        }
    }

    /**

     * Instantiates blocks and get_content() when an instance exists.

     */
    private function validate_block(array $plugin, array &$groups): void {
        global $CFG, $DB, $PAGE;
        if (($plugin['type'] ?? '') !== 'block') {
            return;
        }
        $rule = 'execution:block';
        $name = (string)$plugin['name'];
        $file = $this->root($plugin) . '/block_' . $name . '.php';
        try {
            require_once($CFG->libdir . '/blocklib.php');
            require_once($file);
            $classname = 'block_' . $name;
            if (!class_exists($classname) || !is_subclass_of($classname, 'block_base')) {
                throw new coding_exception(get_string('runtimeexception24', 'local_pluginvalidator', (object)['p1' => $classname]));
            }
            $block = new $classname();
            $formats = $block->applicable_formats();
            if (!is_array($formats)) {
                throw new coding_exception(get_string('runtimeexception25', 'local_pluginvalidator'));
            }
            $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                get_string('runtimecheck29', 'local_pluginvalidator', (object)['p1' => $classname]), basename($file), $classname);

            $instance = $DB->get_record('block_instances', ['blockname' => $name], '*', IGNORE_MULTIPLE);
            if ($instance) {
                $liveblock = block_instance($name, $instance, $PAGE);
                if (!$liveblock) {
                    throw new coding_exception(get_string('runtimeexception26', 'local_pluginvalidator'));
                }
                $liveblock->get_content();
                $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                    get_string('runtimecheck30', 'local_pluginvalidator', (object)['p1' => $classname, 'p2' => $instance->id]),
                    basename($file), $classname . '::get_content');
            } else {
                $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                    get_string('runtimecheck31', 'local_pluginvalidator'),
                    basename($file), $classname . '::get_content');
            }
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                get_string('runtimecheck32', 'local_pluginvalidator', (object)['p1' => $e->getMessage()]), basename($file));
        }
    }

    /**

     * Instantiates text filters and filters a short HTML fragment.

     */
    private function validate_filter(array $plugin, array &$groups): void {
        global $CFG;
        if (($plugin['type'] ?? '') !== 'filter') {
            return;
        }
        $rule = 'execution:filter';
        $name = (string)$plugin['name'];
        $file = $this->root($plugin) . '/filter.php';
        try {
            require_once($CFG->libdir . '/filterlib.php');
            if (is_file($file)) {
                require_once($file);
            }
            $candidates = ['filter_' . $name, 'filter_' . $name . '\\text_filter'];
            $classname = '';
            foreach ($candidates as $candidate) {
                if (moodle_class_loader::load_symbol($candidate)) {
                    $classname = $candidate;
                    break;
                }
            }
            if ($classname === '') {
                throw new coding_exception(get_string('runtimeexception27', 'local_pluginvalidator'));
            }
            $filter = new $classname(\context_system::instance(), []);
            $result = $filter->filter('<p>Plugin validator filter smoke test.</p>', []);
            if (!is_string($result)) {
                throw new coding_exception(get_string('runtimeexception28', 'local_pluginvalidator'));
            }
            $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                get_string('runtimecheck33', 'local_pluginvalidator', (object)['p1' => $classname]),
                is_file($file) ? 'filter.php' : 'classes/text_filter.php',
                $classname
            );
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                get_string('runtimecheck34', 'local_pluginvalidator', (object)['p1' => $e->getMessage()]), is_file($file) ? 'filter.php' : 'classes/text_filter.php');
        }
    }

    /**

     * Validates grade support and executes idempotent grade synchronization when possible.

     */
    private function validate_grade(array $plugin, array &$groups): void {
        global $CFG, $DB;
        if (($plugin['type'] ?? '') !== 'mod') {
            return;
        }
        $root = $this->root($plugin);
        if (!is_file($root . '/lib.php')) {
            return;
        }
        require_once($root . '/lib.php');
        require_once($CFG->libdir . '/gradelib.php');

        $rule = 'execution:grade';
        $name = (string)$plugin['name'];
        $supports = $name . '_supports';
        $itemupdate = $name . '_grade_item_update';
        $updategrades = $name . '_update_grades';
        $declared = function_exists($supports) && defined('FEATURE_GRADE_HAS_GRADE')
            ? (bool)$supports(FEATURE_GRADE_HAS_GRADE) : false;

        if (!$declared && !function_exists($itemupdate) && !function_exists($updategrades)) {
            return;
        }

        if ($declared && !function_exists($itemupdate)) {
            $this->add_check($groups, $rule, 'error', self::STATE_CONTRACT,
                get_string('runtimecheck35', 'local_pluginvalidator'), 'lib.php', $itemupdate);
            return;
        }

        $module = $this->get_module_runtime($plugin);
        if ($module === null) {
            foreach ([$itemupdate, $updategrades] as $function) {
                if (function_exists($function)) {
                    $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                        get_string('runtimecheck36', 'local_pluginvalidator', (object)['p1' => $function]), 'lib.php', $function);
                }
            }
            return;
        }

        try {
            if (function_exists($itemupdate)) {
                $itemupdate($module['instance']);
                $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                    get_string('runtimecheck37', 'local_pluginvalidator', (object)['p1' => $itemupdate, 'p2' => $module['instance']->id]), 'lib.php', $itemupdate);
            }
            if (function_exists($updategrades)) {
                $gradeitem = $DB->get_record('grade_items', [
                    'courseid' => $module['course']->id,
                    'itemtype' => 'mod',
                    'itemmodule' => $name,
                    'iteminstance' => $module['instance']->id,
                ], '*', IGNORE_MULTIPLE);
                $userid = 0;
                if ($gradeitem) {
                    $grade = $DB->get_record('grade_grades', ['itemid' => $gradeitem->id], '*', IGNORE_MULTIPLE);
                    $userid = $grade ? (int)$grade->userid : 0;
                }
                if ($userid > 0) {
                    $reflection = new ReflectionFunction($updategrades);
                    $args = [$module['instance'], $userid];
                    if ($reflection->getNumberOfParameters() >= 3) {
                        $args[] = false;
                    }
                    $reflection->invokeArgs($args);
                    $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                        get_string('runtimecheck38', 'local_pluginvalidator', (object)['p1' => $updategrades, 'p2' => $userid]), 'lib.php', $updategrades);
                } else {
                    $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                        get_string('runtimecheck39', 'local_pluginvalidator', (object)['p1' => $updategrades]),
                        'lib.php', $updategrades);
                }
            }
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                get_string('runtimecheck40', 'local_pluginvalidator', (object)['p1' => $e->getMessage()]), 'lib.php');
        }
    }

    /**

     * Validates legacy and modern activity completion APIs.

     */
    private function validate_completion(array $plugin, array &$groups): void {
        global $USER;
        if (($plugin['type'] ?? '') !== 'mod') {
            return;
        }
        $root = $this->root($plugin);
        if (is_file($root . '/lib.php')) {
            require_once($root . '/lib.php');
        }
        $rule = 'execution:completion';
        $name = (string)$plugin['name'];
        $supports = $name . '_supports';
        $legacy = $name . '_get_completion_state';
        $hasrules = function_exists($supports) && defined('FEATURE_COMPLETION_HAS_RULES')
            ? (bool)$supports(FEATURE_COMPLETION_HAS_RULES) : false;
        $classname = 'mod_' . $name . '\\completion\\custom_completion';
        $hasclass = moodle_class_loader::load_symbol($classname);

        if (!$hasrules && !$hasclass && !function_exists($legacy) && !is_dir($root . '/completion')) {
            return;
        }
        if ($hasrules && !$hasclass && !function_exists($legacy)) {
            $this->add_check($groups, $rule, 'error', self::STATE_CONTRACT,
                get_string('runtimecheck41', 'local_pluginvalidator'),
                'lib.php');
            return;
        }

        $module = $this->get_module_runtime($plugin);
        if ($module === null) {
            $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                get_string('runtimecheck42', 'local_pluginvalidator'),
                $hasclass ? 'classes/completion/custom_completion.php' : 'lib.php');
            return;
        }

        try {
            if ($hasclass) {
                if (!is_subclass_of($classname, \core_completion\activity_custom_completion::class)) {
                    throw new coding_exception(get_string('runtimeexception29', 'local_pluginvalidator', (object)['p1' => $classname]));
                }
                $rules = $classname::get_defined_custom_rules();
                if (!is_array($rules)) {
                    throw new coding_exception(get_string('runtimeexception30', 'local_pluginvalidator'));
                }
                $custom = new $classname($module['cminfo'], (int)$USER->id);
                $descriptions = $custom->get_custom_rule_descriptions();
                $sortorder = $custom->get_sort_order();
                if (!is_array($descriptions) || !is_array($sortorder)) {
                    throw new coding_exception(get_string('runtimeexception31', 'local_pluginvalidator'));
                }

                // Call get_state() only for rules enabled in this activity instance.
                // Activity_custom_completion::validate_rule() intentionally rejects defined but disabled rules.
                $availablerules = $custom->get_available_custom_rules();
                foreach ($availablerules as $completionrule) {
                    if (!in_array($completionrule, $rules, true)) {
                        throw new coding_exception(get_string('runtimeexception32', 'local_pluginvalidator', (object)['p1' => $completionrule]));
                    }
                    $custom->get_state((string)$completionrule);
                }

                $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                    get_string('runtimecheck43', 'local_pluginvalidator', (object)['p1' => $classname, 'p2' => count($rules), 'p3' => count($availablerules)]),
                    'classes/completion/custom_completion.php', $classname);
            }
            if (function_exists($legacy)) {
                $legacy($module['course'], $module['cm'], (int)$USER->id,
                    defined('COMPLETION_AND') ? COMPLETION_AND : 1);
                $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                    get_string('runtimecheck44', 'local_pluginvalidator', (object)['p1' => $legacy]), 'lib.php', $legacy);
            }
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                get_string('runtimecheck45', 'local_pluginvalidator', (object)['p1' => $e->getMessage()]),
                $hasclass ? 'classes/completion/custom_completion.php' : 'lib.php');
        }
    }

    /**

     * Instantiates a custom renderer through Moodle's renderer factory.

     */
    private function validate_renderer(array $plugin, array &$groups): void {
        global $PAGE;
        $file = $this->root($plugin) . '/renderer.php';
        $namespaced = (string)$plugin['component'] . '\\output\\renderer';
        if (!is_file($file) && !moodle_class_loader::load_symbol($namespaced)) {
            return;
        }
        $rule = 'execution:renderer';
        try {
            if (is_file($file)) {
                require_once($file);
            }
            $renderer = $PAGE->get_renderer((string)$plugin['component']);
            if (!$renderer) {
                throw new coding_exception(get_string('runtimeexception33', 'local_pluginvalidator'));
            }
            $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                get_string('runtimecheck46', 'local_pluginvalidator'),
                is_file($file) ? 'renderer.php' : 'classes/output/renderer.php',
                get_class($renderer));
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                get_string('runtimecheck47', 'local_pluginvalidator', (object)['p1' => $e->getMessage()]), is_file($file) ? 'renderer.php' : 'classes/output/renderer.php');
        }
    }

    /**

     * Loads a course using a third-party course format.

     */
    private function validate_course_format(array $plugin, array &$groups): void {
        global $CFG, $DB;
        if (($plugin['type'] ?? '') !== 'format') {
            return;
        }
        $rule = 'execution:course_format';
        require_once($CFG->dirroot . '/course/lib.php');
        $course = $DB->get_record('course', ['format' => (string)$plugin['name']], '*', IGNORE_MULTIPLE);
        if (!$course) {
            $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                get_string('runtimecheck48', 'local_pluginvalidator'), 'lib.php');
            return;
        }
        try {
            $format = course_get_format($course);
            $format->get_course();
            $format->get_section(0);
            $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                get_string('runtimecheck49', 'local_pluginvalidator', (object)['p1' => $course->id]), 'lib.php', get_class($format));
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                get_string('runtimecheck50', 'local_pluginvalidator', (object)['p1' => $e->getMessage()]), 'lib.php');
        }
    }

    /**

     * Authentication plugin smoke test.

     */
    private function validate_auth(array $plugin, array &$groups): void {
        if (($plugin['type'] ?? '') !== 'auth') {
            return;
        }
        $rule = 'execution:auth';
        $file = $this->root($plugin) . '/auth.php';
        $classname = 'auth_plugin_' . $plugin['name'];
        try {
            require_once($file);
            if (!class_exists($classname) || !is_subclass_of($classname, 'auth_plugin_base')) {
                throw new coding_exception(get_string('runtimeexception34', 'local_pluginvalidator', (object)['p1' => $classname]));
            }
            $auth = new $classname();
            $auth->is_internal();
            $auth->can_change_password();
            $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                get_string('runtimecheck51', 'local_pluginvalidator', (object)['p1' => $classname]), 'auth.php', $classname);
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                get_string('runtimecheck52', 'local_pluginvalidator', (object)['p1' => $e->getMessage()]), 'auth.php', $classname);
        }
    }

    /**

     * Enrol plugin class smoke test without adding or removing enrolments.

     */
    private function validate_enrol(array $plugin, array &$groups): void {
        global $CFG;
        if (($plugin['type'] ?? '') !== 'enrol') {
            return;
        }
        $rule = 'execution:enrol';
        try {
            require_once($CFG->libdir . '/enrollib.php');
            $instance = enrol_get_plugin((string)$plugin['name']);
            if (!$instance) {
                throw new coding_exception(get_string('runtimeexception35', 'local_pluginvalidator'));
            }
            $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                get_string('runtimecheck53', 'local_pluginvalidator'),
                'lib.php', get_class($instance));
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                get_string('runtimecheck54', 'local_pluginvalidator', (object)['p1' => $e->getMessage()]), 'lib.php');
        }
    }

    /**

     * Repository plugin contract validation without remote listings.

     */
    private function validate_repository(array $plugin, array &$groups): void {
        global $CFG;
        if (($plugin['type'] ?? '') !== 'repository') {
            return;
        }
        $rule = 'execution:repository';
        $file = $this->root($plugin) . '/lib.php';
        $classname = 'repository_' . $plugin['name'];
        try {
            require_once($CFG->dirroot . '/repository/lib.php');
            require_once($file);
            if (!class_exists($classname) || !is_subclass_of($classname, 'repository')) {
                throw new coding_exception(get_string('runtimeexception36', 'local_pluginvalidator', (object)['p1' => $classname]));
            }
            $reflection = new \ReflectionClass($classname);
            $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                get_string('runtimecheck55', 'local_pluginvalidator', (object)['p1' => $classname]),
                'lib.php', $reflection->getName());
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                get_string('runtimecheck56', 'local_pluginvalidator', (object)['p1' => $e->getMessage()]), 'lib.php', $classname);
        }
    }

    /**

     * Question type smoke test through question_bank.

     */
    private function validate_qtype(array $plugin, array &$groups): void {
        global $CFG;
        if (($plugin['type'] ?? '') !== 'qtype') {
            return;
        }
        $rule = 'execution:qtype';
        try {
            require_once($CFG->dirroot . '/question/engine/bank.php');
            $qtype = \question_bank::get_qtype((string)$plugin['name'], false);
            if (!$qtype) {
                throw new coding_exception(get_string('runtimeexception37', 'local_pluginvalidator'));
            }
            $qtype->menu_name();
            $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                get_string('runtimecheck57', 'local_pluginvalidator'),
                'questiontype.php',
                get_class($qtype)
            );
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                get_string('runtimecheck58', 'local_pluginvalidator', (object)['p1' => $e->getMessage()]), 'questiontype.php');
        }
    }

    /**
     * Keeps the activity backup focused without overriding Moodle's locked settings.
     *
     * @param \backup_controller $controller Backup controller.
     * @return void
     */
    private function configure_activity_backup(\backup_controller $controller): void {
        $plan = $controller->get_plan();

        foreach (['blocks', 'filters'] as $settingname) {
            if (!$plan->setting_exists($settingname)) {
                continue;
            }

            $setting = $plan->get_setting($settingname);
            if ($setting->get_status() === \base_setting::NOT_LOCKED) {
                $setting->set_value(false);
            }
        }
    }

    /**
     * Builds structured technical diagnostics for a runtime exception.
     *
     * @param Throwable $e Exception to describe.
     * @param array $details Context-specific label/value pairs.
     * @return array
     */
    private function exception_diagnostics(Throwable $e, array $details = []): array {
        global $CFG;

        $details[] = [
            'label' => 'Exception class',
            'value' => get_class($e),
        ];
        $filename = str_replace($CFG->dirroot, "", $e->getFile());
        $details[] = [
            'label' => 'Exception location',
            'value' => $filename . ':' . $e->getLine(),
        ];

        if (property_exists($e, 'a') && $e->a !== null && $e->a !== '') {
            $detail = is_scalar($e->a)
                ? (string)$e->a
                : json_encode($e->a, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($detail !== false && $detail !== '') {
                $details[] = [
                    'label' => 'Moodle exception data',
                    'value' => $detail,
                ];
            }
        }

        if (property_exists($e, 'debuginfo') && !empty($e->debuginfo)) {
            $details[] = [
                'label' => 'Moodle debug info',
                'value' => (string)$e->debuginfo,
            ];
        }

        return [
            'hastechnicaldetails' => true,
            'technicaldetails' => $details,
            'exceptiontrace' => $e->getTraceAsString(),
        ];
    }

    /**
     * Formats runtime exceptions while preserving Moodle-specific diagnostic values.
     *
     * @param Throwable $e Exception to format.
     * @return string
     */
    private function format_exception(Throwable $e): string {
        $parts = [$e->getMessage()];

        if (property_exists($e, 'a') && $e->a !== null && $e->a !== '') {
            $detail = is_scalar($e->a)
                ? (string)$e->a
                : json_encode($e->a, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($detail !== false && $detail !== '') {
                $parts[] = 'detail=' . $detail;
            }
        }

        if (property_exists($e, 'debuginfo') && !empty($e->debuginfo)) {
            $parts[] = 'debug=' . (string)$e->debuginfo;
        }

        $parts[] = get_class($e) . ' @ ' . $e->getFile() . ':' . $e->getLine();

        return implode(' | ', $parts);
    }

    /**

     * Returns a realistic installed module context for activity checks.

     */
    private function get_module_runtime(array $plugin): ?array {
        global $DB;
        if (($plugin['type'] ?? '') !== 'mod') {
            return null;
        }
        $record = $DB->get_record_sql(
            "SELECT cm.*, c.id AS courseid
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
               JOIN {course} c ON c.id = cm.course
              WHERE m.name = :modname
                AND cm.instance > 0
                AND cm.deletioninprogress = 0
           ORDER BY cm.id ASC",
            ['modname' => (string)$plugin['name']], IGNORE_MULTIPLE
        );
        if (!$record) {
            return null;
        }
        $course = $DB->get_record('course', ['id' => $record->course], '*', MUST_EXIST);
        $cm = get_coursemodule_from_id((string)$plugin['name'], (int)$record->id, (int)$course->id, false, MUST_EXIST);
        $instance = $DB->get_record((string)$plugin['name'], ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance((int)$cm->id);
        $cminfo = get_fast_modinfo($course)->get_cm((int)$cm->id);
        return compact('course', 'cm', 'instance', 'context', 'cminfo');
    }

    /**

     * Finds the module created by restore_controller.

     */
    private function find_restored_module_id(\restore_controller $restorecontroller, int $oldcontextid): int {
        foreach ($restorecontroller->get_plan()->get_tasks() as $task) {
            if (is_subclass_of($task, 'restore_activity_task') && (int)$task->get_old_contextid() === $oldcontextid) {
                return (int)$task->get_moduleid();
            }
        }
        return 0;
    }

    /**

     * Loads one legacy db/*.php array variable in isolated scope.

     */
    private function load_array_file(string $file, string $variable): array {
        $loader = static function(string $path, string $name): array {
            ${$name} = [];
            include($path);
            $value = ${$name};
            return is_array($value) ? $value : [];
        };
        return $loader($file, $variable);
    }

    /**

     * Validates a callback and its minimum accepted arguments.

     */
    private function validate_callable($callback, int $minimumparameters): void {
        if (is_string($callback) && strpos($callback, '::') !== false) {
            [$callbackclass] = explode('::', ltrim($callback, '\\'), 2);
            moodle_class_loader::load_symbol($callbackclass);
        } else if (is_array($callback) && isset($callback[0]) && is_string($callback[0])) {
            moodle_class_loader::load_symbol($callback[0]);
        }

        if (!is_callable($callback)) {
            throw new coding_exception(get_string('runtimeexception38', 'local_pluginvalidator', (object)['p1' => $this->callback_name($callback)]));
        }
        if (is_array($callback)) {
            $reflection = new ReflectionMethod($callback[0], $callback[1]);
        } else if (is_string($callback) && strpos($callback, '::') !== false) {
            [$class, $method] = explode('::', $callback, 2);
            $reflection = new ReflectionMethod($class, $method);
        } else {
            $reflection = new ReflectionFunction($callback);
        }
        if ($reflection instanceof ReflectionMethod && !$reflection->isPublic()) {
            throw new coding_exception(get_string('runtimeexception39', 'local_pluginvalidator'));
        }
        if ($reflection->getNumberOfParameters() < $minimumparameters) {
            throw new coding_exception(get_string('runtimeexception40', 'local_pluginvalidator', (object)['p1' => $minimumparameters]));
        }
    }

    /**

     * Returns a printable callback name.

     */
    private function callback_name($callback): string {
        if (is_string($callback)) {
            return $callback;
        }
        if (is_array($callback) && count($callback) === 2) {
            return (is_object($callback[0]) ? get_class($callback[0]) : (string)$callback[0]) . '::' . $callback[1];
        }
        return (is_object($callback) ? get_class($callback) : gettype($callback));
    }

    /**
     * Discovers autoloadable plugin classes using Moodle's classes/ path convention.
     *
     * A subdirectory can be supplied when a validator only needs one class family.
     * This prevents unrelated classes from being autoloaded while looking for adhoc
     * tasks. Theme renderer overrides are a common example because legacy core
     * renderer parents are loaded by Moodle's renderer factory, not by PSR autoload.
     *
     * @param array $plugin Plugin information.
     * @param string $subdirectory Optional classes/ subdirectory.
     * @return array
     */
    private function discover_component_classes(array $plugin, string $subdirectory = ''): array {
        $classesroot = $this->root($plugin) . '/classes';
        $subdirectory = trim(str_replace('\\', '/', $subdirectory), '/');
        $root = $subdirectory === '' ? $classesroot : $classesroot . '/' . $subdirectory;
        if (!is_dir($root)) {
            return [];
        }

        $namespace = (string)$plugin['component'];
        if ($subdirectory !== '') {
            $namespace .= '\\' . str_replace('/', '\\', $subdirectory);
        }

        $classes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if (!$item->isFile() || strtolower($item->getExtension()) !== 'php') {
                continue;
            }
            $relative = substr($item->getPathname(), strlen($root) + 1);
            $relative = substr($relative, 0, -4);
            $classes[] = $namespace . '\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
        }
        return $classes;
    }

    /**

     * Converts an autoload class name back to a relative plugin file.

     */
    private function class_file(array $plugin, string $classname): string {
        $prefix = (string)$plugin['component'] . '\\';
        if (!strpos($classname, $prefix) !== 0) {
            return 'classes';
        }
        return 'classes/' . str_replace('\\', '/', substr($classname, strlen($prefix))) . '.php';
    }

    /**

     * Returns normalized plugin root.

     */
    private function root(array $plugin): string {
        return rtrim((string)$plugin['rootdir'], DIRECTORY_SEPARATOR);
    }

    /**

     * Adds one check using the structured engine schema plus execution-state metadata.

     */
    private function add_check(
        array &$groups,
        string $rule,
        string $status,
        string $executionstate,
        string $message,
        string $file = '',
        string $target = '',
        array $diagnostics = []
    ): void {
        if (!isset($groups[$rule])) {
            $groups[$rule] = [
                'rule' => $rule,
                'status' => 'ok',
                'summary' => ['total' => 0, 'ok' => 0, 'warnings' => 0, 'errors' => 0,
                    'executed' => 0, 'contract' => 0, 'notapplicable' => 0],
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
        if ($executionstate === self::STATE_EXECUTED) {
            $groups[$rule]['summary']['executed']++;
        } else if ($executionstate === self::STATE_CONTRACT) {
            $groups[$rule]['summary']['contract']++;
        } else {
            $groups[$rule]['summary']['notapplicable']++;
        }
        $check = [
            'status' => $status,
            'executionstate' => $executionstate,
            'rule' => $rule,
            'file' => $file,
            'line' => 0,
            'key' => $rule,
            'target' => $target,
            'message' => $message,
        ];

        foreach ($diagnostics as $key => $value) {
            $check[$key] = $value;
        }

        $groups[$rule]['checks'][] = $check;
    }
}
