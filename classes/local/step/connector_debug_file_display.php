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
use tool_dataflows\local\execution\engine_step;
use tool_dataflows\helper;

/**
 * Dumps the contents of a file to mtrace. Use for debugging.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connector_debug_file_display extends connector_step {

    /**
     * Executes the step
     *
     * This will take the contents of the given stream name, and dump its contents via mtrace.
     *
     * @return bool Returns true if successful, false otherwise.
     */
    public function execute($input = null) {
        $config = $this->enginestep->stepdef->config;

        $streamname = $this->enginestep->engine->resolve_path($config->streamname);
        $content = file_get_contents($streamname);

        mtrace($content);
        return true;
    }

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'streamname' => ['type' => PARAM_TEXT],
        ];
    }

    /**
     * Validate the configuration settings.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        $errors = [];
        if (!isset($config->streamname)) {
            $errors['config_streamname'] = get_string('config_field_missing', 'tool_dataflows', 'streamname', true);
        } else {
            $error = helper::path_validate($config->streamname);
            if ($error !== true) {
                $errors['config_streamname'] = $error;
            }
        }

        return empty($errors) ? true : $errors;
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
        // Stream name.
        $mform->addElement('text', 'config_streamname', get_string('writer_stream:streamname', 'tool_dataflows'), ['size' => '50']);
        $mform->addElement('static', 'config_streamname_help', '', get_string('path_help', 'tool_dataflows').
            \html_writer::nonempty_tag('pre', get_string('path_help_examples', 'tool_dataflows')));
    }
}


