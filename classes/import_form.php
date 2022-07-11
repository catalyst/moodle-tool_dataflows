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

namespace tool_dataflows;

/**
 * Import dataflow form class.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_form extends \moodleform {

    /**
     * Build form for importing woekflows.
     *
     * {@inheritDoc}
     * @see \moodleform::definition()
     */
    public function definition() {

        $mform = $this->_form;

        // Workflow file.
        $mform->addElement(
            'filepicker',
            'userfile',
            get_string('dataflow_file', 'tool_dataflows'),
            null,
            ['maxbytes' => 256000, 'accepted_types' => ['.yml', '.yaml', '.txt']]
        );
        $mform->addRule('userfile', get_string('required'), 'required');

        $this->add_action_buttons();
    }

    /**
     * Validate uploaded YAML file.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        global $USER;

        $validationerrors = [];

        // Get the file from the filestystem. $files will always be empty.
        $fs = get_file_storage();

        $context = \context_user::instance($USER->id);
        $itemid = $data['userfile'];

        // This is how core gets files in this case.
        if (!$files = $fs->get_area_files($context->id, 'user', 'draft', $itemid, 'id DESC', false)) {
            $validationerrors['nofile'] = get_string('nodataflowfile', 'tool_dataflows');
            return $validationerrors;
        }
        $file = reset($files);

        // Check if file is valid YAML.
        $content = $file->get_content();
        if (!empty($content)) {
            $parser = new parser;
            $validation = $parser->validate_yaml($content);
            if ($validation !== true) {
                $validationerrors['userfile'] = $validation;
            }
        }

        return $validationerrors;
    }

    /**
     * Get the errors returned during form validation.
     *
     * @return array|mixed
     */
    public function get_errors() {
        $form = $this->_form;
        $errors = $form->_errors;

        return $errors;
    }
}
