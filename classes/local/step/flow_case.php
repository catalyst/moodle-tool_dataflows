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

use tool_dataflows\local\execution\iterators\dataflow_iterator;
use tool_dataflows\local\execution\iterators\iterator;
use tool_dataflows\parser;

/**
 * Flow case
 *
 * @package    tool_dataflows
 * @author     Ghaly Marc-Alexandre <marc-alexandreghaly@catalyst-ca.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow_case extends flow_logic_step {
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
    protected $outputflows = [1, 20];

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    protected static function form_define_fields(): array {
        return [
            'returntype' => ['type' => PARAM_TEXT],
            'equals' => ['type' => PARAM_TEXT],
            'execute' => ['type' => PARAM_TEXT],
            'empty' => ['type' => PARAM_TEXT],
        ];
    }

    /**
     * Allows each step type to determine a list of optional/required form
     * inputs for their configuration
     *
     * It's recommended you prefix the additional config related fields to avoid
     * conflicts with any existing fields.
     *
     * @param \MoodleQuickForm &$mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {
        $mform->addElement('select', 'config_returntype', get_string('flow_case:returntype', 'tool_dataflows'),
            [
                'bool' => get_string('flow_case:bool', 'tool_dataflows'),
                'value' => get_string('flow_case:value', 'tool_dataflows'),
                'empty' => get_string('flow_case:empty', 'tool_dataflows'),
                'exception' => get_string('flow_case:exception', 'tool_dataflows'),
            ]
        );
        $mform->addElement('textarea', 'config_equals', get_string('flow_case:equals', 'tool_dataflows'),
            ['cols' => 50, 'rows' => 7]);
        $mform->addElement('text', 'config_execute', get_string('flow_case:execute', 'tool_dataflows'));
    }

    /**
     * Validate the configuration settings.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        $errors = [];
        if (empty($config->returntype)) {
            $errors['config_returntype'] = get_string('config_field_missing', 'tool_dataflows', 'returntype', true);
        }
        if (empty($config->equals)) {
            $errors['config_equals'] = get_string('config_field_missing', 'tool_dataflows', 'equals', true);
        }
        if (empty($config->execute)) {
            $errors['config_execute'] = get_string('config_field_missing', 'tool_dataflows', 'execute', true);
        }
        return empty($errors) ? true : $errors;
    }

    // /**
    //  * Get the iterator for the step, based on configurations.
    //  *
    //  * @return iterator
    //  */
    public function get_iterator(): iterator {
        $config = $this->enginestep->stepdef->config;
        $upstream = current($this->enginestep->upstreams);
        if ($upstream === false || !$upstream->is_flow()) {
            throw new \moodle_exception(get_string('non_reader_steps_must_have_flow_upstreams', 'tool_dataflows'));
        }
        return new class($this->enginestep, $upstream->iterator, $config) extends dataflow_iterator {
            /** @var string returntype return type we compare. */
            public $returntype;
            /** @var string equals value to compare */
            public $equals;
            /** @var string execute step to execute on ne */
            public $execute;

            public function __construct($step, $input, $config) {
                $this->returntype = $config->returntype;
                $this->equals = $config->equals;
                $this->execute = $config->execute;
                parent::__construct($step, $input);
            }

            /**
             * Next item in the stream.
             *
             * @return object|bool A JSON compatible object, or false if nothing returned.
             */
            public function on_next() {
                $current = $this->input->current();
                $value = $this->value;
                foreach ($this->steptype->enginestep->downstreams as $key => $nextstep) {
                    if ($key !== $stepid) {
                        unset($this->enginestep->downstreams[$key]);
                    }
                }
            }
        };
    }

    /**
     * Executes the step
     *
     * Performs case output.
     *
     * @param object|mixed $input
     * @return bool Returns true if successful, false otherwise.
     */
    public function execute($input) {
        // $config = $this->enginestep->stepdef->config;
        // $steps = $this->stepdef->dataflow->get_steps();
        // $iterator = reset($this->enginestep->upstreams)->iterator;
        // $value = $iterator->newvalue;
        // $stepid = $steps->{$config->execute}->get('id');

        // if ($config->returntype === 'bool') {
        //     if ($value === true) {
        //         $this->select_one_output($stepid);
        //     }
        // }
        // if ($config->returntype === 'empty') {
        //     $parser = new parser();
        //     $value = $parser->array_evaluate_recursive([$config->equals], $input);
        //     if (reset($value) === $config->equals) {
        //         $this->select_one_output($stepid);
        //     }
        // }
        return true;
    }

    protected function select_one_output($stepid) {
        foreach ($this->enginestep->downstreams as $key => $nextstep) {
            if ($key !== $stepid) {
                unset($this->enginestep->downstreams[$key]);
            }
        }
    }
}