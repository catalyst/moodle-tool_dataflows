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

/**
 * Main file
 *
 * @package    tool_dataflows
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2022, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_dataflows\step;

function tool_dataflows_after_config() {
}

/**
 * Returns a list of step types available for this plugin.
 *
 * NOTE: For other plugins, the function name should be simply declared as <component_name>_dataflow_step_types.
 *
 * @return     array of step types
 */
function tool_dataflows_step_types() {
    return [
        new step\debugging(),
        new step\join(),
        new step\mtrace(),
        new step\void_step(),
    ];
}
