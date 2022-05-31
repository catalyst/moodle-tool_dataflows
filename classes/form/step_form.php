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

        // Description for the step which may include the purpose for its inclusion, more detail about what it does or how it works.
        $mform->addElement(
            'textarea',
            'description',
            get_string('field_description', 'tool_dataflows'),
            ['cols' => 20, 'rows' => 2]
        );

        // Alias for the step (e.g. id-like field of a yaml configured dataflow, if absent, the key for the step).
        $mform->addElement('text', 'alias', get_string('field_alias', 'tool_dataflows'));
        $mform->addElement('static', 'alias_help', '', get_string('field_alias_help', 'tool_dataflows'));

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
            // Example: writer_debugging (tool_dataflows\step\writer_debugging).
            $acc[$classname] = "$basename ($classname)";
            return $acc;
        }, []);
        $mform->addElement('select', 'type', get_string('field_type', 'tool_dataflows'), $steptypes);

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
