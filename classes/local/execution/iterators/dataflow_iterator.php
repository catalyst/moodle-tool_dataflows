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

namespace tool_dataflows\local\execution\iterators;

use tool_dataflows\local\execution\engine;
use tool_dataflows\local\execution\flow_engine_step;

/**
 * A mapping iterator that takes a PHP iterator as a source.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright Catalyst IT, 2022
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dataflow_iterator implements iterator {

    /** @var steptype $steptype */
    protected $steptype;

    /** @var bool $finished */
    protected $finished = false;

    /** @var mixed $input */
    protected $input;

    /** @var flow_engine_step $step */
    protected $step;

    /** @var mixed $value */
    protected $value = null;

    /** @var int $iterationcount */
    protected $iterationcount = 0;

    /**
     * Create an instance of this class.
     *
     * @param flow_engine_step $step The step the iterator is for.
     * @param \Iterator|iterator|moodle_recordset $input An iterator of some sort
     */
    public function __construct(flow_engine_step $step, $input) {
        if (is_null($input)) {
            throw new \moodle_exception('dataflow_iterator:null_input', 'tool_dataflows', '', $step->stepdef->alias);
        }
        $this->step = $step;
        $this->input = $input;
        $this->steptype = $step->steptype;
    }

    /**
     * True if the iterator has no more values to provide.
     *
     * @return bool
     */
    public function is_finished(): bool {
        return $this->finished;
    }

    /**
     * True if the iterator is capable (or allowed) of supplying a value.
     *
     * @return bool
     */
    public function is_ready(): bool {
        return !$this->finished && $this->input->valid();
    }

    /**
     * Terminate the iterator immediately.
     */
    final public function abort() {
        $this->finished = true;
        $this->on_abort();
        $this->step->set_status(engine::STATUS_FINISHED);
    }

    /**
     * Any custom handling for on_abort
     */
    public function on_abort() {
        // Do nothing by default.
    }

    /**
     * Return the current element
     *
     * @return mixed can return any type
     */
    public function current() {
        return $this->value;
    }

    /**
     * Checks if current position is valid
     *
     * @return bool The return value will be casted to boolean and then evaluated. Returns true on success or false on failure.
     */
    public function valid(): bool {
        return $this->input->valid();
    }

    /**
     * This is where you define your custom iterator handling, if needed.
     */
    public function on_next() {
        // Do nothing by default.
    }

    /**
     * Should this iterator pull the next value down?
     *
     * @return  bool
     */
    public function should_pull_next(): bool {
        return !empty($this->pulled)
            || $this->steptype->get_group() !== 'readers';
    }

    /**
     * Next item in the stream.
     *
     * @param   \stdClass $caller The engine step that called this method, internally used to connect outputs.
     * @return  \stdClass|bool A JSON compatible \stdClass, or false if nothing returned.
     */
    public function next($caller) {
        // Record the timestamp of when the step type handling has been entered
        // into. This should be set as early as possible to encapsulate the time
        // it takes.
        $now = microtime(true);
        $this->steptype->set_variables('timeentered', $now);

        if ($this->finished) {
            return false;
        }

        // Do not call this for the initial pull (of data) for a reader.
        if ($this->should_pull_next()) {
            if ($this->input instanceof dataflow_iterator) {
                $this->input->next($this);
            } else {
                $this->input->next();
            }
        }
        $this->pulled = true;

        // Validate if input is valid before grabbing it.
        if (!$this->input->valid()) {
            $this->abort();
            return false;
        }

        // Grabs the current value if valid.
        $this->value = $this->input->current();

        // If the current value is false, it should just fall right through and do nothing.
        if ($this->value === false) {
            return false;
        }

        try {
            // Do the actions defined for the particular step.
            $this->on_next();
            $newvalue = $this->steptype->execute($this->value);

            // Handle step outputs - noting that for flow steps, the values may change between each iteration.
            $this->steptype->prepare_vars();

            ++$this->iterationcount;

            // Expose the number of times this step has been iterated over.
            $this->steptype->set_variables('iterations', $this->iterationcount);

            $this->step->log('Iteration ' . $this->iterationcount . ': ' . json_encode($newvalue));
        } catch (\Throwable $e) {
            $this->step->log($e->getMessage());
            $this->abort();
            throw $e;
        }

        return $newvalue;
    }
}
