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

namespace tool_dataflows\step;

use tool_dataflows\execution\engine;
use tool_dataflows\execution\engine_step;
use tool_dataflows\execution\engine_flow_cap;

/**
 * A special, virtual flow step that is attached to the end of a flow block.
 *
 * @package   <insert>
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class flow_cap extends base_step {

    /**
     * Does this type define a flow step?
     * @return bool
     */
    public function is_flow(): bool {
        return true;
    }

    /**
     * Executes the step
     *
     * Does nothing.
     *
     * @param mixed $input
     * @return mixed $output
     */
    public function execute($input) {
        return $input;
    }

    /**
     * Generates an engine step for this type.
     *
     * @param engine $engine
     * @param \tool_dataflows\step $stepdef
     * @return engine_step
     */
    public function get_engine_step(engine $engine, \tool_dataflows\step $stepdef): engine_step {
        // This should be sufficient for most cases. Override this function if needed.
        return new engine_flow_cap($engine, $stepdef, $this);
    }
}
