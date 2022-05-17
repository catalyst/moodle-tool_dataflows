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

/**
 * Manages the execution of a dataflow
 *
 * @package   <insert>
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class dataflow {

    const STATUS_NEW = 0;
    const STATUS_INITALISED = 1;
    const STATUS_BLOCKED = 2;
    const STATUS_WAITING = 3;
    const STATUS_PROCESSING = 4;
    const STATUS_FLOWING = 5;
    const STATUS_FINISHED = 6;
    const STATUS_CANCELLED = 7;
    const STATUS_ABORTED = 8;

    /** @var \tool_dataflows\dataflow The dataflow definition */
    protected $dataflow;

    /** @var array The execution steps */
    protected $steps = [];

    protected $flowpullers = [];

    protected $endpoints = [];

    protected $status = self::STATUS_NEW;

    public function __construct(\tool_dataflows\dataflow $dataflow) {
        $this->dataflow = $dataflow;

        foreach ($dataflow->raw_steps() as $stepdef) {
            $classname = $stepdef->type;
            $steptype = new $classname();
            $stepdef = new \tool_dataflows\step(0, $stepdef);
            if ($steptype->is_flow()) {
                $this->steps[$stepdef->id] = new flow_step($this, $stepdef, $steptype);
            } else {
                $this->steps[$stepdef->id] = new connector_step($this, $stepdef, $steptype);
            }
        }

        // Create the links between steps
        foreach ($this->steps as $id => $step) {
            $deps = $step->stepdef->dependencies();
            foreach ($deps as $dep) {
                $depstep = $this->steps[$dep->id];
                $step->upstreams[$dep->id] = new upstream($depstep);
                $depstep->downstreams[$id] = $step;
            }
        }

        // Find the endpoints.
        // Find the flow blocks and attach pullers to them.
        // TODO: For now this assumes no forks and no downstream connectors.
        foreach ($this->steps as $step) {
            if (count($step->downstreams) == 0) {
                $this->endpoints[] = $step;
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

    public function status(): int {
        return $this->status;
    }

    public function start() {
        if ($this->status != self::STATUS_INITALISED) {
            throw new \moodle_exception('Cannot start a dataflow execution in any state other than initialised');
        }
        $this->status = self::STATUS_PROCESSING;
        $this->endpoints[0]->start();
    }

    public function abort(step $step, \Throwable $exception) {
        $this->status = self::STATUS_ABORTED;
        foreach ($this->steps as $step) {
            $step->abort();
        }
        throw $exception;
    }
}
