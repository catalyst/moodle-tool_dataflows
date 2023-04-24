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
     * Get the iterator for the step, based on configurations.
     *
     * @return  iterator
     */
    public function get_iterator(): iterator {
        $upstream = current($this->enginestep->upstreams);
        if ($upstream === false || !$upstream->is_flow()) {
            throw new \moodle_exception(get_string('non_reader_steps_must_have_flow_upstreams', 'tool_dataflows'));
        }

        /*
         * Iterator class to handle when to pull from upstream based on the condition of the current value.
         */
        return new class($this->enginestep, $upstream->iterator) extends dataflow_iterator {

            protected $expr = true;

            /**
             * Create an instance of this class.
             *
             * @param  flow_engine_step $step
             * @param  iterator $input
             */
            public function __construct(flow_engine_step $step, iterator $input) {
                $this->expr = $step->stepdef->config->filter;
                parent::__construct($step, $input);
            }

            /**
             * Next item in the stream.
             *
             * @param   \stdClass $caller The engine step that called this method, internally used to connect outputs.
             * @return  \stdClass|bool A JSON compatible \stdClass, or false if nothing returned.
             */
            public function next($caller) {
                $now = microtime(true);
                $stepvars = $this->steptype->get_variables();
                $stepvars->set('timeentered', $now);

                if ($this->finished) {
                    return false;
                }

                // Do not call this for the initial pull (of data) for a step that has a producing iterator (e.g. readers).
                if ($this->should_pull_next()) {
                    if ($this->input instanceof dataflow_iterator) {
                        $this->input->next($this);
                    } else {
                        $this->input->next();
                    }
                }
                if ($this->step->engine->is_aborted()) {
                    return false;
                }
                $this->pulled = true;

                // Validate if input is valid before grabbing it.
                if (!$this->input->valid()) {
                    $this->finish();
                    return false;
                }

                // Grabs the current value if valid.
                $this->value = $this->input->current();

                // If the current value is false, it should just fall right through and do nothing.
                if ($this->value === false) {
                    return false;
                }

                $stepvars->set('record', $this->value);

                try {
                    // Evaluate the expression.
                    // If true then it will pass the input on. Otherwise it will pass on false.
                    $result = (bool) $stepvars->evaluate('${{ ' . $this->expr . ' }}');

                    if (!$result) {
                        $this->value = false;
                    }

                    // Log vars for this iteration.
                    $this->steptype->log_vars();

                    ++$this->iterationcount;

                    // Expose the number of times this step has been iterated over.
                    $stepvars->set('iterations', $this->iterationcount);

                    $this->step->log('Iteration ' . $this->iterationcount);
                } catch (\Throwable $e) {
                    $this->step->log($e->getMessage());
                    $this->step->engine->abort();
                    throw $e;
                }

                return $this->value;
            }
        };
    }
}
