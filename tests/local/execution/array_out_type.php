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

namespace tool_dataflows\local\execution;

use tool_dataflows\local\execution\iterators\iterator;
use tool_dataflows\local\execution\iterators\dataflow_iterator;
use tool_dataflows\local\execution\flow_engine_step;
use tool_dataflows\local\step\writer_step;

/**
 * Test writer step type that writes to an array.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class array_out_type extends writer_step {

    /** @var array The output array that the dat is written into. Make sure it is empty before use. */
    public static $dest = [];

    /** @var bool whether or not this step type (potentially) contains a side effect or not */
    protected $hassideeffect = true;

    /**
     * Get the iterator for the step, based on configurations.
     *
     * @return iterator
     */
    public function get_iterator(): iterator {
        $input = current($this->enginestep->upstreams)->iterator;
        return new dataflow_iterator($this->enginestep, $input);
    }

    /**
     * Step callback handler
     *
     * Adds the input to the static $dest (destination)
     *
     * @param   mixed $input
     * @return  mixed $input
     */
    public function execute($input = null) {
        self::$dest[] = $input;
        return $input;
    }
}
