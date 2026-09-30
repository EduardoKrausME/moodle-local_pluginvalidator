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
use EduardoKraus\MoodleStringValidate\Validator;

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
     * Runs EduardoKrausME/moodle-plugin-validate against one plugin.
     *
     * The validator library is loaded and executed directly in the current PHP
     * process. This avoids requiring PHP CLI, proc_open(), exec() or shell access.
     *
     * @param array $plugin Plugin information.
     * @return array
     * @throws coding_exception
     */
    public function validate(array $plugin): array {
        $enginepath = $this->engine->get_engine_path();
        if ($enginepath === null) {
            throw new coding_exception(get_string('enginenotinstalled', 'local_pluginvalidator'));
        }

        $engineroot = dirname($enginepath, 2);
        $autoload = $engineroot . '/autoload.php';
        if (!is_readable($autoload)) {
            throw new coding_exception('Unable to load the moodle-plugin-validate autoloader.');
        }

        require_once($autoload);

        try {
            $checks = (new Validator())->validateDetailed($plugin['rootdir']);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'exitcode' => 2,
                'output' => 'ERROR: ' . $e->getMessage(),
                'command' => '',
            ];
        }

        $errorcount = 0;
        $warningcount = 0;
        $okcount = 0;
        $currentrule = null;
        $output = [
            'Moodle String Validate',
            str_repeat('=', 22),
            '',
        ];

        foreach ($checks as $check) {
            $rule = $check->rule;
            if ($rule === '' && str_starts_with($check->key, 'xmldb:')) {
                $rule = 'installxml';
            } else if ($rule === '') {
                $rule = 'general';
            }

            if ($rule !== $currentrule) {
                if ($currentrule !== null) {
                    $output[] = '';
                }
                $output[] = "## {$rule}";
                $currentrule = $rule;
            }

            if ($check->isWarning()) {
                $warningcount++;
                $output[] = "  ▶ WARNING {$check->file}:{$check->line}";
                $output[] = "    {$check->message}";
                continue;
            }

            if (!$check->isError()) {
                $okcount++;

                if ($rule === 'pluginname' && $check->languageString && $check->key === 'pluginname') {
                    continue;
                }

                if ($check->key !== '') {
                    $output[] = '  ▶ OK ' . $check->target();
                } else {
                    $output[] = "  ▶ OK {$check->message}";
                }
                continue;
            }

            $errorcount++;
            $output[] = "  ▶ ERROR {$check->file}:{$check->line}";
            $output[] = "    {$check->message}";
        }

        $output[] = '';
        $output[] = $okcount . ' OK, '
            . $warningcount . ' warning' . ($warningcount === 1 ? '' : 's') . ', '
            . $errorcount . ' error' . ($errorcount === 1 ? '' : 's') . '.';

        return [
            'success' => $errorcount === 0,
            'exitcode' => $errorcount === 0 ? 0 : 1,
            'output' => trim(implode("\n", $output)),
            'command' => '',
        ];
    }
}
