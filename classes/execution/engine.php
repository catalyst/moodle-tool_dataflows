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

namespace tool_dataflows\execution;

use tool_dataflows\dataflow;

/**
 * Executes a dataflow
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engine {

    /**
     * Defines the execution status used by the engine and the engine steps.
     */
    /** @var int Just created. */
    const STATUS_NEW = 0;
    /** @var int Initialised. Ready to run. */
    const STATUS_INITIALISED = 1;
    /** @var int Connector cannot proceed, waiting on upstreams. */
    const STATUS_BLOCKED = 2;
    /** @var int Flow cannot proceed, waiting on upstreams. */
    const STATUS_WAITING = 3;
    /** @var int Connector is currently processing. */
    const STATUS_PROCESSING = 4;
    /** @var int Flow is currently active */
    const STATUS_FLOWING = 5;
    /** @var int Step has finished activity. Downstreams can proceed. */
    const STATUS_FINISHED = 6;
    /** @var int Step has been cancelled. Downstreams may also be cancelled. */
    const STATUS_CANCELLED = 7;
    /** @var int The dataflow execution has been aborted. Not able to finish */
    const STATUS_ABORTED = 8;
    /** @var int Step/dataflow has completely finished. */
    const STATUS_FINALISED = 9;

    /** @var  array The queue of steps to be given a run. */
    protected $queue;

    /** @var dataflow The dataflow defined by the user. */
    protected $dataflow;

    /** @var array The engine steps in the dataflow. */
    protected $enginesteps = [];

    /** @var array The steps that have no outputstreams. */
    protected $sinks = [];

    /** @var int The status of the execution. */
    protected $status = self::STATUS_NEW;

    /**
     * Constructs the engine.
     *
     * @param dataflow $dataflow The dataflow to be executed, as defined in the editor.
     */
    function __construct(dataflow $dataflow) {
        $this->dataflow = $dataflow;

       // Create engine steps for each step in the dataflow.
        foreach ($dataflow->steps() as $stepdef) {
            $classname = $stepdef->type;
            $steptype = new $classname();
            $this->enginesteps[$stepdef->id] = $steptype->get_engine_step($this, $stepdef);
        }

        // Create the links between step executors.
        foreach ($this->enginesteps as $id => $enginestep) {
            $deps = $enginestep->stepdef->dependencies();
            foreach ($deps as $dep) {
                $depstep = $this->enginesteps[$dep->id];
                $enginestep->upstreams[$dep->id] = $depstep;
                $depstep->downstreams[$id] = $enginestep;
            }
        }

        // Find the sinks.
        foreach ($this->enginesteps as $enginestep) {
            if (count($enginestep->downstreams) == 0) {
                $this->sinks[] = $enginestep;
            }
        }

        // Find the flow blocks
        $this->create_flow_caps();
    }

    public function initialise() {
        foreach ($this->enginesteps as $enginestep) {
            $enginestep->initialise();
        }

        // Add sinks to the execution queue.
        $this->queue = $this->sinks;
        $this->status = self::STATUS_INITIALISED;
    }

    /**
     * Finds the steps that are sinks for their respective flow blocks and create flow caps for them.
     */
    protected function create_flow_caps() {
    }

    /**
     * Runs the data flow as a single action. This function initialises the dataflow,
     * runs the dataflow, and finalises it.
     */
    public function execute() {
        $this->initialise();
        while ($this->status != self::STATUS_FINISHED) {
            $this->execute_step();
            if ($this->status == self::STATUS_ABORTED) {
                return;
            }
        }
        $this->finalise();
    }

    /**
     * Executes a single step. Must be initialised prior to calling.
     */
    public function execute_step() {
        if ($this->status == self::STATUS_INITIALISED) {
            $this->status = self::STATUS_PROCESSING;
        }
        if ($this->status != self::STATUS_PROCESSING) {
            throw new \moodle_exception("bad_status", "tool_dataflows");
        }
        if (count($this->queue)) {
            $this->status = self::STATUS_FINISHED;
        } else {
            $currentstep = array_shift($this->queue);
            $result = $currentstep->go();

            switch ($result['status']) {
                case self::STATUS_BLOCKED:
                case self::STATUS_WAITING:
                    foreach ($currentstep->upstreams as $upstream) {
                        $this->queue[] = $upstream;
                    }
                    break;
                case self::STATUS_FINISHED:
                case self::STATUS_CANCELLED:
                    foreach ($currentstep->downstreams as $downstream) {
                        $this->queue[] = $downstream;
                    }
                    break;
                case self::STATUS_FLOWING:
                    foreach ($currentstep->downstreams as $downstream) {
                        if ($downstream->is_flow()) {
                            $this->queue[] = $downstream;
                        }
                    }
                    break;
                case self::STATUS_ABORTED:
                    $this->abort();
            }
        }
    }

    /**
     * Finalises the execution. Any remaining resources should be released.
     */
    public function finalise() {
        foreach ($this->enginesteps as $enginestep) {
            $enginestep->finalise();
        }
        $this->status = self::STATUS_FINALISED;
    }

    /**
     * Stops execution immediately. Gracefully stops all processors and iterators.
     */
    public function abort() {
        foreach ($this->enginesteps as $enginestep) {
            $enginestep->abort();
        }
        $this->status = self::STATUS_ABORTED;
    }

    public function __get($p) {
        switch ($p) {
            case 'status':
                return $this->status;
            default:
                throw new \moodle_exception('');
        }
    }
}
