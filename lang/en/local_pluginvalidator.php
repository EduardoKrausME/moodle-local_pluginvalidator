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
$string['engineinstalled'] = 'Validation engine {$a} installed successfully.';
$string['engineinstallfailed'] = 'Unable to install the validation engine: {$a}';
$string['enginemissing'] = 'The validation engine is not installed yet.';
$string['enginenotinstalled'] = 'Install the validation engine before running validation.';
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
$string['howtofix'] = 'How to fix';
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
$string['whythishappened'] = 'Why this happened';
