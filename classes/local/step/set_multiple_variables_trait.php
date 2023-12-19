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

use tool_dataflows\local\variables\var_root;
use tool_dataflows\local\variables\var_object_visible;

/**
 * Set multiple variables trait
 *
 * Similar to the single approach, except it allows multiple to be set in a
 * single step. This is great for initialising counters, and initial variables
 * that need to be reset every run, but might change during.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2023
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait set_multiple_variables_trait {
    use set_variable_trait;

    /**
     * Executes the step, fetching the config and actioning the step.
     *
     * @param mixed|null $input
     * @return mixed
     */
    public function execute($input = null) {

        $stepvars = $this->get_variables();
        $config = $stepvars->get('config');
        $rootvars = $this->get_variables_root();
        $this->run($rootvars, $config->field, $config->values);

        return $input;
    }

    /**
     * Main handler of this step, split out to make it easier to test.
     *
     * @param var_root $varobject
     * @param string $field
     * @param mixed $values
     */
    public function run(var_root $varobject, string $field, $values) {
        // Do nothing if the value has not changed.
        $currentvalue = $varobject->get($field);
        if ($currentvalue === $values) {
            return;
        }

        // Set the value in the variable tree.
        $varobject->set($field, $values);
        $this->log->info("Set '{field}' as '{values}'", ['field' => $field, 'values' => json_encode($values)]);

        // We do not persist the value if it is a dry run.
        if ($this->is_dry_run()) {
            return;
        }
    }

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'field' => ['type' => PARAM_TEXT, 'required' => true],
            'values' => ['type' => PARAM_TEXT, 'required' => true, 'yaml' => true],
        ];
    }

    /**
     * Custom form inputs
     *
     * @param \MoodleQuickForm $mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {
        $mform->addElement('text', 'config_field', get_string('set_multiple_variables:field', 'tool_dataflows'));
        $mform->addElement('static', 'config_field_help', '', get_string('set_multiple_variables:field_help', 'tool_dataflows'));

        $mform->addElement('textarea', 'config_values', get_string('set_multiple_variables:values', 'tool_dataflows'));
        $mform->addElement('static', 'config_field_help', '', get_string('set_multiple_variables:values_help', 'tool_dataflows'));
    }
}
