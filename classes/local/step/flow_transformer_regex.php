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
 * Flow regex (transformer step) class
 *
 * Alter the values being passed down a flow.
 *
 * @package    tool_dataflows
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  Catalyst IT, 2023
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow_transformer_regex extends flow_transformer_step {

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
            'pattern' => ['type' => PARAM_RAW, 'required' => true],
            'field' => ['type' => PARAM_TEXT, 'required' => true],
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
            'text',
            'config_pattern',
            get_string('flow_transformer_regex:pattern', 'tool_dataflows'),
            [
                'placeholder' => "/[abc]/",
            ]
        );
        $mform->addElement(
            'static',
            'config_pattern_help',
            '',
            get_string('flow_transformer_regex:pattern_help', 'tool_dataflows')
        );
        $mform->addElement(
            'text',
            'config_field',
            get_string('flow_transformer_regex:field', 'tool_dataflows')
        );
        $mform->addElement(
            'static',
            'config_field_help',
            '',
            get_string('flow_transformer_regex:field_help', 'tool_dataflows')
        );
    }

    /**
     * Apply the filter based on configuration
     *
     * @param  mixed $input
     * @return mixed The new value to be passed on to the next step.
     */
    public function execute($input = null) {
        $variables = $this->get_variables();
        $pattern = $this->stepdef->config->pattern;
        $field = $this->stepdef->config->field;
        $haystack = $variables->evaluate($field);
        $matches = [];
        preg_match($pattern, $haystack, $matches);
        // Capture the first matched string as a variable.
        $uniquekey = $variables->get('alias');
        $input->$uniquekey = $matches[0] ?? null;
        return $input;
    }
}
