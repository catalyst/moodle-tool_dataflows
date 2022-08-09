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

/**
 * Flow logic: switch
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dataflows\local\step;

use tool_dataflows\local\execution\flow_engine_step;
use tool_dataflows\local\execution\iterators\dataflow_iterator;
use tool_dataflows\local\execution\iterators\iterator;
use tool_dataflows\parser;

/**
 * Flow logic: switch
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow_logic_switch extends flow_logic_step {

    /**
     * For this step, it should have a maximum of 1 input flow.
     *
     * @var int[] number of input flows (min, max)
     */
    protected $inputflows = [1, 1];

    /**
     * For this step, it should have 2 or more inputs and for now, up to 20.
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
        $cases = $this->stepdef->config->cases ?? null;
        if (!$cases) {
            return [];
        }

        // Currently since the positions start at 1, it will need to be set as such accordingly.
        // Depending on DX this may change.
        $labels = [];
        $position = 1;
        foreach ($cases as $key => $unused) {
            $labels[$position++] = $key;
        }
        return $labels;
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
        [, $maxoutputs] = $this->outputflows;
        $mform->addElement(
            'textarea',
            'config_cases',
            get_string('flow_logic_switch:cases', 'tool_dataflows'),
            [
                'placeholder' => "label: <expression>\nsome other label: <expression>\ndefault: 1",
                'cols' => 50,
                // Maxoutputs is too big for normal user. In most cases it will
                // be 3-5, but if there is N expressions saved then it should be
                // N+2 so always room for more, up to the maximum (e.g. 20).
                'rows' => min($maxoutputs, count($this->get_output_labels()) + 2),
            ]
        );
        // Help text for the cases input: Showing a small example, that
        // everything on the right side is an expression by default so does not
        // require the ${{ }}, and lists the current mappings.
        $mform->addElement('static', 'config_cases_help', '', get_string('flow_logic_switch:cases_help', 'tool_dataflows'));
    }

    /**
     * Get the iterator for the step, based on configurations.
     *
     * @return  data_iterator
     */
    public function get_iterator(): iterator {
        // There should only be one upstream for a case step.
        $upstream = current($this->enginestep->upstreams);
        if ($upstream === false || !$upstream->is_flow()) {
            throw new \moodle_exception(get_string('non_reader_steps_must_have_flow_upstreams', 'tool_dataflows'));
        }

        /*
         * Iterator class to handle when to pull from upstream based on the condition of the current value.
         */
        return new class($this->enginestep, $upstream->iterator) extends dataflow_iterator {

            /** @var array of different cases */
            private $cases = [];

            /** @var array mapping of step to cases, [step.id => case index] */
            private $stepcasemap = [];

            /** @var bool whether or not the case for the current iterator has passed */
            private $passed = false;

            /**
             * Create an instance of this class.
             *
             * @param  flow_engine_step $step
             * @param  iterator $input
             */
            public function __construct(flow_engine_step $step, iterator $input) {
                // Prepare and map output number, with expected step.id.
                $cases = array_values((array) $step->stepdef->config->cases);
                $this->cases = $cases;

                $dependants = $step->stepdef->dependants();
                $this->stepcasemap = array_reduce($dependants, function ($acc, $step) {
                    // Maps the case index to the relevant output.
                    $acc[$step->id] = $step->position - 1;
                    return $acc;
                }, []);

                parent::__construct($step, $input);
            }

            /**
             * Override the default handling of next
             *
             * In particular, only do the default 'next' if there is no current
             * value from the iterator, or if the case 'matches'.
             *
             * For all other cases, return false|null which indicates no value to pull from at this stage.
             *
             * @param \stdClass $caller
             */
            public function next($caller) {
                if ($this->finished) {
                    return false;
                }

                // Pull the next record if needed.
                $value = $this->input->current();
                if (is_null($value) || !empty($this->passed)) {
                    $this->input->next($this);
                    ++$this->iterationcount;
                    $this->passed = false;
                }
                $value = $this->input->current();

                // If the current value is false, it should just fall right through and do nothing.
                if ($value === false) {
                    $this->value = false;
                    return false;
                }

                try {
                    $casenumber = $this->stepcasemap[$caller->step->id];
                    $position = $casenumber + 1;
                    $case = $this->cases[$casenumber] ?? null;
                    if (!isset($case)) {
                        throw new \moodle_exception(get_string('flow_logic_switch:casenotfound', 'tool_dataflows', $casenumber));
                    }

                    // Prepare variables for expression parsing.
                    $variables = $caller->step->engine->get_variables();
                    $variables['record'] = $value;

                    // By default, this step should go through the list of
                    // expressions, in order, and stop at the first matching case.
                    // It should also stop at the first failing case that matches
                    // the position of the step that is 'pulling' on this one for
                    // efficiency.
                    // See issue #347 for more details.
                    $parser = new parser;
                    $casefailures = 0;
                    foreach ($this->cases as $caseindex => $case) {
                        $result = (bool) $parser->evaluate_or_fail('${{ ' . $case . ' }}', $variables);

                        // If there was a passing expression, break the loop.
                        if ($result === true) {
                            // We know it passed, but did it pass on the correct step output connection?
                            $result = $caseindex === $casenumber;
                            break;
                        } else {
                            $casefailures++;
                            // Check if all cases have failed, if so pull next record.
                            if ($casefailures === count($this->cases)) {
                                // Log details for when a no cases matches.
                                $this->step->log(get_string('flow_logic_switch:nomatchingcases', 'tool_dataflows'));
                                $this->input->next($this);
                                break;
                            }
                        }

                        // If this is on the same index as the case, break the loop.
                        if ($caseindex === $casenumber) {
                            break;
                        }
                    }

                    // If the matching failed, do not pass the iterator value downstream.
                    if (!$result) {
                        $this->value = false;
                        return false;
                    }

                    // Log details for when a case matches.
                    $output = sprintf(
                        'Matching case "%s" (position #%d) with expression: %s',
                        $this->steptype->get_output_label($position),
                        $position,
                        $case
                    );
                    $this->step->log($output);
                } catch (\Throwable $e) {
                    $this->step->log($e->getMessage());
                    throw $e;
                }

                $this->value = $value;
                $this->passed = true;
                return $value;
            }
        };
    }
}
