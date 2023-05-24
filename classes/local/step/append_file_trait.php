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
 * Trait for appending files. Similar to copying but will not overwrite existing files. If a
 * destination file doesn't exist, it will be created.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait append_file_trait {
    /**
     * Returns whether or not the step configured, has a side effect.
     *
     * @return     bool whether or not this step has a side effect
     * @link https://en.wikipedia.org/wiki/Side_effect_(computer_science)
     */
    public function has_side_effect(): bool {
        if (isset($this->stepdef)) {
            $config = $this->get_variables()->get('config');
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
            'chopfirstline' => ['type' => PARAM_BOOL],
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
        // Strip first line?
        $mform->addElement('checkbox', 'config_chopfirstline', get_string('flow_append_file:chopfirstline', 'tool_dataflows'));
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

        if (is_dir($from) || !is_readable($from)) {
            throw new \moodle_exception('trait_append_file:cannot_read_file', 'tool_dataflows', null, $from);
        }

        if (is_dir($to)) {
            $to .= DIRECTORY_SEPARATOR . basename($from);
        }

        if ($this->is_dry_run() && $this->has_side_effect()) {
            return $input;
        }

        $this->log("Appending $from to $to");

        $this->run($from, $to);
        return $input;
    }

    /**
     * Do the append.
     *
     * @param string $from
     * @param string $to
     * @throws \moodle_exception
     */
    protected function run(string $from, string $to) {
        $variables = $this->get_variables();
        $config = $variables->get('config');

        // If not chopping lines, then we can simply copy.
        if (empty($config->chopfirstline) || !file_exists($to) || !filesize($to) === 0) {
            $handle = fopen($from, 'r');
            if (file_put_contents($to, $handle,  FILE_APPEND) === false) {
                throw new \moodle_exception('flow_copy_file:copy_failed', 'tool_dataflows', (object) [
                    'from' => $from,
                    'to' => $to,
                ]);
            }
            return;
        }

        // We need to strip out the first line.
        $contents = file_get_contents($from);
        $contents = preg_split('/\R/', $contents, 2);
        if (isset($contents[1])) {
            if (file_put_contents($to, $contents[1],  FILE_APPEND) === false) {
                throw new \moodle_exception('flow_copy_file:copy_failed', 'tool_dataflows', (object) [
                    'from' => $from,
                    'to' => $to,
                ]);
            }
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
