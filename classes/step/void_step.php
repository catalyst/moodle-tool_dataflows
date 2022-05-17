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
use tool_dataflows\executor\step_executor;

/**
 * Step type: void
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class void_step extends base_step {

    public function get_iterator(step_executor $step): iterator {
        $input = current($step->upstreams)->iterator;
        return new class($step, $input) extends map_iterator {
            public function __construct(\tool_dataflows\executor\step $step, iterator $input) {
                parent::__construct($step, $input);
                // This finishes straight away.
                $this->abort();
            }
        };
    }

    /**
     * Executes the step
     *
     * This will simply return nothing, causing the output chain to be empty
     *
     * @param mixed $input
     * @return mixed $output
     */
    public function execute($input) {
        return;
    }
}
