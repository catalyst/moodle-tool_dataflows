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

/**
 * Step type: join
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class join extends base_step {

    /**
     * @var int[] number of input streams (min, max)
     *
     * For a join step, it should have 2 or more inputs and for now, up to 20
     * possible input streams.
     */
    protected $inputstreams = [2, 20];

    /**
     * @var int[] number of output streams (min, max)
     *
     * For a join step, there should be exactly one output. This is because
     * without at least one output, there is no need to perform a join.
     */
    protected $outputstreams = [1, 1];

    /**
     * Executes the step
     *
     * This step not perform any operations, but instead waits for all
     * dependencies to be complete before continuing. This passes the input
     * as-is to the output.
     *
     * @param mixed $input
     * @return mixed $output
     */
    public function execute($input) {
        return $input;
    }
}
