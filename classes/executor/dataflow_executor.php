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

namespace tool_dataflows\executor;

use tool_dataflows\dataflow;
use tool_dataflows\step;

/**
 * Manages the execution of a dataflow
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dataflow_executor {

    const STATUS_NEW = 0;
    const STATUS_INITALISED = 1;
    const STATUS_BLOCKED = 2;
    const STATUS_WAITING = 3;
    const STATUS_PROCESSING = 4;
    const STATUS_FLOWING = 5;
    const STATUS_FINISHED = 6;
    const STATUS_CANCELLED = 7;
    const STATUS_ABORTED = 8;

    /** @var dataflow The dataflow definition */
    protected $dataflow;

    /** @var array The execution steps */
    protected $steps = [];

    /** @var array The pullers, one for each flow block*/
    protected $flowpullers = [];

    /** @var array The steps that have no downstreams */
    protected $sinks = [];

    /** @var int */
    protected $status = self::STATUS_NEW;

    /**
     * Builds the engine from the definition, and initialises it.
     *
     * @param dataflow $dataflow
     */
    public function __construct(dataflow $dataflow) {
        $this->dataflow = $dataflow;

        // Create executors for each step in the dataflow.
        foreach ($dataflow->raw_steps() as $stepdef) {
            $classname = $stepdef->type;
            $steptype = new $classname();
            $stepdef = new step(0, $stepdef);
            if ($steptype->is_flow()) {
                $this->steps[$stepdef->id] = new flow_step_executor($this, $stepdef, $steptype);
            } else {
                $this->steps[$stepdef->id] = new connector_step_executor($this, $stepdef, $steptype);
            }
        }

        // Create the links between step executors.
        foreach ($this->steps as $id => $step) {
            $deps = $step->stepdef->dependencies();
            foreach ($deps as $dep) {
                $depstep = $this->steps[$dep->id];
                $step->upstreams[$dep->id] = new upstream($depstep);
                $depstep->downstreams[$id] = $step;
            }
        }

        // Find the sinks.
        // Find the flow blocks and attach pullers to them.
        // TODO: For now this assumes no forks and no downstream connectors.
        foreach ($this->steps as $step) {
            if (count($step->downstreams) == 0) {
                $this->sinks[] = $step;
                if ($step->is_flow()) {
                    $this->flowpullers[] = new flow_puller($this, [$step]);
                }
            }
        }

        // TODO: This may be separated out into a separate function.
        foreach ($this->steps as $step) {
            $step->initialise();
        }
        $this->status = self::STATUS_INITALISED;
    }

    /**
     * The dataflow status.
     *
     * @return int
     */
    public function status(): int {
        return $this->status;
    }

    /**
     * Start the execution.
     *
     * @throws \moodle_exception
     */
    public function start() {
        if ($this->status != self::STATUS_INITALISED) {
            throw new \moodle_exception('Cannot start a dataflow execution in any state other than initialised');
        }
        $this->status = self::STATUS_PROCESSING;
        $this->sinks[0]->start();
    }

    /**
     * Gracefully aborts the execution. Will stop all iterators, release all resources, and then throws
     * the exception that triggered the abort.
     *
     * @param step_executor $step
     * @param \Throwable $exception
     * @throws \Throwable
     */
    public function abort(step_executor $step, \Throwable $exception) {
        $this->status = self::STATUS_ABORTED;
        foreach ($this->steps as $step) {
            $step->abort();
        }
        throw $exception;
    }
}
