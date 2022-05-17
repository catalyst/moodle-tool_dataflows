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
 * Dataflow Step Form
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class step_form extends \core\form\persistent {

    /** @var string Persistent class name. */
    protected static $persistentclass = 'tool_dataflows\step';

    /** @var array Fields to remove from the persistent validation. */
    protected static $foreignfields = ['dependson'];

    /**
     * Define the form.
     */
    public function definition() {
        $mform = $this->_form;
        $dataflowid = $this->_customdata['dataflowid'];

        // User ID.
        $mform->addElement('hidden', 'userid');
        $mform->setConstant('userid', $this->_customdata['userid']);

        // Dataflow Id.
        $mform->addElement('hidden', 'dataflowid');
        $mform->setConstant('dataflowid', $dataflowid);

        // Name of the step.
        $mform->addElement('text', 'name', get_string('field_name', 'tool_dataflows'));

        // Show a list of other steps as options for depends on.
        $dataflow = new dataflow($dataflowid);
        $steps = $dataflow->raw_steps();
        $options = array_reduce($steps, function ($acc, $step) {
            if ((int) $step->id !== $this->get_persistent()->id) {
                $acc[$step->id] = $step->name;
            }
            return $acc;
        }, []);

        $select = $mform->addElement(
            'select',
            'dependson',
            get_string('field_dependson', 'tool_dataflows'),
            $options,
            ['class' => empty($options) ? 'hidden' : ''] // Hidden if there are no options to select from.
        );
        $select->setMultiple(true);

        // Type of the step (should be a FQCN).
        $steptypes = manager::get_steps_types();
        $steptypes = array_reduce($steptypes, function ($acc, $steptype) {
            $classname = get_class($steptype);
            $basename = substr($classname, strrpos($classname, '\\') + 1);
            // For readability, opting to show the name of the type of step first, and FQCN afterwards.
            // Example: debugging (tool_dataflows\step\debugging).
            $acc[$classname] = "$basename ($classname)";
            return $acc;
        }, []);
        $mform->addElement('select', 'type', get_string('field_type', 'tool_dataflows'), $steptypes);

        // Configuration - e.g. as JSON/YML (leaning towards the later).
        $mform->addElement(
            'textarea',
            'config',
            get_string('field_config', 'tool_dataflows'),
            ['cols' => 50, 'rows' => 7]
        );

        $this->add_action_buttons();
    }

}
