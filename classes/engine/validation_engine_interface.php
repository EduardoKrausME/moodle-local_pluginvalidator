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

namespace local_pluginvalidator\engine;

/**
 * Contract implemented by validation engines.
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface validation_engine_interface {
    /**
     * Stable engine identifier used in requests and configuration keys.
     *
     * @return string
     */
    public function get_id(): string;

    /**
     * Human-readable engine name.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Human-readable engine description.
     *
     * @return string
     */
    public function get_description(): string;

    /**
     * Returns installation and availability information.
     *
     * @return array
     */
    public function get_status(): array;

    /**
     * Installs or updates the engine to its latest release.
     *
     * @return array
     */
    public function install_latest(): array;

    /**
     * Validates one installed Moodle plugin.
     *
     * All engines return the same structured schema consumed by plugin.php.
     *
     * @param array $plugin Plugin information.
     * @return array
     */
    public function validate(array $plugin): array;
}
