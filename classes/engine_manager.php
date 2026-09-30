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

use curl;
use coding_exception;

/**
 * Manages the moodle-plugin-validate library used by the validator.
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engine_manager {
    /** GitHub latest release endpoint. */
    private const RELEASE_API = 'https://api.github.com/repos/EduardoKrausME/moodle-plugin-validate/releases/latest';

    /** Directory used to store the downloaded validator. */
    private const ENGINE_DIRECTORY = 'moodle-plugin-validate';

    /**
     * Returns the active validation library root.
     *
     * Downloaded engine has precedence over a bundled fallback.
     *
     * @return string|null
     */
    public function get_engine_root(): ?string {
        $downloaded = $this->get_engine_directory();
        if ($this->is_engine_root($downloaded)) {
            return $downloaded;
        }

        $bundled = dirname(__DIR__, 2) . '/tools/' . self::ENGINE_DIRECTORY;
        if ($this->is_engine_root($bundled)) {
            return $bundled;
        }

        return null;
    }

    /**
     * Returns information about the active validation engine.
     *
     * @return array
     */
    public function get_status(): array {
        $root = $this->get_engine_root();
        if ($root === null) {
            return [
                'available' => false,
                'version' => '',
                'source' => '',
                'path' => '',
            ];
        }

        $downloaded = realpath($this->get_engine_directory());
        $active = realpath($root);
        $source = ($downloaded !== false && $downloaded === $active)
            ? get_string('enginesourcedownloaded', 'local_pluginvalidator')
            : get_string('enginesourcebundled', 'local_pluginvalidator');

        return [
            'available' => true,
            'version' => (string)get_config('local_pluginvalidator', 'engineversion'),
            'source' => $source,
            'path' => $root,
        ];
    }

    /**
     * Downloads and installs the latest moodle-plugin-validate release.
     *
     * The release is extracted into Moodle data and loaded directly as a PHP
     * library through its autoloader. No CLI executable is required by Moodle.
     *
     * @return array Release metadata.
     */
    public function install_latest(): array {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->libdir . '/filestorage/file_storage.php');

        $curl = new curl();
        $curl->setHeader([
            'Accept: application/vnd.github+json',
            'User-Agent: Moodle-local_pluginvalidator',
            'X-GitHub-Api-Version: 2022-11-28',
        ]);
        $response = $curl->get(self::RELEASE_API, null, ['CURLOPT_TIMEOUT' => 30]);
        if ($curl->get_errno()) {
            throw new coding_exception('Unable to query moodle-plugin-validate releases: ' . $curl->error);
        }

        $release = json_decode($response, true);
        if (!is_array($release) || empty($release['tag_name']) || empty($release['zipball_url'])) {
            throw new coding_exception('Invalid moodle-plugin-validate release response.');
        }

        $directory = dirname($this->get_engine_directory());
        if (!is_dir($directory) && !mkdir($directory, $CFG->directorypermissions, true) && !is_dir($directory)) {
            throw new coding_exception('Unable to create validation engine directory.');
        }

        $suffix = bin2hex(random_bytes(8));
        $archive = $directory . '/.' . self::ENGINE_DIRECTORY . '-' . $suffix . '.zip';
        $extractdirectory = $directory . '/.' . self::ENGINE_DIRECTORY . '-' . $suffix;
        @unlink($archive);
        if (is_dir($extractdirectory)) {
            remove_dir($extractdirectory);
        }

        $download = new curl();
        $download->setHeader([
            'Accept: application/vnd.github+json',
            'User-Agent: Moodle-local_pluginvalidator',
            'X-GitHub-Api-Version: 2022-11-28',
        ]);
        $ok = $download->download_one($release['zipball_url'], null, [
            'filepath' => $archive,
            'timeout' => 300,
            'followlocation' => true,
            'maxredirs' => 5,
        ]);
        if ($ok !== true || !is_readable($archive)) {
            @unlink($archive);
            $message = is_string($ok) ? $ok : 'Download failed.';
            throw new coding_exception($message);
        }

        if (!mkdir($extractdirectory, $CFG->directorypermissions, true) && !is_dir($extractdirectory)) {
            @unlink($archive);
            throw new coding_exception('Unable to create temporary validation engine directory.');
        }

        $packer = get_file_packer('application/zip');
        $files = $packer->extract_to_pathname($archive, $extractdirectory);
        if (!$files) {
            @unlink($archive);
            remove_dir($extractdirectory);
            throw new coding_exception('Unable to extract the moodle-plugin-validate release.');
        }

        $sourceroot = $this->find_extracted_root($extractdirectory);
        if ($sourceroot === null) {
            @unlink($archive);
            remove_dir($extractdirectory);
            throw new coding_exception('The moodle-plugin-validate library was not found in the downloaded release.');
        }

        $target = $this->get_engine_directory();
        $backup = $directory . '/.' . self::ENGINE_DIRECTORY . '-previous';
        if (is_dir($backup)) {
            remove_dir($backup);
        }

        if (is_dir($target) && !@rename($target, $backup)) {
            @unlink($archive);
            remove_dir($extractdirectory);
            throw new coding_exception('Unable to replace the current validation engine.');
        }

        if (!@rename($sourceroot, $target)) {
            if (is_dir($backup)) {
                @rename($backup, $target);
            }
            @unlink($archive);
            remove_dir($extractdirectory);
            throw new coding_exception('Unable to activate the downloaded validation engine.');
        }

        if (is_dir($backup)) {
            remove_dir($backup);
        }
        if (is_dir($extractdirectory)) {
            remove_dir($extractdirectory);
        }
        @unlink($archive);

        set_config('engineversion', $release['tag_name'], 'local_pluginvalidator');
        set_config('engineupdated', time(), 'local_pluginvalidator');
        unset_config('enginedigest', 'local_pluginvalidator');

        return [
            'version' => $release['tag_name'],
        ];
    }

    /**
     * Finds the validator project root inside an extracted GitHub archive.
     *
     * @param string $directory Extracted archive directory.
     * @return string|null
     */
    private function find_extracted_root(string $directory): ?string {
        if ($this->is_engine_root($directory)) {
            return $directory;
        }

        $entries = glob($directory . '/*', GLOB_ONLYDIR);
        if ($entries === false) {
            return null;
        }

        foreach ($entries as $entry) {
            if ($this->is_engine_root($entry)) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Checks whether a directory is a loadable moodle-plugin-validate library root.
     *
     * @param string $directory Candidate engine root.
     * @return bool
     */
    private function is_engine_root(string $directory): bool {
        return is_readable($directory . '/autoload.php')
            && is_readable($directory . '/src/Validator.php');
    }

    /**
     * Returns downloaded engine directory in Moodle data.
     *
     * @return string
     */
    private function get_engine_directory(): string {
        global $CFG;
        return $CFG->dataroot . '/local_pluginvalidator/tools/' . self::ENGINE_DIRECTORY;
    }
}
