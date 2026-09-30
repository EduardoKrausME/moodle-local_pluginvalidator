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
 * Third-party plugins for one plugin type.
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_pluginvalidator\plugin_repository;

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$type = required_param('type', PARAM_ALPHANUMEXT);

$repository = new plugin_repository();
$plugins = $repository->get_extensions_of_type($type);
if (empty($plugins)) {
    redirect(new moodle_url('/local/pluginvalidator/index.php'));
}

$typename = core_plugin_manager::instance()->plugintype_name_plural($type);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/pluginvalidator/plugins.php', ['type' => $type]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title($typename);
$PAGE->set_heading(get_string('pluginname', 'local_pluginvalidator'));

$templatedata = [
    'typename' => $typename,
    'backurl' => (new moodle_url('/local/pluginvalidator/index.php'))->out(false),
    'plugins' => array_values(array_map(static function(array $plugin): array {
        $plugin['url'] = new moodle_url('/local/pluginvalidator/plugin.php', ['component' => $plugin['component']]);
        return $plugin;
    }, $plugins)),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_pluginvalidator/plugins', $templatedata);
echo $OUTPUT->footer();
