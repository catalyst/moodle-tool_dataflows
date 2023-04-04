<?php
// This file is part of Moodle - https://moodle.org/
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

namespace tool_dataflows\local\step;

use tool_dataflows\helper;

/**
 * Trait for copying files.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait copy_file_trait {
    /**
     * Returns whether or not the step configured, has a side effect.
     *
     * @return     bool whether or not this step has a side effect
     * @link https://en.wikipedia.org/wiki/Side_effect_(computer_science)
     */
    public function has_side_effect(): bool {
        if (isset($this->stepdef)) {
            $config = $this->stepdef->config;
            return !helper::path_is_relative($config->to);
        }
        return true;
    }

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'from' => ['type' => PARAM_TEXT, 'required' => true],
            'to'   => ['type' => PARAM_TEXT, 'required' => true],
            'append'   => ['type' => PARAM_BOOL, 'required' => false],
        ];
    }

    /**
     * Allows each step type to determine a list of optional/required form
     * inputs for their configuration
     *
     * It's recommended you prefix the additional config related fields to avoid
     * conflicts with any existing fields.
     *
     * @param \MoodleQuickForm $mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {
        // From / Source path.
        $mform->addElement('text', 'config_from', get_string('flow_copy_file:from', 'tool_dataflows'));
        // To / Target path.
        $mform->addElement('text', 'config_to', get_string('flow_copy_file:to', 'tool_dataflows'));
        $mform->addElement('advcheckbox', 'config_append', get_string('flow_copy_file:append', 'tool_dataflows'));
    }

    /**
     * Executes the step and copies what is in $from, to the $to path
     *
     * @param  mixed|null $input
     * @return mixed
     */
    public function execute($input = null) {
        global $CFG;

        $config = $this->get_variables()->get('config');
        $from = $this->enginestep->engine->resolve_path($config->from);
        $to = $this->enginestep->engine->resolve_path($config->to);

        // Create directory if it doesn't already exist - recursively.
        $todirectory = dirname($to);
        if (!file_exists($todirectory)) {
            $this->log("Creating a directory at {$todirectory}");
            mkdir($todirectory, $CFG->directorypermissions, true);
        }

        // Glob files to allow wildcards.
        $files = glob($from);
        if (empty($files)) {
            return $input;
        }

        $this->log('Copying ' . count($files) . ' files');
        foreach ($files as $file) {
            if (!is_dir($file) && is_readable($file)) {
                if (is_dir($to)) {
                    $to .= DIRECTORY_SEPARATOR . basename($file);
                }

                $this->copy($file, $to, $config);
            }
        }
        return $input;
    }

    /**
     * Performs a native copy operation and throws an exception if there is a problem.
     *
     * @param string $from
     * @param string $to
     * @param object $config
     * @throws \moodle_exception
     */
    private function copy(string $from, string $to, object $config) {
        $this->log("Copying $from to $to");
        $flags = 0;
        if ($config->append) {
            $flags = FILE_APPEND;
        }
        if ($this->is_dry_run() && $this->has_side_effect()) {
            return;
        }
        $handle = fopen($from, 'r');
        if (file_put_contents($to, $handle,  $flags) === false) {
            throw new \moodle_exception('flow_copy_file:copy_failed', 'tool_dataflows', (object) [
                'from' => $from,
                'to' => $to,
            ]);
        }
    }

    /**
     * Perform any extra validation that is required only for runs.
     *
     * @return true|array Will return true or an array of errors.
     */
    public function validate_for_run() {
        $config = $this->get_variables()->get('config');

        $errors = [];

        $paths = ['from', 'to'];
        foreach ($paths as $key) {
            $error = helper::path_validate($config->$key);
            if ($error !== true) {
                $errors["config_{$key}"] = $error;
            }
        }

        return empty($errors) ? true : $errors;
    }
}
