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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

/**
 * Update user using core api
 *
 * @package   tool_dataflows
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright Catalyst IT, 2023
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait update_user_trait {

    /**
     * Returns whether or not the step configured, has a side effect.
     *
     * @return     bool whether or not this step has a side effect
     * @link https://en.wikipedia.org/wiki/Side_effect_(computer_science)
     */
    public function has_side_effect(): bool {
        return true;
    }

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'userid' => ['type' => PARAM_TEXT, 'required' => true],
            'fields' => ['type' => PARAM_TEXT, 'required' => true, 'yaml' => true],
        ];
    }

    /**
     * Custom elements for editing the connector.
     *
     * @param \MoodleQuickForm $mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {
        $mform->addElement('text', 'config_userid', get_string('update_user:userid', 'tool_dataflows'));
        $mform->addElement('static', 'config_userid_help', '', get_string('update_user:userid_help', 'tool_dataflows'));

        $mform->addElement(
            'textarea',
            'config_fields',
            get_string('update_user:fields', 'tool_dataflows'),
            ['cols' => 60, 'rows' => 5]
        );
        $mform->addElement('static', 'config_fields_help', '', get_string('update_user:fields_help', 'tool_dataflows'));
    }

    /**
     * Main execution method which updates the user's details.
     *
     * @param  mixed|null $input
     * @return mixed
     */
    public function execute($input = null) {
        $variables = $this->get_variables();
        $config = $variables->get('config');
        $userobject = (object) array_merge(
            ['id' => $config->userid],
            (array) $config->fields
        );

        // Update user fields using core api.
        \user_update_user($userobject, false, false);
        \profile_save_data($userobject);

        return $input;
    }
}
