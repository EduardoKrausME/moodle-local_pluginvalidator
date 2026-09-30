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
use local_pluginvalidator\engine\moodle_plugin_ci_engine;
use local_pluginvalidator\engine\moodle_plugin_validate_engine;
use local_pluginvalidator\engine\validation_engine_interface;

/**
 * Registry and resolver for validation engines.
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engine_manager {
    /** @var array<string, validation_engine_interface> */
    private array $engines = [];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->register_engine(new moodle_plugin_validate_engine());
        $this->register_engine(new moodle_plugin_ci_engine());
    }

    /**
     * Returns all registered engines keyed by their stable identifier.
     *
     * @return array<string, validation_engine_interface>
     */
    public function get_engines(): array {
        return $this->engines;
    }

    /**
     * Returns one engine by identifier.
     *
     * @param string $engineid Engine identifier.
     * @return validation_engine_interface
     * @throws coding_exception
     */
    public function get_engine(string $engineid): validation_engine_interface {
        if (!isset($this->engines[$engineid])) {
            throw new coding_exception(get_string('invalidengine', 'local_pluginvalidator'));
        }

        return $this->engines[$engineid];
    }

    /**
     * Registers an engine implementation.
     *
     * @param validation_engine_interface $engine Engine.
     */
    private function register_engine(validation_engine_interface $engine): void {
        $this->engines[$engine->get_id()] = $engine;
    }
}
