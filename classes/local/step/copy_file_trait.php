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
 * Copy file trait
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait copy_file_trait {
    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'from' => ['type' => PARAM_TEXT, 'required' => true],
            'to'   => ['type' => PARAM_TEXT, 'required' => true],
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
            if (!mkdir($todirectory, $CFG->directorypermissions, true)) {
                throw new \moodle_exception('flow_copy_file:mkdir_failed', 'tool_dataflows', '', $todirectory);
            }
        }

        // Attempt to copy the file to the destination.
        // If $to is not a directory and $from exists as a file, then it should not glob anything and copy as-is.
        if (!is_dir($to) && file_exists($from)) {
            $this->copy($from, $to);
            return $input;
        }

        // Otherwise, it is probably multiple files or a file to be renamed, and should be globbed.
        $files = glob($from);
        if (empty($files)) {
            return $input;
        }

        $this->log('Copying ' . count($files) . ' files');
        // Copy all files direct to the endpoint specified, either a direct target or in directory.
        $targetdir = is_dir($to);
        foreach ($files as $file) {
            if (!is_dir($file) && is_readable($file)) {
                $dest = $targetdir
                    ? realpath($to . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($file)
                    : $to;
                $this->copy($file, $dest);
            }
        }
        return $input;
    }

    /**
     * Performs a native copy operation and throws an exception if there is a problem.
     *
     * @param string $from
     * @param string $to
     * @throws \moodle_exception
     */
    private function copy(string $from, string $to) {
        $this->log("Copying $from to $to");
        if (!copy($from, $to)) {
            throw new \moodle_exception(
                'flow_copy_file:copy_failed',
                'tool_dataflows',
                '',
                (object) ['from' => $from, 'to' => $to]
            );
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
