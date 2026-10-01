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
 * Plugin types page.
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

$query = optional_param('q', '', PARAM_TEXT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/pluginvalidator/index.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginname', 'local_pluginvalidator'));
$PAGE->set_heading(get_string('pluginname', 'local_pluginvalidator'));

$repository = new plugin_repository();
$types = $repository->get_types_with_extensions();
$plugins = $query !== '' ? $repository->search_extensions($query) : [];

$plugins = array_map(static function (array $plugin): array {
    $plugin['url'] = new moodle_url('/local/pluginvalidator/plugin.php', ['component' => $plugin['component']]);
    return $plugin;
}, $plugins);

$templatedata = [
    'intro' => get_string('welcome_desc', 'local_pluginvalidator'),
    'searchurl' => new moodle_url('/local/pluginvalidator/index.php'),
    'query' => $query,
    'hasquery' => $query !== '',
    'hasresults' => !empty($plugins),
    'resulttitle' => get_string('searchresultsfor', 'local_pluginvalidator', $query),
    'plugins' => $plugins,
    'hastypes' => !empty($types),
    'types' => array_values(array_map(static function (array $type): array {
        $type['url'] = new moodle_url('/local/pluginvalidator/plugins.php', ['type' => $type['type']]);
        return $type;
    }, $types)),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_pluginvalidator/index', $templatedata);
echo $OUTPUT->footer();
