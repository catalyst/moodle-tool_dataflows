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

use tool_dataflows\local\execution\flow_engine_step;
use tool_dataflows\local\execution\iterators\dataflow_iterator;
use tool_dataflows\local\execution\iterators\iterator;

/**
 * Flow filter (transformer step) class
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow_transformer_filter extends flow_transformer_step {

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
            'filter' => ['type' => PARAM_TEXT, 'required' => true],
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
            'config_filter',
            get_string('flow_transformer_filter:filter', 'tool_dataflows')
        );
        $mform->addElement(
            'static',
            'config_cases_help',
            '',
            get_string('flow_transformer_filter:filter_help', 'tool_dataflows')
        );
    }

    /**
     * Execute the step.
     *
     * Tests an expression. If the expression evaluates to 'true', the input will be returned. Otherwise 'false' will be returned.
     *
     * @param mixed|null $input
     * @return mixed
     */
    public function execute($input = null) {
        $expr = $this->stepdef->config->filter;
        $result = (bool) $this->get_variables()->evaluate('${{ ' . $expr . ' }}');
        if (!$result) {
            return false;
        };
        return $input;
    }
}
