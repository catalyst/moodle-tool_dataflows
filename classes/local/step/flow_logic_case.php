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

use tool_dataflows\local\execution\engine;
use tool_dataflows\local\execution\flow_engine_step;
use tool_dataflows\local\execution\iterators\dataflow_iterator;
use tool_dataflows\local\execution\iterators\iterator;
use tool_dataflows\parser;

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

            private $cases = [];
            private $stepcasemap = [];

            /**
             * Create an instance of this class.
             *
             * @param  flow_engine_step $step
             * @param  object $config
             * @param  iterator $input
             */
            public function __construct(flow_engine_step $step, iterator $input) {
                // TODO: pull conditions out, and also internally line up each
                // 'case' with the expected step that is asking for the input.
                // To later determine who to provide the input to and who to
                // ignore.

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
             */
            public function next($caller) {
                if ($this->finished) {
                    return false;
                }

                // Pull if needed.
                $value = $this->input->current();
                if (is_null($value) || !empty($this->passed)) {
                    $this->input->next($this);
                    ++$this->iterationcount;
                    $this->step->log('Pulling upstream - ' . $this->iterationcount . ': ' . json_encode($this->input->current()));
                    $this->passed = false;
                }
                $value = $this->input->current();

                $casenumber = $this->stepcasemap[$caller->step->id];
                $case = $this->cases[$casenumber] ?? null;
                if (!$case) {
                    throw new \moodle_exception(get_string('casenotfound', 'tool_dataflows'));
                }

                $parser = new parser;
                $result = (bool) $parser->evaluate_or_fail('${{ ' . $case . ' }}', ['record' => $value]);
                if (!$result) {
                    $this->value = false;
                    return false;
                }

                $this->value = $value;
                $this->passed = true;
                return $value;
            }
        };
    }
}
