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
use tool_dataflows\local\execution\engine_step;

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
     * This is autopopulated by the dataflows manager.
     *
     * @var string $component - The component / plugin this step belongs to.
     */
    protected $component = 'tool_dataflows';

    /* Input and Output flow and connectors, default all things to zero */

    /** @var int[] number of input flows (min, max) */
    protected $inputflows = [0, 0];

    /** @var int[] number of output flows (min, max) */
    protected $outputflows = [0, 0];

    /** @var int[] number of input connectors (min, max) */
    protected $inputconnectors = [0, 0];

    /** @var int[] number of output connectors (min, max) */
    protected $outputconnectors = [0, 0];

    /** @var bool whether or not this step type (potentially) contains a side effect or not */
    protected $hassideeffect = false;

    /**
     * Returns whether or not the step configured, has a side effect
     *
     * A side effect if it modifies some state variable value(s) outside its
     * local environment, which is to say if it has any observable effect other
     * than its primary effect of returning a value to the invoker of the
     * operation
     *
     * @return     bool whether or not this step has a side effect
     * @link https://en.wikipedia.org/wiki/Side_effect_(computer_science)
     */
    public function has_side_effect(): bool {
        return $this->hassideeffect;
    }

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
     * Does this type define a flow step?
     *
     * @return bool
     */
    public function is_flow(): bool {
        return false;
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
     * Returns the [min, max] number of input flows
     *
     * @return     int[] of 2 ints, min and max values for the stream
     */
    public function get_number_of_input_flows() {
        return $this->inputflows;
    }

    /**
     * Returns the [min, max] number of output flows
     *
     * @return     int[] of 2 ints, min and max values for the stream
     */
    public function get_number_of_output_flows() {
        return $this->outputflows;
    }

    /**
     * Returns the [min, max] number of input connectors
     *
     * @return     int[] of 2 ints, min and max values for the stream
     */
    public function get_number_of_input_connectors() {
        return $this->inputconnectors;
    }

    /**
     * Returns the [min, max] number of output connectors
     *
     * @return     int[] of 2 ints, min and max values for the stream
     */
    public function get_number_of_output_connectors() {
        return $this->outputconnectors;
    }

    /**
     * Validate the configuration settings.
     * Defaults to no check.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        return true;
    }

    /**
     * Generates an engine step for this type.
     *
     * This should be sufficient for most cases. Override this function if needed.
     *
     * @param engine $engine
     * @param \tool_dataflows\step $stepdef
     * @return engine_step
     */
    abstract public function get_engine_step(engine $engine, \tool_dataflows\step $stepdef): engine_step;

    /**
     * Returns the group (string) this step type is categorised under.
     *
     * @return  string name of the group
     */
    abstract public function get_group(): string;

    /**
     * Returns an array with styles used to draw the dot graph
     *
     * @return  array containing the styles
     * @link    https://graphviz.org/doc/info/attrs.html for a list of available attributes
     */
    public function get_node_styles(): array {
        return [
            'color'     => '#b8c1ca',
            'shape'     => 'record',
            'fillcolor' => '#ced4da',
            'fontsize'  => '10',
            'fontname'  => 'Arial',
        ];
    }
}
