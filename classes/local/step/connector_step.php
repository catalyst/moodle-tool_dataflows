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

use tool_dataflows\execution\connector_engine_step;
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
    protected $inputconnectors = [1, 1];

    /** @var int[] number of output connectors (min, max) */
    protected $outputconnectors = [0, 1];

    /**
     * Perform the task required by this connector.
     *
     * @return bool Returns true if successful, false otherwise.
     */
    abstract public function execute(): bool;

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
     * @param \tool_dataflows\step $stepdef
     * @return engine_step
     */
    public function get_engine_step(engine $engine, \tool_dataflows\step $stepdef): engine_step {
        return new connector_engine_step($engine, $stepdef, $this);
    }
}
