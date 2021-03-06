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

namespace tool_dataflows\form;

use tool_dataflows\dataflow;
use tool_dataflows\manager;

/**
 * Dataflow Form
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dataflow_form extends \core\form\persistent {

    /** @var string Persistent class name. */
    protected static $persistentclass = 'tool_dataflows\dataflow';

    /**
     * Define the form.
     */
    public function definition() {
        $mform = $this->_form;

        // User ID.
        $mform->addElement('hidden', 'userid');
        $mform->setConstant('userid', $this->_customdata['userid']);

        // Name of the dataflow.
        $mform->addElement('text', 'name', get_string('field_name', 'tool_dataflows'));

        // Enable/Disable control.
        $mform->addElement('advcheckbox', 'enabled', get_string('dataflow_enabled', 'tool_dataflows'));
        $mform->setDefault('enabled', 0);

        // Configuration - YAML format.
        $mform->addElement(
            'textarea',
            'config',
            get_string('field_config', 'tool_dataflows'),
            ['cols' => 50, 'rows' => 7]
        );

        $this->add_action_buttons();
    }
}
