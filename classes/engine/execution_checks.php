<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_pluginvalidator\engine;

use coding_exception;
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
                'lib.php failed while loading: ' . $e->getMessage(), 'lib.php');
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
                    throw new coding_exception('Callback did not resolve to plugin code.');
                }

                if ($mode === 'contract') {
                    $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                        "{$function}() loaded and its callable contract is valid; execution is intentionally skipped.",
                        'lib.php', $function);
                    continue;
                }

                if (str_ends_with($function, '_supports')) {
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
                        "{$function}() executed for standard feature constants without errors.", 'lib.php', $function);
                    continue;
                }

                if ($module === null) {
                    $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                        "{$function}() is callable, but no installed module instance exists for a safe runtime call.",
                        'lib.php', $function);
                    continue;
                }

                if (str_ends_with($function, '_get_coursemodule_info')) {
                    $function($module['cm']);
                    $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                        "{$function}() executed with course module {$module['cm']->id}.", 'lib.php', $function);
                    continue;
                }

                if (str_ends_with($function, '_get_file_areas')) {
                    $areas = $function($module['course'], $module['cm'], $module['context']);
                    if (!is_array($areas)) {
                        throw new coding_exception('get_file_areas() must return an array.');
                    }
                    $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                        "{$function}() executed and returned " . count($areas) . ' file area(s).', 'lib.php', $function);
                    $this->validate_file_storage($plugin, $module, $areas, $groups);
                }
            } catch (Throwable $e) {
                $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                    "{$function}() failed: {$e->getMessage()}", 'lib.php', $function);
            }
        }

        if (!$found) {
            $this->add_check($groups, $rule, 'ok', self::STATE_NOT_APPLICABLE,
                'lib.php loaded, but none of the standard runtime callbacks targeted by this validator are declared.',
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
                throw new coding_exception('File API round-trip did not return the written content.');
            }
            $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                "File API create/read/delete round-trip succeeded in area '{$filearea}'.", 'lib.php', $filearea);
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                'File API round-trip failed: ' . $e->getMessage(), 'lib.php', $filearea);
        } finally {
            if ($storedfile) {
                try {
                    $storedfile->delete();
                } catch (Throwable $ignored) {
                    // Cleanup failure must not hide the primary validation result.
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
                'backup/moodle2 exists, but automatic round-trip execution is limited to activity modules.',
                'backup/moodle2');
            return;
        }

        $module = $this->get_module_runtime($plugin);
        if ($module === null) {
            $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                'Backup/restore classes are present, but no installed activity instance exists for a real round-trip.',
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
                \backup::MODE_IMPORT,
                $USER->id
            );
            $backupid = $backupcontroller->get_backupid();
            $backupbasepath = $backupcontroller->get_plan()->get_basepath();
            $backupcontroller->execute_plan();
            $backupcontroller->destroy();
            $backupcontroller = null;

            $restorecontroller = new \restore_controller(
                $backupid,
                (int)$module['course']->id,
                \backup::INTERACTIVE_NO,
                \backup::MODE_IMPORT,
                $USER->id,
                \backup::TARGET_CURRENT_ADDING
            );

            if (!$restorecontroller->execute_precheck()) {
                throw new coding_exception('Restore precheck returned false.');
            }
            $restorecontroller->execute_plan();
            $newcmid = $this->find_restored_module_id($restorecontroller, (int)$module['context']->id);
            if (!$newcmid) {
                throw new coding_exception('The restored course module could not be identified.');
            }

            $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                "Backup + restore round-trip executed successfully from cmid {$module['cm']->id} to temporary cmid {$newcmid}.",
                'backup/moodle2', 'cmid=' . (int)$module['cm']->id);
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                'Backup + restore round-trip failed: ' . $e->getMessage(), 'backup/moodle2',
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
                }
            }
            if ($backupcontroller) {
                try {
                    $backupcontroller->destroy();
                } catch (Throwable $ignored) {
                    // Continue cleanup.
                }
            }
            if ($backupbasepath && is_dir($backupbasepath)) {
                fulldelete($backupbasepath);
            }
        }
    }

    /** Validates scheduled task declarations without calling execute(). */
    private function validate_scheduled_tasks(array $plugin, array &$groups): void {
        $file = $this->root($plugin) . '/db/tasks.php';
        if (!is_file($file)) {
            return;
        }
        $rule = 'execution:scheduled_tasks';
        try {
            $tasks = $this->load_array_file($file, 'tasks');
            if ($tasks === []) {
                throw new coding_exception('db/tasks.php does not declare any scheduled task.');
            }
            foreach ($tasks as $task) {
                $classname = (string)($task['classname'] ?? '');
                if ($classname === '' || !class_exists($classname)) {
                    throw new coding_exception("Scheduled task class '{$classname}' cannot be loaded.");
                }
                if (!is_subclass_of($classname, \core\task\scheduled_task::class)) {
                    throw new coding_exception("{$classname} does not extend core\\task\\scheduled_task.");
                }
                $instance = new $classname();
                $name = $instance->get_name();
                if (!is_string($name) || $name === '') {
                    throw new coding_exception("{$classname}::get_name() returned an empty value.");
                }
                $method = new ReflectionMethod($classname, 'execute');
                if (!$method->isPublic() || $method->getNumberOfRequiredParameters() !== 0) {
                    throw new coding_exception("{$classname}::execute() must be public and parameterless.");
                }
                $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                    "{$classname} loaded and get_name() executed; execute() was intentionally not called.",
                    'db/tasks.php', $classname);
            }
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                'Scheduled task validation failed: ' . $e->getMessage(), 'db/tasks.php');
        }
    }

    /** Discovers adhoc tasks and validates custom data serialization without executing them. */
    private function validate_adhoc_tasks(array $plugin, array &$groups): void {
        $classes = $this->discover_component_classes($plugin);
        $rule = 'execution:adhoc_tasks';
        $found = false;
        foreach ($classes as $classname) {
            try {
                if (!class_exists($classname) || !is_subclass_of($classname, \core\task\adhoc_task::class)) {
                    continue;
                }
                $found = true;
                $task = new $classname();
                $task->set_custom_data(['pluginvalidator' => true]);
                $data = $task->get_custom_data();
                if (!is_object($data) || empty($data->pluginvalidator)) {
                    throw new coding_exception('Custom data serialization round-trip failed.');
                }
                $method = new ReflectionMethod($classname, 'execute');
                if (!$method->isPublic() || $method->getNumberOfRequiredParameters() !== 0) {
                    throw new coding_exception('execute() must be public and parameterless.');
                }
                $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                    "{$classname} instantiated and custom data serialized; execute() was intentionally not called.",
                    $this->class_file($plugin, $classname), $classname);
            } catch (Throwable $e) {
                $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                    "Adhoc task {$classname} failed validation: {$e->getMessage()}",
                    $this->class_file($plugin, $classname), $classname);
            }
        }
        if (!$found && is_dir($this->root($plugin) . '/classes/task')) {
            $this->add_check($groups, $rule, 'ok', self::STATE_NOT_APPLICABLE,
                'No class extending core\\task\\adhoc_task was found.', 'classes/task');
        }
    }

    /** Validates db/events.php observers and callback signatures. */
    private function validate_event_observers(array $plugin, array &$groups): void {
        $file = $this->root($plugin) . '/db/events.php';
        if (!is_file($file)) {
            return;
        }
        $rule = 'execution:events';
        try {
            $observers = $this->load_array_file($file, 'observers');
            if ($observers === []) {
                throw new coding_exception('db/events.php does not declare observers.');
            }
            foreach ($observers as $observer) {
                $eventname = ltrim((string)($observer['eventname'] ?? ''), '\\');
                $callback = $observer['callback'] ?? null;
                if (!empty($observer['includefile'])) {
                    require_once($this->root($plugin) . '/' . ltrim((string)$observer['includefile'], '/'));
                }
                if ($eventname === '' || !class_exists($eventname)
                        || !is_subclass_of($eventname, \core\event\base::class)) {
                    throw new coding_exception("Observer event '{$eventname}' is not a valid Moodle event class.");
                }
                $this->validate_callable($callback, 1);
                $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                    "Observer for {$eventname} resolves to a callable accepting an event object; event dispatch was skipped.",
                    'db/events.php', $this->callback_name($callback));
            }
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                'Event observer validation failed: ' . $e->getMessage(), 'db/events.php');
        }
    }

    /** Validates hook registrations and hook callback signatures. */
    private function validate_hooks(array $plugin, array &$groups): void {
        $file = $this->root($plugin) . '/db/hooks.php';
        if (!is_file($file)) {
            return;
        }
        $rule = 'execution:hooks';
        try {
            $callbacks = $this->load_array_file($file, 'callbacks');
            if ($callbacks === []) {
                throw new coding_exception('db/hooks.php does not declare callbacks.');
            }
            foreach ($callbacks as $definition) {
                $hook = ltrim((string)($definition['hook'] ?? ''), '\\');
                $callback = $definition['callback'] ?? null;
                if ($hook === '' || (!class_exists($hook) && !interface_exists($hook))) {
                    throw new coding_exception("Hook '{$hook}' cannot be loaded on this Moodle version.");
                }
                $this->validate_callable($callback, 1);
                $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                    "Hook {$hook} and callback {$this->callback_name($callback)} are loadable; dispatch was skipped.",
                    'db/hooks.php', $this->callback_name($callback));
            }
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                'Hook validation failed: ' . $e->getMessage(), 'db/hooks.php');
        }
    }

    /** Includes settings.php with a fresh administration tree. */
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
                throw new coding_exception('admin_get_root() did not return an administration tree.');
            }
            $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                'settings.php loaded successfully while Moodle rebuilt the full administration tree.', 'settings.php');
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                'settings.php failed while building the administration tree: ' . $e->getMessage(), 'settings.php');
        }
    }

    /** Executes privacy metadata providers when safe and validates null providers. */
    private function validate_privacy(array $plugin, array &$groups): void {
        $file = $this->root($plugin) . '/classes/privacy/provider.php';
        if (!is_file($file)) {
            return;
        }
        $rule = 'execution:privacy';
        $classname = (string)$plugin['component'] . '\\privacy\\provider';
        try {
            if (!class_exists($classname)) {
                throw new coding_exception("Privacy provider {$classname} cannot be loaded.");
            }
            if (is_subclass_of($classname, \core_privacy\local\metadata\provider::class)) {
                $collection = new \core_privacy\local\metadata\collection((string)$plugin['component']);
                $result = $classname::get_metadata($collection);
                if (!$result instanceof \core_privacy\local\metadata\collection) {
                    throw new coding_exception('get_metadata() did not return a metadata collection.');
                }
                $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                    "{$classname}::get_metadata() executed successfully.", 'classes/privacy/provider.php', $classname);
                return;
            }
            if (is_subclass_of($classname, \core_privacy\local\metadata\null_provider::class)) {
                $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                    "{$classname} correctly declares core_privacy\\local\\metadata\\null_provider.",
                    'classes/privacy/provider.php', $classname);
                return;
            }
            throw new coding_exception('Provider implements neither metadata provider nor null_provider.');
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                'Privacy provider validation failed: ' . $e->getMessage(), 'classes/privacy/provider.php', $classname);
        }
    }

    /** Instantiates blocks and get_content() when an instance exists. */
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
                throw new coding_exception("{$classname} does not extend block_base.");
            }
            $block = new $classname();
            $formats = $block->applicable_formats();
            if (!is_array($formats)) {
                throw new coding_exception('applicable_formats() must return an array.');
            }
            $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                "{$classname}::init() and applicable_formats() executed successfully.", basename($file), $classname);

            $instance = $DB->get_record('block_instances', ['blockname' => $name], '*', IGNORE_MULTIPLE);
            if ($instance) {
                $liveblock = block_instance($name, $instance, $PAGE);
                if (!$liveblock) {
                    throw new coding_exception('block_instance() failed to create the installed block instance.');
                }
                $liveblock->get_content();
                $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                    "{$classname}::get_content() executed using installed block instance {$instance->id}.",
                    basename($file), $classname . '::get_content');
            } else {
                $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                    'get_content() is callable, but no installed block instance exists for a realistic execution context.',
                    basename($file), $classname . '::get_content');
            }
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                'Block runtime validation failed: ' . $e->getMessage(), basename($file));
        }
    }

    /** Instantiates text filters and filters a short HTML fragment. */
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
                if (class_exists($candidate)) {
                    $classname = $candidate;
                    break;
                }
            }
            if ($classname === '') {
                throw new coding_exception('Filter class cannot be loaded.');
            }
            $filter = new $classname(\context_system::instance(), []);
            $result = $filter->filter('<p>Plugin validator filter smoke test.</p>', []);
            if (!is_string($result)) {
                throw new coding_exception('filter() must return a string.');
            }
            $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                "{$classname}::filter() executed successfully.", is_file($file) ? 'filter.php' : 'classes/text_filter.php', $classname);
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                'Filter runtime validation failed: ' . $e->getMessage(), is_file($file) ? 'filter.php' : 'classes/text_filter.php');
        }
    }

    /** Validates grade support and executes idempotent grade synchronization when possible. */
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
                'FEATURE_GRADE_HAS_GRADE is declared, but grade_item_update() is missing.', 'lib.php', $itemupdate);
            return;
        }

        $module = $this->get_module_runtime($plugin);
        if ($module === null) {
            foreach ([$itemupdate, $updategrades] as $function) {
                if (function_exists($function)) {
                    $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                        "{$function}() is callable, but no installed instance exists for a safe execution.", 'lib.php', $function);
                }
            }
            return;
        }

        try {
            if (function_exists($itemupdate)) {
                $itemupdate($module['instance']);
                $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                    "{$itemupdate}() executed successfully for instance {$module['instance']->id}.", 'lib.php', $itemupdate);
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
                        "{$updategrades}() executed for existing graded user {$userid}.", 'lib.php', $updategrades);
                } else {
                    $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                        "{$updategrades}() is valid, but no existing graded user was found; mass grade synchronization was skipped.",
                        'lib.php', $updategrades);
                }
            }
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                'Grade API runtime validation failed: ' . $e->getMessage(), 'lib.php');
        }
    }

    /** Validates legacy and modern activity completion APIs. */
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
        $hasclass = class_exists($classname);

        if (!$hasrules && !$hasclass && !function_exists($legacy) && !is_dir($root . '/completion')) {
            return;
        }
        if ($hasrules && !$hasclass && !function_exists($legacy)) {
            $this->add_check($groups, $rule, 'error', self::STATE_CONTRACT,
                'FEATURE_COMPLETION_HAS_RULES is declared, but neither the modern custom_completion class nor legacy get_completion_state() exists.',
                'lib.php');
            return;
        }

        $module = $this->get_module_runtime($plugin);
        if ($module === null) {
            $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                'Completion API classes/callbacks load, but no installed activity instance exists for runtime evaluation.',
                $hasclass ? 'classes/completion/custom_completion.php' : 'lib.php');
            return;
        }

        try {
            if ($hasclass) {
                if (!is_subclass_of($classname, \core_completion\activity_custom_completion::class)) {
                    throw new coding_exception("{$classname} does not extend core_completion\\activity_custom_completion.");
                }
                $rules = $classname::get_defined_custom_rules();
                if (!is_array($rules)) {
                    throw new coding_exception('get_defined_custom_rules() must return an array.');
                }
                $custom = new $classname($module['cminfo'], (int)$USER->id);
                $descriptions = $custom->get_custom_rule_descriptions();
                $sortorder = $custom->get_sort_order();
                if (!is_array($descriptions) || !is_array($sortorder)) {
                    throw new coding_exception('Completion descriptions and sort order must be arrays.');
                }
                foreach ($rules as $completionrule) {
                    $custom->get_state((string)$completionrule);
                }
                $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                    "{$classname} loaded; " . count($rules) . ' custom rule(s) were resolved and evaluated.',
                    'classes/completion/custom_completion.php', $classname);
            }
            if (function_exists($legacy)) {
                $legacy($module['course'], $module['cm'], (int)$USER->id,
                    defined('COMPLETION_AND') ? COMPLETION_AND : 1);
                $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                    "{$legacy}() executed successfully.", 'lib.php', $legacy);
            }
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                'Completion API runtime validation failed: ' . $e->getMessage(),
                $hasclass ? 'classes/completion/custom_completion.php' : 'lib.php');
        }
    }

    /** Instantiates a custom renderer through Moodle's renderer factory. */
    private function validate_renderer(array $plugin, array &$groups): void {
        global $PAGE;
        $file = $this->root($plugin) . '/renderer.php';
        $namespaced = (string)$plugin['component'] . '\\output\\renderer';
        if (!is_file($file) && !class_exists($namespaced)) {
            return;
        }
        $rule = 'execution:renderer';
        try {
            if (is_file($file)) {
                require_once($file);
            }
            $renderer = $PAGE->get_renderer((string)$plugin['component']);
            if (!$renderer) {
                throw new coding_exception('Moodle renderer factory returned no renderer.');
            }
            $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                'Custom renderer was instantiated successfully by Moodle.', is_file($file) ? 'renderer.php' : 'classes/output/renderer.php',
                get_class($renderer));
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                'Renderer validation failed: ' . $e->getMessage(), is_file($file) ? 'renderer.php' : 'classes/output/renderer.php');
        }
    }

    /** Loads a course using a third-party course format. */
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
                'Course format class is installed, but no course currently uses it.', 'lib.php');
            return;
        }
        try {
            $format = course_get_format($course);
            $format->get_course();
            $format->get_section(0);
            $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                "Course format loaded course {$course->id} and resolved section 0 successfully.", 'lib.php', get_class($format));
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                'Course format runtime validation failed: ' . $e->getMessage(), 'lib.php');
        }
    }

    /** Authentication plugin smoke test. */
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
                throw new coding_exception("{$classname} does not extend auth_plugin_base.");
            }
            $auth = new $classname();
            $auth->is_internal();
            $auth->can_change_password();
            $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                "{$classname} instantiated and basic metadata callbacks executed.", 'auth.php', $classname);
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                'Authentication plugin validation failed: ' . $e->getMessage(), 'auth.php', $classname);
        }
    }

    /** Enrol plugin class smoke test without adding or removing enrolments. */
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
                throw new coding_exception('enrol_get_plugin() returned false.');
            }
            $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                'Enrolment plugin class loaded successfully; add/delete instance operations were intentionally skipped.',
                'lib.php', get_class($instance));
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                'Enrolment plugin validation failed: ' . $e->getMessage(), 'lib.php');
        }
    }

    /** Repository plugin contract validation without remote listings. */
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
                throw new coding_exception("{$classname} does not extend repository.");
            }
            $reflection = new \ReflectionClass($classname);
            $this->add_check($groups, $rule, 'ok', self::STATE_CONTRACT,
                "{$classname} loaded and repository inheritance is valid; remote listing was intentionally skipped.",
                'lib.php', $reflection->getName());
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                'Repository plugin validation failed: ' . $e->getMessage(), 'lib.php', $classname);
        }
    }

    /** Question type smoke test through question_bank. */
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
                throw new coding_exception('question_bank::get_qtype() returned no question type.');
            }
            $qtype->menu_name();
            $this->add_check($groups, $rule, 'ok', self::STATE_EXECUTED,
                'Question type instantiated through question_bank and menu_name() executed.', 'questiontype.php', get_class($qtype));
        } catch (Throwable $e) {
            $this->add_check($groups, $rule, 'error', self::STATE_EXECUTED,
                'Question type validation failed: ' . $e->getMessage(), 'questiontype.php');
        }
    }

    /** Returns a realistic installed module context for activity checks. */
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

    /** Finds the module created by restore_controller. */
    private function find_restored_module_id(\restore_controller $restorecontroller, int $oldcontextid): int {
        foreach ($restorecontroller->get_plan()->get_tasks() as $task) {
            if (is_subclass_of($task, 'restore_activity_task') && (int)$task->get_old_contextid() === $oldcontextid) {
                return (int)$task->get_moduleid();
            }
        }
        return 0;
    }

    /** Loads one legacy db/*.php array variable in isolated scope. */
    private function load_array_file(string $file, string $variable): array {
        $loader = static function(string $path, string $name): array {
            ${$name} = [];
            include($path);
            $value = ${$name};
            return is_array($value) ? $value : [];
        };
        return $loader($file, $variable);
    }

    /** Validates a callback and its minimum accepted arguments. */
    private function validate_callable($callback, int $minimumparameters): void {
        if (!is_callable($callback)) {
            throw new coding_exception("Callback '{$this->callback_name($callback)}' is not callable.");
        }
        if (is_array($callback)) {
            $reflection = new ReflectionMethod($callback[0], $callback[1]);
        } else if (is_string($callback) && str_contains($callback, '::')) {
            [$class, $method] = explode('::', $callback, 2);
            $reflection = new ReflectionMethod($class, $method);
        } else {
            $reflection = new ReflectionFunction($callback);
        }
        if ($reflection instanceof ReflectionMethod && !$reflection->isPublic()) {
            throw new coding_exception('Callback method must be public.');
        }
        if ($reflection->getNumberOfParameters() < $minimumparameters) {
            throw new coding_exception("Callback must accept at least {$minimumparameters} parameter(s).");
        }
    }

    /** Returns a printable callback name. */
    private function callback_name($callback): string {
        if (is_string($callback)) {
            return $callback;
        }
        if (is_array($callback) && count($callback) === 2) {
            return (is_object($callback[0]) ? get_class($callback[0]) : (string)$callback[0]) . '::' . $callback[1];
        }
        return get_debug_type($callback);
    }

    /** Discovers autoloadable plugin classes using Moodle's classes/ path convention. */
    private function discover_component_classes(array $plugin): array {
        $root = $this->root($plugin) . '/classes';
        if (!is_dir($root)) {
            return [];
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
            $classes[] = (string)$plugin['component'] . '\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
        }
        return $classes;
    }

    /** Converts an autoload class name back to a relative plugin file. */
    private function class_file(array $plugin, string $classname): string {
        $prefix = (string)$plugin['component'] . '\\';
        if (!str_starts_with($classname, $prefix)) {
            return 'classes';
        }
        return 'classes/' . str_replace('\\', '/', substr($classname, strlen($prefix))) . '.php';
    }

    /** Returns normalized plugin root. */
    private function root(array $plugin): string {
        return rtrim((string)$plugin['rootdir'], DIRECTORY_SEPARATOR);
    }

    /** Adds one check using the structured engine schema plus execution-state metadata. */
    private function add_check(
        array &$groups,
        string $rule,
        string $status,
        string $executionstate,
        string $message,
        string $file = '',
        string $target = ''
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
        $groups[$rule]['checks'][] = [
            'status' => $status,
            'executionstate' => $executionstate,
            'rule' => $rule,
            'file' => $file,
            'line' => 0,
            'key' => $rule,
            'target' => $target,
            'message' => $message,
        ];
    }
}
