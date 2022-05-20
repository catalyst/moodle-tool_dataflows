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

use tool_dataflows\execution\engine;
use tool_dataflows\execution\engine_step;
use tool_dataflows\execution\flow_engine_step;

/**
 * Step type: mtrace
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mtrace extends base_step {

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
     * This will logs the input via mtrace and passes the input value as-is to the output.
     *
     * @param mixed $input
     * @return mixed $output
     */
    public function execute($input) {
        $output = $input;
        mtrace(json_encode($input));
        return $output;
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
        return new flow_engine_step($engine, $stepdef, $this);
    }
}
