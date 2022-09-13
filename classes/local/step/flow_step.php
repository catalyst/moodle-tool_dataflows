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

namespace tool_dataflows\local\step;

use tool_dataflows\local\execution\engine;
use tool_dataflows\local\execution\engine_step;
use tool_dataflows\local\execution\iterators\iterator;
use tool_dataflows\local\execution\iterators\dataflow_iterator;
use tool_dataflows\local\execution\flow_engine_step;

/**
 * Base class for flow step types.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class flow_step extends base_step {

    /** @var int[] number of input flows (min, max). */
    protected $inputflows = [1, 1];

    /** @var int[] number of output flows (min, max). */
    protected $outputflows = [1, 1];

    /**
     * Does this type define a flow step?
     * @return bool
     */
    final public function is_flow(): bool {
        return true;
    }

    /**
     * Step callback handler
     *
     * Implementation can vary, this might be a transformer, resource, or
     * something else.
     *
     * @param mixed|null $input
     * @return mixed
     */
    public function execute($input = null) {
        // Default is to do nothing.
        return $input;
    }

    /**
     * Generates an engine step for this type.
     *
     * This should be sufficient for most cases. Override this function if needed.
     *
     * @param engine $engine
     * @return engine_step
     */
    protected function generate_engine_step(engine $engine): engine_step {
        return new flow_engine_step($engine, $this->stepdef, $this);
    }

    /**
     * Get the iterator for the step, based on configurations.
     *
     * @return iterator
     */
    public function get_iterator(): iterator {
        $upstream = current($this->enginestep->upstreams);
        if ($upstream === false || !$upstream->is_flow()) {
            throw new \moodle_exception(get_string('non_reader_steps_must_have_flow_upstreams', 'tool_dataflows'));
        }
        return new dataflow_iterator($this->enginestep, $upstream->iterator);
    }

    /**
     * {@inheritdoc}
     */
    public function get_group(): string {
        return 'flows';
    }

    /**
     * Returns an array with styles used to draw the dot graph
     *
     * @return  array containing the styles
     * @link    https://graphviz.org/doc/info/attrs.html for a list of available attributes
     */
    public function get_node_styles(): array {
        $basestyles = parent::get_node_styles();
        $styles = [
            'shape'     => 'rect',
            'fillcolor' => '#8cd0db',
            'fontcolor' => '#000000',
            'style'     => 'filled,rounded',
        ];

        if ($this->has_side_effect()) {
            $styles['fillcolor'] = '#008196';
            $styles['fontcolor'] = '#ffffff';
        }

        return array_merge($basestyles, $styles);
    }
}
