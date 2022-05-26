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

namespace tool_dataflows\execution;

use tool_dataflows\execution\iterators\iterator;
use tool_dataflows\execution\iterators\php_iterator;
use tool_dataflows\execution\flow_engine_step;
use tool_dataflows\step\base_step;

/**
 * Test reader step type that supplies an array.
 * TODO: Until better classes have been defined, this is GEFN (Good Enough For Now).
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class array_in_type extends base_step {

    /** @var int[] number of input streams (min, max), zero for readers. */
    protected $inputstreams = [0, 0];

    public function is_flow(): bool {
        return true;
    }

    /** @var array The source. Place dat here before use. */
    public static $source = [];

    public function get_iterator(flow_engine_step $step): iterator {
        return new php_iterator($step, new \ArrayIterator(self::$source));
    }

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
        return new flow_engine_step($engine, $stepdef, $this);
    }
}
