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

namespace local_pluginvalidator;

use coding_exception;

/**
 * Runs validation commands against an installed plugin.
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validator_runner {
    /** @var engine_manager */
    private $engine;

    /**
     * Constructor.
     *
     * @param engine_manager $engine Engine manager.
     */
    public function __construct(engine_manager $engine) {
        $this->engine = $engine;
    }

    /**
     * Runs moodle-plugin-ci validate against one plugin.
     *
     * @param array $plugin Plugin information.
     * @return array
     * @throws coding_exception
     */
    public function validate(array $plugin): array {
        global $CFG;

        $enginepath = $this->engine->get_engine_path();
        if ($enginepath === null) {
            throw new coding_exception(get_string('enginenotinstalled', 'local_pluginvalidator'));
        }
        if (!function_exists('proc_open')) {
            throw new coding_exception(get_string('procopendisabled', 'local_pluginvalidator'));
        }

        $php = $this->engine->get_php_binary();
        $command = escapeshellarg($php)
            . ' ' . escapeshellarg($enginepath)
            . ' validate --no-ansi --no-interaction'
            . ' --moodle=' . escapeshellarg($CFG->dirroot)
            . ' -- ' . escapeshellarg($plugin['rootdir']);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $CFG->dirroot);
        if (!is_resource($process)) {
            throw new coding_exception('Unable to start moodle-plugin-ci.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitcode = proc_close($process);

        $output = trim($stdout . ($stderr !== '' ? "\n" . $stderr : ''));

        return [
            'success' => $exitcode === 0,
            'exitcode' => $exitcode,
            'output' => $output,
            'command' => $command,
        ];
    }
}
