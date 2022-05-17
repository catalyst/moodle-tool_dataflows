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

namespace tool_dataflows\step;

use tool_dataflows\executor\dataflow;
use tool_dataflows\executor\iterators\iterator;

/**
 * Base class for steps
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_step {

    /**
     * @var string $component - The component / plugin this step belongs to.
     *
     * This is autopopulated by the dataflows manager.
     */
    protected $component = 'tool_dataflows';


    /** @var int[] number of input streams (min, max) */
    protected $inputstreams = [0, 1];

    /** @var int[] number of output streams (min, max) */
    protected $outputstreams = [0, 1];

    /**
     * Get the frankenstyle component name
     *
     * @return string
     */
    public function get_component(): string {
        return $this->component;
    }

    /**
     * Get the frankenstyle component name
     *
     * @param string $component name
     */
    public function set_component(string $component) {
        $this->component = $component;
    }

    /**
     * Get the step's id
     *
     * This defaults to the base name of the class which is ok in the most
     * cases but if you have a step which can have multiple instances then
     * you should override this to be unique.
     *
     * @return string must be unique within a component
     */
    public function get_id(): string {
        $class = get_class($this);
        $id = explode("\\", $class);
        return end($id);
    }

    /**
     * Get the step reference
     *
     * @return string must be globally unique
     */
    public function get_ref(): string {
        $ref = $this->get_component();
        if (!empty($ref)) {
            $ref .= '_';
        }
        $ref .= $this->get_id();
        return $ref;
    }

    /**
     * Get the short step name
     *
     * @return string
     */
    public function get_name(): string {
        $id = $this->get_id();
        return get_string("step{$id}", $this->get_component());
    }

    /**
     * returns true if this is a flow step type. Override for any connector steps.
     * TODO: Perhaps have two sub classes - flow_step and connector_step.
     *
     * @return bool
     */
    public function is_flow(): bool {
        return true;
    }

    /*
     * Execution functions
     *
     * Functions that will be used by the execution engine.
     */

    /**
     * Once all lights are green, this will be called to construct an iterator.
     */
    // TODO: I anticipate this will be subject to a lot of refactoring as steps get defined and fleshed out.
    abstract public function get_iterator(\tool_dataflows\executor\step $step): iterator;

    /**
     * Step callback handler
     *
     * Implementation can vary, this might be a transformer, resource, or
     * something else.
     */

    abstract public function execute($input);
}
