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

use tool_dataflows\local\execution\flow_engine_step;
use tool_dataflows\local\step\base_step;

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

    /** @var base_step */
    protected $steptype;

    /** @var bool */
    protected $finished = false;

    /** @var mixed */
    protected $input;

    /** @var flow_engine_step */
    protected $step;

    /** @var mixed */
    protected $value = null;

    /** @var int */
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
        $this->stepvars = $this->steptype->get_variables();
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
     * Finishes the iterator. Calls finish for engine step.
     */
    final public function finish() {
        $this->stop();
        $this->step->finish();
    }

    /**
     * Terminate the iterator.
     */
    final public function stop() {
        $this->finished = true;
        $this->on_stop();
    }

    /**
     * Called when the iterator is stopped, either because of finishing ar due to an abort.
     */
    public function on_stop() {
        // Do nothing by default.
    }

    /**
     * Return the current element. If the current element is an object, it will be cloned.
     *
     * @return mixed can return any type
     */
    public function current() {
        return is_object($this->value) ? clone $this->value : $this->value;
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
        return !empty($this->pulled) || !$this->steptype->has_producing_iterator();
    }

    /**
     * Common function to perform startup boilerplate.
     *
     * @return bool If false, do not execute iteration.
     */
    protected function prepare_iteration(): bool {
        // Record the timestamp of when the step type handling has been entered
        // into. This should be set as early as possible to encapsulate the time
        // it takes.
        $now = microtime(true);
        $this->stepvars->set('timeentered', $now);

        // Ensure iterations is always available.
        $this->stepvars->set('iterations', $this->iterationcount);

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

        $this->stepvars->set('record', $this->value);

        return true;
    }

    /**
     * Next item in the stream.
     *
     * @param   \stdClass $caller The engine step that called this method, internally used to connect outputs.
     */
    public function next($caller) {
        if (!$this->prepare_iteration()) {
            $this->step->engine->set_current_step(null);
            return;
        }
        $this->step->engine->set_current_step($this->step);

        try {
            // Do the actions defined for the particular step.
            $this->on_next();
            $newvalue = $this->steptype->execute($this->current());
            if ($this->step->engine->is_aborted()) {
                $this->value = false;
                return;
            }

            // This is to cover if a programmer 'forgets' to have execute() return a value.
            if (!is_null($newvalue)) {
                $this->value = $newvalue;
            } else {
                $this->step->log->info('Step execution failed to return a value. Ignoring.');
            }

            // Log vars for this iteration.
            $this->steptype->log_vars();

            ++$this->iterationcount;

            // Expose the number of times this step has been iterated over.
            $this->stepvars->set('iterations', $this->iterationcount);

            // Log the iteration for real steps.
            $this->step->log->debug('Iteration ' . $this->iterationcount, (array) $newvalue);
        } catch (\Throwable $e) {
            $this->step->log->error($e->getMessage());
            $this->step->engine->abort();
            throw $e;
        }
    }
}
