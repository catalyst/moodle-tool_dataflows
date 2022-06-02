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

use tool_dataflows\local\execution\iterators\iterator;
use tool_dataflows\local\execution\iterators\map_iterator;
use tool_dataflows\local\execution\flow_engine_step;

/**
 * Base class for writer step types.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class writer_step extends flow_step {

    /** @var int[] number of input flows (min, max). */
    protected $inputflows = [1, 1];

    /** @var int[] number of output flows (min, max). */
    protected $outputflows = [0, 1];

    /** @var int[] number of output connectors (min, max). */
    protected $outputconnectors = [0, 1];

    /** @var bool whether or not this step type (potentially) contains a side effect or not */
    protected $hassideeffect = true;

    /**
     * Get the iterator for the step, based on configurations.
     *
     * @param flow_engine_step $step
     * @return iterator
     */
    public function get_iterator(flow_engine_step $step): iterator {
        // Default is to simply map.
        $upstream = current($step->upstreams);
        if ($upstream === false || !$upstream->is_flow()) {
            throw new \moodle_exception(get_string('non_reader_steps_must_have_flow_upstreams', 'tool_dataflows'));
        }
        return new map_iterator($step, $upstream->iterator);
    }

    /**
     * {@inheritdoc}
     */
    public function get_group(): string {
        return 'writers';
    }
}
