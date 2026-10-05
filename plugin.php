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

use local_pluginvalidator\engine\validation_engine_interface;
use local_pluginvalidator\engine_manager;
use local_pluginvalidator\plugin_repository;
use local_pluginvalidator\validator_runner;

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$component = required_param('component', PARAM_COMPONENT);
$action = optional_param('action', '', PARAM_ALPHA);
$engineid = optional_param('engine', '', PARAM_ALPHANUMEXT);

$repository = new plugin_repository();
$plugin = $repository->get_extension($component);
if ($plugin === null) {
    throw new moodle_exception('invalidplugin', 'local_pluginvalidator');
}

$enginemanager = new engine_manager();
$engines = $enginemanager->get_engines();

if ($engineid === '' && $action !== '') {
    $engineid = 'moodle_plugin_validate';
}

$selectedengine = $engineid !== '' ? $enginemanager->get_engine($engineid) : null;

$result = null;
$notice = null;
$noticeclass = null;

if ($action !== '') {
    require_sesskey();

    if ($selectedengine === null) {
        throw new moodle_exception('invalidengine', 'local_pluginvalidator');
    }

    if ($action === 'installengine') {
        try {
            $release = $selectedengine->install_latest();
            $notice = get_string('engineinstalled', 'local_pluginvalidator', $release['version']);
            $noticeclass = 'success';
        } catch (Throwable $e) {
            $notice = get_string('engineinstallfailed', 'local_pluginvalidator', $e->getMessage());
            $noticeclass = 'danger';
        }
    } else if ($action === 'validate') {
        try {
            $runner = new validator_runner($selectedengine);
            $result = $runner->validate($plugin);
        } catch (Throwable $e) {
            $result = [
                'schema' => 1,
                'engine' => $selectedengine->get_id(),
                'format' => $selectedengine->get_result_format(),
                'component' => $plugin['component'],
                'success' => false,
                'status' => 'error',
                'output' => '',
                'summary' => [
                    'total' => 0,
                    'ok' => 0,
                    'warnings' => 0,
                    'errors' => 1,
                ],
                'groups' => [],
                'runtimeError' => [
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }
}

$enginecards = [];
foreach ($engines as $engine) {
    $status = $engine->get_status();
    $enginecards[] = [
        'id' => $engine->get_id(),
        'name' => $engine->get_name(),
        'description' => $engine->get_description(),
        'format' => $engine->get_result_format(),
        'available' => $status['available'],
        'version' => $status['version'],
        'source' => $status['source'],
        'updatable' => array_key_exists('updatable', $status) ? (bool)$status['updatable'] : true,
    ];
}

$resultsummary = [
    'total' => 0,
    'ok' => 0,
    'warnings' => 0,
    'errors' => 0,
    'executed' => 0,
    'contract' => 0,
    'notapplicable' => 0,
];
$resultgroups = [];
$resultruntimeerror = false;
$resulterrormessage = '';
$resultformat = '';
$resultstructured = false;
$resulttext = false;
$resultoutput = '';
$resultenginename = '';

if ($result !== null) {
    $resultformat = (string)($result['format'] ?? validation_engine_interface::RESULT_STRUCTURED);
    $resultstructured = $resultformat === validation_engine_interface::RESULT_STRUCTURED;
    $resulttext = $resultformat === validation_engine_interface::RESULT_TEXT;
    $resultoutput = (string)($result['output'] ?? '');
    $resultruntimeerror = !empty($result['runtimeError']);
    $resulterrormessage = (string)($result['runtimeError']['message'] ?? '');

    $resultengineid = (string)($result['engine'] ?? '');
    if ($resultengineid !== '' && isset($engines[$resultengineid])) {
        $resultenginename = $engines[$resultengineid]->get_name();
    }

    if ($resultstructured) {
        $resultsummary = array_merge($resultsummary, $result['summary'] ?? []);

        $statusclasses = [
            'ok' => 'success',
            'warning' => 'warning',
            'error' => 'danger',
        ];
        $statuslabels = [
            'ok' => get_string('statusok', 'local_pluginvalidator'),
            'warning' => get_string('statuswarning', 'local_pluginvalidator'),
            'error' => get_string('statuserror', 'local_pluginvalidator'),
        ];
        $executionstateclasses = [
            'executed' => 'primary',
            'contract' => 'info',
            'not_applicable' => 'secondary',
        ];
        $executionstatelabels = [
            'executed' => get_string('executionstateexecuted', 'local_pluginvalidator'),
            'contract' => get_string('executionstatecontract', 'local_pluginvalidator'),
            'not_applicable' => get_string('executionstatenotapplicable', 'local_pluginvalidator'),
        ];

        foreach ($result['groups'] ?? [] as $group) {
            $status = $group['status'] ?? 'ok';
            $group['statusclass'] = $statusclasses[$status] ?? 'secondary';
            $group['statuslabel'] = $statuslabels[$status] ?? $status;
            $viewchecks = [];

            foreach ($group['checks'] ?? [] as $check) {
                $checkstatus = $check['status'] ?? 'ok';
                $check['statusclass'] = $statusclasses[$checkstatus] ?? 'secondary';
                $check['statuslabel'] = $statuslabels[$checkstatus] ?? $checkstatus;
                $executionstate = (string)($check['executionstate'] ?? 'executed');
                $check['executionstateclass'] = $executionstateclasses[$executionstate] ?? 'secondary';
                $check['executionstatelabel'] = $executionstatelabels[$executionstate] ?? $executionstate;

                $file = (string)($check['file'] ?? '');
                $line = (int)($check['line'] ?? 0);
                $check['haslocation'] = $file !== '' && $file !== '.';
                $check['location'] = $check['haslocation']
                    ? $file . ($line > 0 ? ':' . $line : '')
                    : '';

                $target = (string)($check['target'] ?? '');
                $check['hastarget'] = $target !== '' && $target !== $file;

                $viewchecks[] = $check;
            }

            $group['checks'] = $viewchecks;
            $resultgroups[] = $group;
        }
    }
}

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
    'actionurl' => new moodle_url('/local/pluginvalidator/plugin.php'),
    'sesskey' => sesskey(),
    'engines' => $enginecards,
    'hasnotice' => $notice !== null,
    'notice' => $notice,
    'noticeclass' => $noticeclass,
    'hasresult' => $result !== null,
    'resultenginename' => $resultenginename,
    'resultsuccess' => $result['success'] ?? false,
    'resultstructured' => $resultstructured,
    'resulttext' => $resulttext,
    'resultoutput' => $resultoutput,
    'resultok' => $resultsummary['ok'],
    'resultwarnings' => $resultsummary['warnings'],
    'resulterrors' => $resultsummary['errors'],
    'resultexecuted' => $resultsummary['executed'],
    'resultcontract' => $resultsummary['contract'],
    'resultnotapplicable' => $resultsummary['notapplicable'],
    'resultgroups' => $resultgroups,
    'resultruntimeerror' => $resultruntimeerror,
    'resulterrormessage' => $resulterrormessage,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_pluginvalidator/plugin', $templatedata);
echo $OUTPUT->footer();
