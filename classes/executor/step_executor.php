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

use tool_dataflows\step;
use tool_dataflows\step\base_step;

/**
 * Manages the execution of a step.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class step_executor implements downstream {

    /** @var dataflow_executor  */
    public $dataflow;

    /** @var step  */
    public $stepdef;

    /** @var base_step  */
    public $steptype;

    /** @var array  */
    public $upstreams = [];

    /** @var array  */
    public $downstreams = [];

    /** @var int  */
    public $status = dataflow_executor::STATUS_NEW;

    public function __construct(dataflow_executor $dataflow, step $stepdef, base_step $steptype) {
        $this->dataflow = $dataflow;
        $this->stepdef = $stepdef;
        $this->steptype = $steptype;
    }

    public function get_id(): int {
        return $this->stepdef->id;
    }

    /**
     * True for flow steps, false for connector steps.
     *
     * @return bool
     */
    abstract public function is_flow(): bool;

    /**
     * Initialises teh step.
     */
    public function initialise() {
        $this->status = dataflow_executor::STATUS_INITALISED;
    }

    /**
     * Starts the execution of the step.
     */
    abstract public function start();

    /**
     * Aborts the execution.
     */
    abstract public function abort();
}