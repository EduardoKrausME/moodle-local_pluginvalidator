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

/**
 * En Lang
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['back'] = 'Back';
$string['diagnosticdebuginfo'] = 'Moodle debug info';
$string['diagnosticexceptionclass'] = 'Exception class';
$string['diagnosticexceptiondata'] = 'Moodle exception data';
$string['diagnosticexceptionfile'] = 'Exception file';
$string['diagnosticexceptionline'] = 'Exception line';
$string['engine'] = 'Validation engine';
$string['engine_execution'] = 'Runtime execution';
$string['engine_execution_desc'] = 'Executes runtime and contract checks inside Moodle, including backup/restore, lib.php callbacks, tasks, events, hooks, settings, privacy, blocks, filters, File API, grade, completion and plugin-type smoke tests.';
$string['engine_moodlepluginvalidate'] = 'Moodle Plugin Validate';
$string['engine_moodlepluginvalidate_desc'] = 'Structured validation from EduardoKrausME/moodle-plugin-validate with separate rules, checks, files and lines.';
$string['engineavailable'] = 'Validation engine available';
$string['enginebuiltin'] = 'Built in';
$string['enginebundlednoupdate'] = 'The validation engine is bundled with the plugin. Update the plugin package to upgrade the engine.';
$string['engineinstalled'] = 'Validation engine {$a} installed successfully.';
$string['engineinstallfailed'] = 'Unable to install the validation engine: {$a}';
$string['enginemissing'] = 'The validation engine is not installed yet.';
$string['enginenotinstalled'] = 'Install the validation engine before running validation.';
$string['enginephpversion'] = 'The bundled Moodle Plugin Validate engine requires PHP 8.1 or later.';
$string['enginesourcebundled'] = '(bundled)';
$string['enginesourcedownloaded'] = '(downloaded)';
$string['executionbackuperror'] = 'Backup execution failed: {$a}';
$string['executionbackupnoinstance'] = 'backup/moodle2 exists, but no installed activity instance was found to execute a real backup.';
$string['executionbackupok'] = 'Backup executed successfully for course module {$a}.';
$string['executionbackupunsupported'] = 'backup/moodle2 exists, but full runtime backup execution is currently supported only for activity modules.';
$string['executionmodformclassinvalid'] = 'Form class {$a} does not extend moodleform_mod.';
$string['executionmodformclassmissing'] = 'Expected form class {$a} was not found.';
$string['executionmodformerror'] = 'mod_form.php failed during runtime instantiation: {$a}';
$string['executionmodformmissing'] = 'Activity plugin does not contain mod_form.php.';
$string['executionmodformok'] = 'mod_form.php was loaded, instantiated and populated successfully.';
$string['executionnotapplicable'] = 'No runtime execution checks apply to this plugin.';
$string['executionservicecontractonly'] = 'External function {$a->name} has a valid contract, but real execution was skipped: {$a->reason}';
$string['executionserviceerror'] = 'External function {$a->name} failed runtime contract validation: {$a->message}';
$string['executionserviceexecuted'] = 'External function {$a} was executed successfully and returned a value accepted by its declared return contract.';
$string['executionserviceinvalid'] = 'External function {$a} has an invalid definition.';
$string['executionserviceok'] = 'External function {$a} loaded successfully and its parameter/return contracts are valid.';
$string['executionservicesempty'] = 'db/services.php exists but does not define external functions.';
$string['executionserviceskipparams'] = 'the function requires real input parameters';
$string['executionserviceskipwrite'] = 'the function is not explicitly declared as read-only';
$string['executionservicesloaderror'] = 'db/services.php could not be loaded: {$a}';
$string['executionserviceunregistered'] = 'External function {$a} is declared in db/services.php but is not registered in Moodle.';
$string['executionservicewrongcomponent'] = 'External function {$a} is registered for another component.';
$string['executionstatecontract'] = 'Contract validated';
$string['executionstateexecuted'] = 'Executed';
$string['executionstatenotapplicable'] = 'Not applicable';
$string['failed'] = 'Failed';
$string['installengine'] = 'Install engine';
$string['invalidengine'] = 'The selected validation engine does not exist.';
$string['invalidplugin'] = 'The selected plugin does not exist or is a standard Moodle plugin.';
$string['nosearchresults'] = 'No third-party plugins were found for this search.';
$string['nothirdpartyplugins'] = 'No third-party plugins were found.';
$string['passed'] = 'Passed';
$string['pluginname'] = 'Plugin validator';
$string['privacy:metadata'] = 'The Plugin validator does not store personal data.';
$string['release'] = 'Release';
$string['result'] = 'Result';
$string['runtimecheck01'] = 'lib.php failed while loading: {$a->p1}';
$string['runtimecheck02'] = '{$a->p1}() loaded and its callable contract is valid; execution is intentionally skipped.';
$string['runtimecheck03'] = '{$a->p1}() executed for standard feature constants without errors.';
$string['runtimecheck04'] = '{$a->p1}() is callable, but no installed module instance exists for a safe runtime call.';
$string['runtimecheck05'] = '{$a->p1}() executed with course module {$a->p2}.';
$string['runtimecheck06'] = '{$a->p1}() executed and returned {$a->p2} file area(s).';
$string['runtimecheck07'] = '{$a->p1}() failed: {$a->p2}';
$string['runtimecheck08'] = 'lib.php loaded, but none of the standard runtime callbacks targeted by this validator are declared.';
$string['runtimecheck09'] = 'File API create/read/delete round-trip succeeded in area \'{$a->p1}\'.';
$string['runtimecheck10'] = 'File API round-trip failed: {$a->p1}';
$string['runtimecheck11'] = 'backup/moodle2 exists, but automatic round-trip execution is limited to activity modules.';
$string['runtimecheck12'] = 'Backup/restore classes are present, but no installed activity instance exists for a real round-trip.';
$string['runtimecheck13'] = 'Backup + restore round-trip executed successfully from cmid {$a->p1} to temporary cmid {$a->p2}.';
$string['runtimecheck14'] = 'Backup + restore round-trip failed: {$a->p1}';
$string['runtimecheck15'] = '{$a->p1} loaded and get_name() executed; execute() was intentionally not called.';
$string['runtimecheck16'] = 'Scheduled task validation failed: {$a->p1}';
$string['runtimecheck17'] = '{$a->p1} instantiated and custom data serialized; execute() was intentionally not called.';
$string['runtimecheck18'] = 'Adhoc task {$a->p1} failed validation: {$a->p2}';
$string['runtimecheck19'] = 'No class extending core\\task\\adhoc_task was found.';
$string['runtimecheck20'] = 'Observer for {$a->p1} resolves to a callable accepting an event object; event dispatch was skipped.';
$string['runtimecheck21'] = 'Event observer validation failed: {$a->p1}';
$string['runtimecheck22'] = 'Hook {$a->p1} and callback {$a->p2} are loadable; dispatch was skipped.';
$string['runtimecheck23'] = 'Hook validation failed: {$a->p1}';
$string['runtimecheck24'] = 'settings.php loaded successfully while Moodle rebuilt the full administration tree.';
$string['runtimecheck25'] = 'settings.php failed while building the administration tree: {$a->p1}';
$string['runtimecheck26'] = '{$a->p1}::get_metadata() executed successfully.';
$string['runtimecheck27'] = '{$a->p1} correctly declares core_privacy\\local\\metadata\\null_provider.';
$string['runtimecheck28'] = 'Privacy provider validation failed: {$a->p1}';
$string['runtimecheck29'] = '{$a->p1}::init() and applicable_formats() executed successfully.';
$string['runtimecheck30'] = '{$a->p1}::get_content() executed using installed block instance {$a->p2}.';
$string['runtimecheck31'] = 'get_content() is callable, but no installed block instance exists for a realistic execution context.';
$string['runtimecheck32'] = 'Block runtime validation failed: {$a->p1}';
$string['runtimecheck33'] = '{$a->p1}::filter() executed successfully.';
$string['runtimecheck34'] = 'Filter runtime validation failed: {$a->p1}';
$string['runtimecheck35'] = 'FEATURE_GRADE_HAS_GRADE is declared, but grade_item_update() is missing.';
$string['runtimecheck36'] = '{$a->p1}() is callable, but no installed instance exists for a safe execution.';
$string['runtimecheck37'] = '{$a->p1}() executed successfully for instance {$a->p2}.';
$string['runtimecheck38'] = '{$a->p1}() executed for existing graded user {$a->p2}.';
$string['runtimecheck39'] = '{$a->p1}() is valid, but no existing graded user was found; mass grade synchronization was skipped.';
$string['runtimecheck40'] = 'Grade API runtime validation failed: {$a->p1}';
$string['runtimecheck41'] = 'FEATURE_COMPLETION_HAS_RULES is declared, but neither the modern custom_completion class nor legacy get_completion_state() exists.';
$string['runtimecheck42'] = 'Completion API classes/callbacks load, but no installed activity instance exists for runtime evaluation.';
$string['runtimecheck43'] = '{$a->p1} loaded; {$a->p2} custom rule(s) were resolved and {$a->p3} enabled rule(s) were evaluated.';
$string['runtimecheck44'] = '{$a->p1}() executed successfully.';
$string['runtimecheck45'] = 'Completion API runtime validation failed: {$a->p1}';
$string['runtimecheck46'] = 'Custom renderer was instantiated successfully by Moodle.';
$string['runtimecheck47'] = 'Renderer validation failed: {$a->p1}';
$string['runtimecheck48'] = 'Course format class is installed, but no course currently uses it.';
$string['runtimecheck49'] = 'Course format loaded course {$a->p1} and resolved section 0 successfully.';
$string['runtimecheck50'] = 'Course format runtime validation failed: {$a->p1}';
$string['runtimecheck51'] = '{$a->p1} instantiated and basic metadata callbacks executed.';
$string['runtimecheck52'] = 'Authentication plugin validation failed: {$a->p1}';
$string['runtimecheck53'] = 'Enrolment plugin class loaded successfully; add/delete instance operations were intentionally skipped.';
$string['runtimecheck54'] = 'Enrolment plugin validation failed: {$a->p1}';
$string['runtimecheck55'] = '{$a->p1} loaded and repository inheritance is valid; remote listing was intentionally skipped.';
$string['runtimecheck56'] = 'Repository plugin validation failed: {$a->p1}';
$string['runtimecheck57'] = 'Question type instantiated through question_bank and menu_name() executed.';
$string['runtimecheck58'] = 'Question type validation failed: {$a->p1}';
$string['runtimeeventdiag01'] = 'Plugin being validated';
$string['runtimeeventdiag02'] = 'Observer declaration';
$string['runtimeeventdiag03'] = 'Observer';
$string['runtimeeventdiag04'] = 'Observed event';
$string['runtimeeventdiag05'] = 'Event component';
$string['runtimeeventdiag06'] = 'Callback';
$string['runtimeeventdiag07'] = 'Cross-component observer';
$string['runtimeeventdiag08'] = 'Event component installed';
$string['runtimeeventdiag09'] = 'Event component directory';
$string['runtimeeventdiag10'] = 'Moodle autoloader resolved event';
$string['runtimeeventdiag11'] = 'Expected event class file';
$string['runtimeeventdiag12'] = 'Expected event class file exists';
$string['runtimeeventdiag13'] = 'Direct fallback loaded event';
$string['runtimeeventdiag14'] = 'Event class exists';
$string['runtimeeventdiag15'] = 'Extends core\\event\\base';
$string['runtimeeventexternal'] = ' The event belongs to external component \'{$a}\'.';
$string['runtimeeventmissing'] = 'While validating {$a->component}, observer #{$a->position} in db/events.php references event \'{$a->event}\', but the event class cannot be loaded.';
$string['runtimeexception01'] = 'Callback did not resolve to plugin code.';
$string['runtimeexception02'] = 'get_file_areas() must return an array.';
$string['runtimeexception03'] = 'File API round-trip did not return the written content.';
$string['runtimeexception04'] = 'Backup did not produce a backup_destination file.';
$string['runtimeexception05'] = 'Restore precheck returned false.';
$string['runtimeexception06'] = 'The restored course module could not be identified.';
$string['runtimeexception07'] = 'db/tasks.php does not declare any scheduled task.';
$string['runtimeexception08'] = 'Scheduled task class \'{$a->p1}\' cannot be loaded.';
$string['runtimeexception09'] = '{$a->p1} does not extend core\\task\\scheduled_task.';
$string['runtimeexception10'] = '{$a->p1}::get_name() returned an empty value.';
$string['runtimeexception11'] = '{$a->p1}::execute() must be public and parameterless.';
$string['runtimeexception12'] = 'Custom data serialization round-trip failed.';
$string['runtimeexception13'] = 'execute() must be public and parameterless.';
$string['runtimeexception14'] = 'db/events.php does not declare observers.';
$string['runtimeexception15'] = 'Observer #{$a->p1} in db/events.php does not declare a valid eventname.';
$string['runtimeexception17'] = 'Observer #{$a->p1} event \'{$a->p2}\' exists but does not extend core\\event\\base.';
$string['runtimeexception18'] = 'db/hooks.php does not declare callbacks.';
$string['runtimeexception19'] = 'Hook \'{$a->p1}\' cannot be loaded on this Moodle version.';
$string['runtimeexception20'] = 'admin_get_root() did not return an administration tree.';
$string['runtimeexception21'] = 'Privacy provider {$a->p1} cannot be loaded.';
$string['runtimeexception22'] = 'get_metadata() did not return a metadata collection.';
$string['runtimeexception23'] = 'Provider implements neither metadata provider nor null_provider.';
$string['runtimeexception24'] = '{$a->p1} does not extend block_base.';
$string['runtimeexception25'] = 'applicable_formats() must return an array.';
$string['runtimeexception26'] = 'block_instance() failed to create the installed block instance.';
$string['runtimeexception27'] = 'Filter class cannot be loaded.';
$string['runtimeexception28'] = 'filter() must return a string.';
$string['runtimeexception29'] = '{$a->p1} does not extend core_completion\\activity_custom_completion.';
$string['runtimeexception30'] = 'get_defined_custom_rules() must return an array.';
$string['runtimeexception31'] = 'Completion descriptions and sort order must be arrays.';
$string['runtimeexception32'] = 'Available completion rule \'{$a->p1}\' is not defined by the plugin.';
$string['runtimeexception33'] = 'Moodle renderer factory returned no renderer.';
$string['runtimeexception34'] = '{$a->p1} does not extend auth_plugin_base.';
$string['runtimeexception35'] = 'enrol_get_plugin() returned false.';
$string['runtimeexception36'] = '{$a->p1} does not extend repository.';
$string['runtimeexception37'] = 'question_bank::get_qtype() returned no question type.';
$string['runtimeexception38'] = 'Callback \'{$a->p1}\' is not callable.';
$string['runtimeexception39'] = 'Callback method must be public.';
$string['runtimeexception40'] = 'Callback must accept at least {$a->p1} parameter(s).';
$string['runtimestate01'] = 'Yes';
$string['runtimestate02'] = 'No';
$string['runtimestate03'] = 'Unknown';
$string['runtimestate04'] = 'Not checked';
$string['runtimestate05'] = 'Not attempted';
$string['runtimestate06'] = '(unknown)';
$string['runtimestate07'] = '(empty)';
$string['runtimestate08'] = '(not resolved)';
$string['runvalidation'] = 'Run validation';
$string['search'] = 'Search';
$string['searchplaceholder'] = 'Plugin name, component or type';
$string['searchplugins'] = 'Search plugins';
$string['searchresultsfor'] = 'Results for "{$a}"';
$string['stacktrace'] = 'Stack trace';
$string['statuserror'] = 'Error';
$string['statusok'] = 'OK';
$string['statuswarning'] = 'Warning';
$string['summaryerrors'] = 'Errors';
$string['summaryok'] = 'OK';
$string['summarywarnings'] = 'Warnings';
$string['technicaldetails'] = 'Technical details';
$string['thirdpartyonly'] = 'Only installed extension plugins are shown. Standard Moodle plugins are excluded automatically.';
$string['updateengine'] = 'Update engine';
$string['validationruntimeerror'] = 'Validator runtime error';
$string['validations'] = 'Validations';
$string['validator_desc'] = 'Runs the EduardoKrausME/moodle-plugin-validate library directly against this installed plugin.';
$string['version'] = 'Version';
$string['welcome'] = 'Plugin validator';
$string['welcome_desc'] = 'Select a plugin type to inspect installed third-party plugins. Plugins shipped with Moodle are hidden automatically.';
