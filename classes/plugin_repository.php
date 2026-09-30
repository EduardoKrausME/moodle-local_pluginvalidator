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

use core\plugininfo\base;
use core_component;
use core_plugin_manager;
use core_text;

/**
 * Finds installed third-party plugins.
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_repository {
    /**
     * Returns plugin types that contain at least one extension plugin.
     *
     * @return array
     */
    public function get_types_with_extensions(): array {
        $result = [];
        foreach (core_component::get_plugin_types() as $type => $path) {
            $plugins = $this->get_extensions_of_type($type);
            if (empty($plugins)) {
                continue;
            }

            $result[$type] = [
                'type' => $type,
                'name' => core_plugin_manager::instance()->plugintype_name_plural($type),
                'count' => count($plugins),
            ];
        }

        uasort($result, static function(array $a, array $b): int {
            return strcasecmp($a['name'], $b['name']);
        });

        return $result;
    }

    /**
     * Returns extension plugins of a given type.
     *
     * Standard plugins shipped with Moodle are intentionally excluded.
     *
     * @param string $type Plugin type.
     * @return array
     */
    public function get_extensions_of_type(string $type): array {
        if (!array_key_exists($type, core_component::get_plugin_types())) {
            return [];
        }

        $result = [];
        $plugins = core_plugin_manager::instance()->get_plugins_of_type($type);

        foreach ($plugins as $name => $plugininfo) {
            if ($plugininfo->is_standard() || empty($plugininfo->rootdir)) {
                continue;
            }

            $result[$name] = $this->normalise_plugin($plugininfo);
        }

        uasort($result, static function(array $a, array $b): int {
            return strcasecmp($a['displayname'], $b['displayname']);
        });

        return $result;
    }

    /**
     * Searches all installed extension plugins.
     *
     * Standard plugins shipped with Moodle are intentionally excluded. The query
     * is matched against the display name, short name, component and plugin type.
     *
     * @param string $query Search term.
     * @return array
     */
    public function search_extensions(string $query): array {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $needle = core_text::strtolower($query);
        $result = [];
        $pluginmanager = core_plugin_manager::instance();

        foreach (core_component::get_plugin_types() as $type => $path) {
            $typename = $pluginmanager->plugintype_name_plural($type);
            $plugins = $pluginmanager->get_plugins_of_type($type);

            foreach ($plugins as $plugininfo) {
                if ($plugininfo->is_standard() || empty($plugininfo->rootdir)) {
                    continue;
                }

                $plugin = $this->normalise_plugin($plugininfo);
                $haystacks = [
                    $plugin['displayname'],
                    $plugin['name'],
                    $plugin['component'],
                    $plugin['type'],
                    $typename,
                ];

                $matches = false;
                foreach ($haystacks as $haystack) {
                    if (strpos(core_text::strtolower((string)$haystack), $needle) !== false) {
                        $matches = true;
                        break;
                    }
                }

                if (!$matches) {
                    continue;
                }

                $plugin['typename'] = $typename;
                $result[] = $plugin;
            }
        }

        usort($result, static function(array $a, array $b): int {
            $comparison = strcasecmp($a['displayname'], $b['displayname']);
            if ($comparison !== 0) {
                return $comparison;
            }
            return strcasecmp($a['component'], $b['component']);
        });

        return $result;
    }

    /**
     * Gets one installed extension plugin by component name.
     *
     * @param string $component Frankenstyle component name.
     * @return array|null
     */
    public function get_extension(string $component): ?array {
        [$type, $name] = core_component::normalize_component($component);
        if ($type === 'core' || $name === null) {
            return null;
        }

        $plugins = core_plugin_manager::instance()->get_plugins_of_type($type);
        if (!isset($plugins[$name])) {
            return null;
        }

        $plugininfo = $plugins[$name];
        if ($plugininfo->is_standard() || empty($plugininfo->rootdir)) {
            return null;
        }

        return $this->normalise_plugin($plugininfo);
    }

    /**
     * Converts Moodle plugin info to view data.
     *
     * @param base $plugininfo Plugin information.
     * @return array
     */
    private function normalise_plugin(base $plugininfo): array {
        return [
            'type' => $plugininfo->type,
            'name' => $plugininfo->name,
            'component' => $plugininfo->component,
            'displayname' => $plugininfo->displayname,
            'rootdir' => $plugininfo->rootdir,
            'release' => (string)($plugininfo->release ?? ''),
            'versiondisk' => (string)($plugininfo->versiondisk ?? ''),
        ];
    }
}
