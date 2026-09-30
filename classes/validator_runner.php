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

use local_pluginvalidator\engine\validation_engine_interface;

/**
 * Executes one validation engine against an installed plugin.
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validator_runner {
    /** @var validation_engine_interface */
    private $engine;

    /**
     * Constructor.
     *
     * @param validation_engine_interface $engine Validation engine.
     */
    public function __construct(validation_engine_interface $engine) {
        $this->engine = $engine;
    }

    /**
     * Runs the selected engine.
     *
     * @param array $plugin Plugin information.
     * @return array
     */
    public function validate(array $plugin): array {
        return $this->engine->validate($plugin);
    }
}
