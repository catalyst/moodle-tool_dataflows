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

use tool_dataflows\local\execution\engine;
use tool_dataflows\local\execution\engine_flow_cap;
use tool_dataflows\local\execution\engine_step;
use tool_dataflows\local\execution\flow_engine_step;
use tool_dataflows\local\execution\iterators\iterator;
use tool_dataflows\local\execution\iterators\dataflow_iterator;

/**
 * A special, virtual flow step that is attached to the end of a flow block.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class flow_cap extends flow_step {

    /**
     * Generates an engine step for this type.
     *
     * @param engine $engine
     * @return engine_step
     */
    protected function generate_engine_step(engine $engine): engine_step {
        return new engine_flow_cap($engine, $this->stepdef, $this);
    }

    /**
     * Gets the iterator for this particular upstream.
     *
     * This returns different next steps/handlers depending on the upstream path.
     *
     * @param  flow_engine_step $upstream
     * @return iterator
     */
    public function get_upstream_iterator(flow_engine_step $upstream): iterator {
        $this->upstream = $upstream;
        return $this->get_iterator();
    }

    /**
     * Get the iterator for the step, based on configurations.
     *
     * @return iterator
     */
    public function get_iterator(): iterator {
        $upstream = $this->upstream;
        if ($upstream === false || !$upstream->is_flow()) {
            throw new \moodle_exception(get_string('non_reader_steps_must_have_flow_upstreams', 'tool_dataflows'));
        }
        return new dataflow_iterator($this->enginestep, $upstream->iterator);
    }
}
