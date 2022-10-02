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

namespace tool_dataflows\local\step;

/**
 * Directory file count
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connector_directory_file_count extends connector_step {

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'path' => ['type' => PARAM_TEXT, 'required' => true],
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
        // Path to check.
        $mform->addElement('text', 'config_path', get_string('connector_directory_file_count:path', 'tool_dataflows'));
    }

    /**
     * Executes the step
     *
     * @param  mixed|null $input
     * @return mixed
     */
    public function execute($input = null) {
        $config = $this->get_resolved_config();

        $path = $this->enginestep->engine->resolve_path($config->path);
        $count = $this->run($path);
        $this->log("Found {$count} files");
        $this->set_variable('result', $count);

        return $input;
    }

    /**
     * Pure function to demonstrate the implementation of this step.
     *
     * @param  string $path
     * @return int count of files in directory
     */
    public function run($path) {
        $exists = file_exists($path);
        $count = 0;
        if ($exists) {
            $iterator = new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS);
            $count = iterator_count($iterator);
        }
        return $count;
    }

    /**
     * A list of outputs and their description if applicable.
     *
     * @return  array of outputs
     */
    public function define_outputs(): array {
        return ['result' => null];
    }
}
