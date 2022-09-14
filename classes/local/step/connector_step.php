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

use tool_dataflows\local\execution\connector_engine_step;
use tool_dataflows\local\execution\engine;
use tool_dataflows\local\execution\engine_step;

/**
 * Base class for connector step types.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class connector_step extends base_step {

    /** @var int[] number of input connectors (min, max) */
    protected $inputconnectors = [0, 1];

    /** @var int[] number of output connectors (min, max) */
    protected $outputconnectors = [0, 1];

    /** @var int[] number of input flows (min, max) */
    protected $inputflows = [0, 1];

    /** @var int[] number of output flows (min, max) */
    protected $outputflows = [0, 1];

    /**
     * Perform the task required by this connector.
     *
     * @param mixed|null $input
     * @return mixed
     */
    abstract public function execute($input = null);

    /**
     * {@inheritdoc}
     */
    public function get_group(): string {
        return 'connectors';
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
        return new connector_engine_step($engine, $this->stepdef, $this);
    }

    /**
     * Returns an array with styles used to draw the dot graph
     *
     * @return  array containing the styles
     * @link    https://graphviz.org/doc/info/attrs.html for a list of available attributes
     */
    public function get_node_styles(): array {
        $styles = [
            'shape'     => 'record',
            'fillcolor' => '#cccccc',
        ];
        $basestyles = parent::get_node_styles();

        if ($this->has_side_effect()) {
            $styles['shape']     = 'rect';
            $styles['style']     = 'filled';
            $styles['fillcolor'] = '#ffc107';
        }

        return array_merge($basestyles, $styles);
    }
}
