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
 * Saves content to a file
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait file_put_content_trait {
    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'path' => ['type' => PARAM_TEXT, 'required' => true],
            'content' => ['type' => PARAM_TEXT, 'required' => true],
        ];
    }

    /**
     * Custom form inputs
     *
     * @param \MoodleQuickForm $mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {
        $mform->addElement('text', 'config_path', get_string('out_path', 'tool_dataflows'));

        $mform->addElement('static', 'config_source_desc', '',
            get_string('out_path_help', 'tool_dataflows').
            \html_writer::nonempty_tag('pre', get_string('path_help_examples', 'tool_dataflows'))
        );

        $mform->addElement('textarea', 'config_content', get_string('file_put_content:content', 'tool_dataflows'));
        $mform->addElement('static', 'config_content_help', '', get_string('file_put_content:content_help', 'tool_dataflows'));
    }

    /**
     * Validate the configuration settings.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        $errors = [];
        if (empty($config->path)) {
            $errors['config_path'] = get_string(
                'config_field_missing',
                'tool_dataflows',
                get_string('out_path', 'tool_dataflows'),
                true
            );
        }
        if (empty($config->content)) {
            $errors['config_content'] = get_string(
                'config_field_missing',
                'tool_dataflows',
                get_string('file_put_content:content', 'tool_dataflows'),
                true
            );
        }
        return $errors ?: true;
    }

    /**
     * Perform any extra validation that is required only for runs.
     *
     * @return true|array Will return true or an array of errors.
     */
    public function validate_for_run() {
        $config = $this->get_variables()->get('config');

        $errors = [];

        $error = helper::path_validate($config->path);
        if ($error !== true) {
            $errors['config_path'] = $error;
        }

        return $errors ?: true;
    }


    /**
     * Executes the step
     *
     * Performs an SFTP call according to config parameters.
     *
     * @param  mixed|null $input
     * @return mixed
     */
    public function execute($input = null) {
        $stepvars = $this->get_variables();
        $config = $stepvars->get('config');
        $path = $this->enginestep->engine->resolve_path($config->path);

        $this->log("Saving to $path");
        file_put_contents($path, $config->content);
    }
}
