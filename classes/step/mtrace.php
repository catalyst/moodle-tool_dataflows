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

use tool_dataflows\executor\iterators\iterator;
use tool_dataflows\executor\iterators\map_iterator;
use tool_dataflows\executor;

/**
 * Step type: mtrace
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mtrace extends base_step {

    public function get_iterator(executor\step $step): iterator {
        $input = current($step->upstreams)->iterator;
        return new map_iterator($step, $input);
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
}
