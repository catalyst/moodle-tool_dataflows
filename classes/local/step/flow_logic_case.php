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
 * Flow logic: case
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow_logic_case extends flow_logic_step {

    /**
     * For a join step, it should have 2 or more inputs and for now, up to 20
     * possible input flows.
     *
     * @var int[] number of input flows (min, max)
     */
    protected $inputflows = [1, 1];

    /**
     * For a join step, there should be exactly one output. This is because
     * without at least one output, there is no need to perform a join.
     *
     * @var int[] number of output flows (min, max)
     */
    protected $outputflows = [2, 20];

    /**
     * Returns a list of labels available for a given step
     *
     * By default, this would be the position / order of each connected output
     * (and show as a number). Each case can however based on its own
     * configuration handling, determine the label it chooses to set and display
     * for the output connection. This will only be used and called if there are
     * more than one expected output.
     *
     * @return  array of labels defined for this step type
     */
    public function get_output_labels(): array {
        // Based on configuration, the list of outputs, depends on the list of expressions defined.
        return [
            'even',
            'odd',
            'simple',
            'complex',
            'chaos',
            'disorder',
        ];
    }

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'cases' => ['type' => PARAM_TEXT, 'required' => true, 'yaml' => true],
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
        // TODO: Fix this number.
        $maxoutputs = 20;
        $mform->addElement(
            'textarea',
            'config_cases',
            get_string('flow_logic_case:cases', 'tool_dataflows'),
            ['cols' => 50, 'rows' => $maxoutputs, 'placeholder' => "label: <expression>\nsome other label: <expression>"]
        );
        // Help text for the cases input: Showing a small example, that
        // everything on the right side is an expression by default so does not
        // require the ${{ }}, and lists the current mappings.
        // TODO: Implement.
    }
}
