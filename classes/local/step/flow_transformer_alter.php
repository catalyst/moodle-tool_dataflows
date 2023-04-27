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

/**
 * Flow alteration (transformer step) class
 *
 * Alter the values being passed down a flow.
 *
 * @package    tool_dataflows
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow_transformer_alter extends flow_transformer_step {

    /** @var int[] number of input flows (min, max). */
    protected $inputflows = [1, 1];

    /** @var int[] number of output flows (min, max). */
    protected $outputflows = [1, 1];

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'expressions' => ['type' => PARAM_TEXT, 'required' => true, 'yaml' => true],
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
        $mform->addElement(
            'textarea',
            'config_expressions',
            get_string('flow_transformer_alter:expressions', 'tool_dataflows'),
            [
                'placeholder' => "field: <expression>\nsome other field: <expression>",
                'cols' => 50,
                'rows' => 5,
            ]
        );
        // Help text for the cases input: Showing a small example, that
        // everything on the right side is an expression by default so does not
        // require the ${{ }}, and lists the current mappings.
        $mform->addElement(
            'static',
            'config_expressions_help',
            '',
            get_string('flow_transformer_alter:expressions_help', 'tool_dataflows')
        );
    }

    /**
     * Apply the filter based on configuration
     *
     * @param  mixed $input
     * @return mixed The new value to be passed on the the next step.
     */
    public function execute($input = null) {
        $variables = $this->get_variables();
        $expressions = (array) $this->stepdef->config->expressions;
        foreach ($expressions as $field => $expr) {
            $input->$field = $variables->evaluate($expr);
        }
        return $input;
    }
}
