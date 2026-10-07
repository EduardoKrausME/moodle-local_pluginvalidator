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
 * Moodle component class loading helper.
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pluginvalidator;

/**
 * Resolves Moodle namespaced symbols even when the component class map is stale.
 */
final class moodle_class_loader {
    /**
     * Loads a Moodle namespaced class, interface, trait or enum.
     *
     * Moodle's normal autoloader is always attempted first. If that fails, the
     * component directory is resolved and the conventional classes/... file is
     * required directly before the symbol is considered missing.
     *
     * @param string $symbol Fully-qualified Moodle symbol.
     * @param array|null $diagnostics Optional resolution details.
     * @return bool True when the symbol is available after resolution.
     */
    public static function load_symbol(string $symbol, ?array &$diagnostics = null): bool {
        $symbol = ltrim(trim($symbol), '\\');
        $diagnostics = [
            'symbol' => $symbol,
            'component' => '',
            'componentdir' => '',
            'file' => '',
            'fileexists' => false,
            'autoloaded' => false,
            'directloaded' => false,
        ];

        if ($symbol === '') {
            return false;
        }

        if (self::symbol_exists($symbol, true)) {
            $diagnostics['autoloaded'] = true;
            return true;
        }

        $separator = strpos($symbol, '\\');
        if ($separator === false) {
            // Legacy non-namespaced classes are intentionally left to the caller,
            // which may have a classpath/externallib.php fallback.
            return false;
        }

        $component = substr($symbol, 0, $separator);
        $relativeclass = substr($symbol, $separator + 1);
        $diagnostics['component'] = $component;

        $componentdir = \core_component::get_component_directory($component);
        if (!is_string($componentdir) || $componentdir === '') {
            [$type, $name] = \core_component::normalize_component($component);
            $plugintypes = \core_component::get_plugin_types();
            if ($type !== 'core' && $name !== null && isset($plugintypes[$type])) {
                $candidate = rtrim((string)$plugintypes[$type], DIRECTORY_SEPARATOR)
                    . DIRECTORY_SEPARATOR . $name;
                if (is_dir($candidate)) {
                    $componentdir = $candidate;
                }
            }
        }

        if (!is_string($componentdir) || $componentdir === '') {
            return false;
        }

        $diagnostics['componentdir'] = $componentdir;
        $classfile = rtrim($componentdir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'classes'
            . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeclass)
            . '.php';
        $diagnostics['file'] = $classfile;
        $diagnostics['fileexists'] = is_file($classfile);

        if (!$diagnostics['fileexists']) {
            return false;
        }

        require_once($classfile);
        $diagnostics['directloaded'] = self::symbol_exists($symbol, false);

        return $diagnostics['directloaded'];
    }

    /**
     * Tests whether a PHP symbol exists.
     *
     * @param string $symbol Fully-qualified symbol.
     * @param bool $autoload Whether registered autoloaders may run.
     * @return bool
     */
    private static function symbol_exists(string $symbol, bool $autoload): bool {
        if (class_exists($symbol, $autoload)
                || interface_exists($symbol, $autoload)
                || trait_exists($symbol, $autoload)) {
            return true;
        }

        return function_exists('enum_exists') && enum_exists($symbol, $autoload);
    }
}
