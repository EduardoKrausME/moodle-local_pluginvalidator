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
 * Plugin validation details.
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_pluginvalidator\engine_manager;
use local_pluginvalidator\plugin_repository;
use local_pluginvalidator\validator_runner;

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$component = required_param('component', PARAM_COMPONENT);
$action = optional_param('action', '', PARAM_ALPHA);

$repository = new plugin_repository();
$plugin = $repository->get_extension($component);
if ($plugin === null) {
    throw new moodle_exception('invalidplugin', 'local_pluginvalidator');
}

$engine = new engine_manager();
$result = null;
$notice = null;
$noticeclass = null;

if ($action !== '') {
    require_sesskey();

    if ($action === 'installengine') {
        try {
            $release = $engine->install_latest();
            $notice = get_string('engineinstalled', 'local_pluginvalidator', $release['version']);
            $noticeclass = 'success';
        } catch (Throwable $e) {
            $notice = get_string('engineinstallfailed', 'local_pluginvalidator', $e->getMessage());
            $noticeclass = 'danger';
        }
    } else if ($action === 'validate') {
        try {
            $runner = new validator_runner($engine);
            $result = $runner->validate($plugin);
        } catch (Throwable $e) {
            $result = [
                'success' => false,
                'exitcode' => -1,
                'output' => $e->getMessage(),
                'command' => '',
            ];
        }
    }
}

$enginestatus = $engine->get_status();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/pluginvalidator/plugin.php', ['component' => $component]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title($plugin['displayname']);
$PAGE->set_heading(get_string('pluginname', 'local_pluginvalidator'));

$templatedata = [
    'displayname' => $plugin['displayname'],
    'component' => $plugin['component'],
    'type' => $plugin['type'],
    'release' => $plugin['release'],
    'versiondisk' => $plugin['versiondisk'],
    'rootdir' => $plugin['rootdir'],
    'backurl' => new moodle_url('/local/pluginvalidator/plugins.php', ['type' => $plugin['type']]),
    'engineavailable' => $enginestatus['available'],
    'engineversion' => $enginestatus['version'],
    'enginesource' => $enginestatus['source'],
    'actionurl' => new moodle_url('/local/pluginvalidator/plugin.php'),
    'sesskey' => sesskey(),
    'hasnotice' => $notice !== null,
    'notice' => $notice,
    'noticeclass' => $noticeclass,
    'hasresult' => $result !== null,
    'resultsuccess' => $result['success'] ?? false,
    'resultexitcode' => $result['exitcode'] ?? null,
    'resultoutput' => $result['output'] ?? '',
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_pluginvalidator/plugin', $templatedata);
echo $OUTPUT->footer();
