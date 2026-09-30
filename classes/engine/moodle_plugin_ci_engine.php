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

use coding_exception;
use curl;
use MoodlePluginCI\PluginValidate\Plugin;
use MoodlePluginCI\PluginValidate\PluginValidate;
use MoodlePluginCI\PluginValidate\Requirements\RequirementsResolver;

/**
 * Moodle HQ moodle-plugin-ci validation engine.
 *
 * This adapter embeds only the upstream validate functionality. It loads the
 * official release PHAR as a PHP library and does not execute its CLI binary.
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_plugin_ci_engine implements validation_engine_interface {
    /** GitHub latest release endpoint. */
    private const RELEASE_API =
        'https://api.github.com/repos/moodlehq/moodle-plugin-ci/releases/latest';

    /** Official release asset. */
    private const ASSET_NAME = 'moodle-plugin-ci.phar';

    /** Directory used to store the downloaded engine. */
    private const ENGINE_DIRECTORY = 'moodle-plugin-ci';

    public function get_id(): string {
        return 'moodle_plugin_ci';
    }

    public function get_name(): string {
        return get_string('engine_moodlepluginci', 'local_pluginvalidator');
    }

    public function get_description(): string {
        return get_string('engine_moodlepluginci_desc', 'local_pluginvalidator');
    }

    public function get_result_format(): string {
        return validation_engine_interface::RESULT_TEXT;
    }

    public function get_status(): array {
        $path = $this->get_engine_path();
        if ($path === null) {
            return [
                'available' => false,
                'version' => '',
                'source' => '',
                'path' => '',
            ];
        }

        $downloaded = realpath($this->get_downloaded_path());
        $active = realpath($path);
        $source = ($downloaded !== false && $downloaded === $active)
            ? get_string('enginesourcedownloaded', 'local_pluginvalidator')
            : get_string('enginesourcebundled', 'local_pluginvalidator');

        return [
            'available' => true,
            'version' => (string)get_config(
                'local_pluginvalidator',
                'engine_' . $this->get_id() . '_version'
            ),
            'source' => $source,
            'path' => $path,
        ];
    }

    public function install_latest(): array {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        $release = $this->get_latest_release();
        $asset = null;

        foreach ($release['assets'] ?? [] as $candidate) {
            if (($candidate['name'] ?? '') === self::ASSET_NAME) {
                $asset = $candidate;
                break;
            }
        }

        if ($asset === null || empty($asset['browser_download_url'])) {
            throw new coding_exception(
                'The latest moodle-plugin-ci release does not contain ' . self::ASSET_NAME . '.'
            );
        }

        $directory = $this->get_engine_directory();
        if (!is_dir($directory) && !mkdir($directory, $CFG->directorypermissions, true) && !is_dir($directory)) {
            throw new coding_exception('Unable to create moodle-plugin-ci engine directory.');
        }

        $target = $this->get_downloaded_path();
        $temporary = $directory . '/.moodle-plugin-ci-' . bin2hex(random_bytes(8)) . '.phar';

        $download = new curl();
        $download->setHeader([
            'User-Agent: Moodle-local_pluginvalidator',
        ]);
        $ok = $download->download_one($asset['browser_download_url'], null, [
            'filepath' => $temporary,
            'timeout' => 300,
            'followlocation' => true,
            'maxredirs' => 5,
        ]);

        if ($ok !== true || !is_readable($temporary) || filesize($temporary) === 0) {
            @unlink($temporary);
            $message = is_string($ok) ? $ok : 'Download failed.';
            throw new coding_exception($message);
        }

        if (!$this->is_engine_path($temporary)) {
            @unlink($temporary);
            throw new coding_exception('Downloaded moodle-plugin-ci PHAR is not a loadable library.');
        }

        $backup = $target . '.previous';
        @unlink($backup);

        if (is_file($target) && !@rename($target, $backup)) {
            @unlink($temporary);
            throw new coding_exception('Unable to replace the current moodle-plugin-ci engine.');
        }

        if (!@rename($temporary, $target)) {
            if (is_file($backup)) {
                @rename($backup, $target);
            }
            @unlink($temporary);
            throw new coding_exception('Unable to activate the downloaded moodle-plugin-ci engine.');
        }

        @unlink($backup);

        $version = (string)($release['tag_name'] ?? '');
        set_config('engine_' . $this->get_id() . '_version', $version, 'local_pluginvalidator');
        set_config('engine_' . $this->get_id() . '_updated', time(), 'local_pluginvalidator');

        return [
            'version' => $version,
        ];
    }

    public function validate(array $plugin): array {
        global $CFG;

        $enginepath = $this->get_engine_path();
        if ($enginepath === null) {
            throw new coding_exception(get_string('enginenotinstalled', 'local_pluginvalidator'));
        }

        try {
            $this->load_library($enginepath);

            $ciplugin = new Plugin(
                $plugin['component'],
                $plugin['type'],
                $plugin['name'],
                $plugin['rootdir']
            );

            $resolver = new RequirementsResolver();
            $requirements = $resolver->resolveRequirements(
                $ciplugin,
                (int)$CFG->branch
            );

            $validator = new PluginValidate($ciplugin, $requirements);
            $validator->verifyRequirements();

            $messages = [];
            foreach ($validator->messages as $message) {
                $messages[] = $this->strip_console_markup((string)$message);
            }

            return [
                'schema' => 1,
                'engine' => $this->get_id(),
                'format' => $this->get_result_format(),
                'component' => $plugin['component'],
                'success' => $validator->isValid,
                'status' => $validator->isValid ? 'ok' : 'error',
                'output' => trim(implode("\n", $messages)),
            ];
        } catch (\Throwable $e) {
            return [
                'schema' => 1,
                'engine' => $this->get_id(),
                'format' => $this->get_result_format(),
                'component' => $plugin['component'],
                'success' => false,
                'status' => 'error',
                'output' => '',
                'runtimeError' => [
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Loads moodle-plugin-ci classes from the official PHAR without running its CLI.
     *
     * @param string $path PHAR path.
     */
    private function load_library(string $path): void {
        if (class_exists(PluginValidate::class)) {
            return;
        }

        $autoload = 'phar://' . $path . '/vendor/autoload.php';
        if (!is_readable($autoload)) {
            throw new coding_exception('Unable to find the moodle-plugin-ci Composer autoloader inside the PHAR.');
        }

        require_once($autoload);

        if (!class_exists(PluginValidate::class)) {
            throw new coding_exception('Unable to load moodle-plugin-ci validation classes from the PHAR.');
        }
    }

    /**
     * Queries latest release metadata.
     *
     * @return array
     */
    private function get_latest_release(): array {
        $curl = new curl();
        $curl->setHeader([
            'Accept: application/vnd.github+json',
            'User-Agent: Moodle-local_pluginvalidator',
            'X-GitHub-Api-Version: 2022-11-28',
        ]);
        $response = $curl->get(self::RELEASE_API, null, ['CURLOPT_TIMEOUT' => 30]);

        if ($curl->get_errno()) {
            throw new coding_exception('Unable to query moodle-plugin-ci releases: ' . $curl->error);
        }

        $release = json_decode($response, true);
        if (!is_array($release)) {
            throw new coding_exception('Invalid moodle-plugin-ci release response.');
        }

        return $release;
    }

    /**
     * Returns active engine path.
     *
     * @return string|null
     */
    private function get_engine_path(): ?string {
        $downloaded = $this->get_downloaded_path();
        if ($this->is_engine_path($downloaded)) {
            return $downloaded;
        }

        $bundled = dirname(__DIR__, 2)
            . '/tools/' . self::ENGINE_DIRECTORY . '/' . self::ASSET_NAME;
        if ($this->is_engine_path($bundled)) {
            return $bundled;
        }

        return null;
    }

    /**
     * Checks whether a PHAR contains the autoloader required by this adapter.
     *
     * @param string $path PHAR path.
     * @return bool
     */
    private function is_engine_path(string $path): bool {
        if (!is_readable($path) || !is_file($path) || filesize($path) === 0) {
            return false;
        }

        try {
            return is_readable('phar://' . $path . '/vendor/autoload.php');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Removes Symfony Console decoration markup while preserving upstream text.
     *
     * @param string $message Console message.
     * @return string
     */
    private function strip_console_markup(string $message): string {
        $clean = preg_replace('/<[^>]+>/', '', $message);
        return trim($clean ?? $message);
    }

    /**
     * Returns engine directory.
     *
     * @return string
     */
    private function get_engine_directory(): string {
        global $CFG;
        return $CFG->dataroot . '/local_pluginvalidator/tools/' . self::ENGINE_DIRECTORY;
    }

    /**
     * Returns downloaded PHAR path.
     *
     * @return string
     */
    private function get_downloaded_path(): string {
        return $this->get_engine_directory() . '/' . self::ASSET_NAME;
    }
}
