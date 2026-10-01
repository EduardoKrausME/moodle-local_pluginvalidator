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
$string['engine'] = 'Validation engine';
$string['engine_moodlepluginci'] = 'Moodle Plugin CI';
$string['engine_moodlepluginci_desc'] = 'Runs directly in PHP, in this order: savepoints, validate and PHPCS with warnings treated as errors. This engine natively returns textual results.';
$string['engine_moodlepluginvalidate'] = 'Moodle Plugin Validate';
$string['engine_moodlepluginvalidate_desc'] = 'Structured validation from EduardoKrausME/moodle-plugin-validate with separate rules, checks, files and lines.';
$string['engineavailable'] = 'Validation engine available';
$string['engineinstalled'] = 'Validation engine {$a} installed successfully.';
$string['engineinstallfailed'] = 'Unable to install the validation engine: {$a}';
$string['enginemissing'] = 'The validation engine is not installed yet.';
$string['enginenotinstalled'] = 'Install the validation engine before running validation.';
$string['enginesourcebundled'] = '(bundled)';
$string['enginesourcedownloaded'] = '(downloaded)';
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
$string['statuserror'] = 'Error';
$string['statusok'] = 'OK';
$string['statuswarning'] = 'Warning';
$string['summaryerrors'] = 'Errors';
$string['summaryok'] = 'OK';
$string['summarywarnings'] = 'Warnings';
$string['thirdpartyonly'] = 'Only installed extension plugins are shown. Standard Moodle plugins are excluded automatically.';
$string['updateengine'] = 'Update engine';
$string['validationruntimeerror'] = 'Validator runtime error';
$string['validations'] = 'Validations';
$string['validator_desc'] = 'Runs the EduardoKrausME/moodle-plugin-validate library directly against this installed plugin.';
$string['version'] = 'Version';
$string['welcome'] = 'Plugin validator';
$string['welcome_desc'] = 'Select a plugin type to inspect installed third-party plugins. Plugins shipped with Moodle are hidden automatically.';
$string['whythishappened'] = 'Why this happened';
