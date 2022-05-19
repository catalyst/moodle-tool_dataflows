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

use tool_dataflows\step\base_step;
use tool_dataflows\step;

/**
 * Manages the execution of a step
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class engine_step {

    /** @var engine Dataflow_execution engine  */
    protected $engine;

    /** @var int ID as supplied by the step definition */
    protected $id;

    /** @var step Step definition provided by the editor */
    protected $stepdef;

    /** @var base_step The step type definition. */
    protected $steptype;

    /** @var array  The upstream steps. */
    public $upstreams = [];

    /** @var array  The downstream steps. */
    public $downstreams = [];

    /** @var int The step's current status */
    protected $status = engine::STATUS_NEW;

    public function __construct(engine $engine, step $stepdef, base_step $steptype) {
        $this->engine = $engine;
        $this->stepdef = $stepdef;
        $this->steptype = $steptype;
        $this->id = $stepdef->id;
    }

    /**
     * Initialises the step.
     */
    public function initialise() {
        $this->status = engine::STATUS_INITIALISED;
    }

    /**
     * Finalises the step.
     */
    public function finalise() {
        $this->status = engine::STATUS_FINALISED;
    }

    /**
     * Exposes parameters that need to be accessed.
     *
     * @param $parameter
     * @return mixed
     * @throws \moodle_exception
     */
    public function __get($parameter) {
        switch ($parameter) {
            case 'id':
            case 'status':
            case 'stepdef':
                return $this->$parameter;
            case 'name':
                return $this->stepdef->name;
            default:
                throw new \moodle_exception('Parameter ' . $parameter . ' not defined.');
        }
    }

}
