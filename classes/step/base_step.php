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

use Symfony\Component\Yaml\Yaml;
use tool_dataflows\execution\engine;
use tool_dataflows\execution\engine_step;
use tool_dataflows\execution\iterators\iterator;
use tool_dataflows\execution\iterators\map_iterator;
use tool_dataflows\execution\flow_engine_step;

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
     * Does this type define a flow step?
     * @return bool
     */
    abstract public function is_flow(): bool;

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
     * Returns the [min, max] number of input streams
     *
     * @return     int[] of 2 ints, min and max values for the stream
     */
    public function get_number_of_input_streams() {
        return $this->inputstreams;
    }

    /**
     * Returns the [min, max] number of output streams
     *
     * @return     int[] of 2 ints, min and max values for the stream
     */
    public function get_number_of_output_streams() {
        return $this->outputstreams;
    }

    /**
     * Extract the configuration settings from a YAML string.
     *
     * @param string $yaml
     * @return object Parsed configurations as an object.
     * @throws \moodle_exception
     */
    public function extract_config(string $yaml) {
        $config = Yaml::parse($yaml, Yaml::PARSE_OBJECT_FOR_MAP);
        if ($this->validate_config($config) !== true) {
            throw new \moodle_exception('invalid_config', 'tool_dataflows');
        }
        return $config;
    }

    /**
     * Validate the configuration settings.
     * Defaults to no check.
     *
     * @param object $config
     * @return true|array true if valid, an array of errors otherwise
     */
    public function validate_config(object $config) {
        return true;
    }

    /**
     * Generates an engine step for this type.
     *
     * @param engine $engine
     * @param \tool_dataflows\step $stepdef
     * @return engine_step
     */
    abstract public function get_engine_step(engine $engine, \tool_dataflows\step $stepdef): engine_step;
}
