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

use MoodlePluginCI\PluginValidate\Finder\FileTokens;
use MoodlePluginCI\PluginValidate\Finder\FinderInterface;
use MoodlePluginCI\PluginValidate\Plugin;
use MoodlePluginCI\PluginValidate\PluginValidate;
use MoodlePluginCI\PluginValidate\Requirements\AbstractRequirements;

/**
 * Captures moodle-plugin-ci validation directly as structured PHP data.
 *
 * The upstream validator normally formats messages for Symfony Console. This
 * adapter intercepts the validation before that presentation layer, so Moodle
 * never needs to execute or parse the moodle-plugin-ci CLI output.
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_plugin_ci_collector extends PluginValidate {
    /** @var Plugin */
    private $sourceplugin;

    /** @var array */
    private $checks = [];

    /**
     * Constructor.
     *
     * @param Plugin $plugin Plugin being validated.
     * @param AbstractRequirements $requirements Moodle plugin requirements.
     */
    public function __construct(Plugin $plugin, AbstractRequirements $requirements) {
        $this->sourceplugin = $plugin;
        parent::__construct($plugin, $requirements);
    }

    /**
     * Returns structured checks.
     *
     * @return array
     */
    public function get_checks(): array {
        return $this->checks;
    }

    /**
     * Captures a generic upstream error.
     *
     * @param string $message Message.
     */
    public function addError(string $message): void {
        $this->add_check('error', $message);
        $this->isValid = false;
    }

    /**
     * Captures a generic upstream success.
     *
     * @param string $message Message.
     */
    public function addSuccess(string $message): void {
        $this->add_check('ok', $message);
    }

    /**
     * Captures a generic upstream warning.
     *
     * @param string $message Message.
     */
    public function addWarning(string $message): void {
        $this->add_check('warning', $message);
    }

    /**
     * Validates required files while preserving the file as structured data.
     *
     * @param array $files Required files.
     */
    public function findRequiredFiles(array $files): void {
        foreach ($files as $file) {
            if (file_exists($this->sourceplugin->directory . '/' . $file)) {
                $this->add_check(
                    'ok',
                    sprintf('Found required file: %s', $file),
                    $file,
                    $file
                );
            } else {
                $this->add_check(
                    'error',
                    sprintf('Failed to find required file: %s', $file),
                    $file,
                    $file
                );
                $this->isValid = false;
            }
        }
    }

    /**
     * Finds required tokens while preserving source file and target information.
     *
     * @param FinderInterface $finder Token finder.
     * @param FileTokens[] $tokenCollection Token definitions.
     */
    public function findRequiredTokens(FinderInterface $finder, array $tokenCollection): void {
        foreach ($tokenCollection as $filetokens) {
            if (!$filetokens->hasTokens()) {
                continue;
            }

            $file = $this->sourceplugin->directory . '/' . $filetokens->file;
            if (!file_exists($file)) {
                $this->add_check(
                    'warning',
                    sprintf('Skipping validation of missing or optional file: %s', $filetokens->file),
                    $filetokens->file
                );
                continue;
            }

            try {
                $finder->findTokens($file, $filetokens);
                $this->addMessagesFromTokens($finder->getType(), $filetokens);
            } catch (\Throwable $e) {
                $this->add_check('error', $e->getMessage(), $filetokens->file);
                $this->isValid = false;
            }
        }
    }

    /**
     * Captures individual token requirements without Symfony Console markup.
     *
     * @param string $type Token type.
     * @param FileTokens $filetokens File token collection.
     */
    public function addMessagesFromTokens(string $type, FileTokens $filetokens): void {
        foreach ($filetokens->tokens as $token) {
            $target = implode(' OR ', $token->tokens);

            if ($token->hasTokenBeenFound()) {
                $this->add_check(
                    'ok',
                    sprintf('In %s, found %s %s', $filetokens->file, $type, $target),
                    $filetokens->file,
                    $target
                );
                continue;
            }

            $message = sprintf(
                'In %s, failed to find %s %s',
                $filetokens->file,
                $type,
                $target
            );
            if ($filetokens->hasHint()) {
                $message .= '. Hint: ' . $filetokens->hint;
            }

            $this->add_check('error', $message, $filetokens->file, $target);
            $this->isValid = false;
        }
    }

    /**
     * Adds one structured validation check.
     *
     * @param string $status ok, warning or error.
     * @param string $message Human-readable message.
     * @param string $file Relative plugin file.
     * @param string $target Requirement target.
     */
    private function add_check(
        string $status,
        string $message,
        string $file = '',
        string $target = ''
    ): void {
        $this->checks[] = [
            'status' => $status,
            'rule' => 'pluginvalidate',
            'file' => $file,
            'line' => 0,
            'key' => '',
            'target' => $target,
            'message' => $message,
            'languageString' => false,
        ];
    }
}
