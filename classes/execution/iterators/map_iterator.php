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

namespace tool_dataflows\execution\iterators;

use tool_dataflows\execution\engine;
use tool_dataflows\execution\flow_engine_step;

/**
 * A mapping iterator that takes in a value from another iterator, passes it to a function, and then
 * passes the return value to the output.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class map_iterator implements iterator {
    protected $steptype;
    protected $finished = false;
    protected $input;

    protected $iterationcount = 0;

    /**
     * @param flow_engine_step $step The step the iterator is for.
     * @param iterator $input The iterator that serves as input to this one.
     */
    public function __construct(flow_engine_step $step, iterator $input) {
        $this->step = $step;
        $this->input = $input;
        $this->steptype = $step->steptype;
    }

    /**
     * True if the iterator has no more values to provide.
     *
     * @return bool
     */
    public function is_finished(): bool {
        return $this->finished;
    }

    /**
     * True if the iterator is capable (or allowed) of supplying a value.
     *
     * @return bool
     */
    public function is_ready(): bool {
        return !$this->finished && $this->input->is_ready();
    }

    /**
     * Terminate the iterator immediately.
     */
    public function abort() {
        $this->finished = true;
    }

    /**
     * Next item in the stream.
     *
     * @return object|bool A JSON compatible object, or false if nothing returned.
     */
    public function next() {
        if ($this->finished) {
            return false;
        }

        $value = $this->input->next();
        if ($this->input->is_finished()) {
            $this->abort();
        }
        $newvalue = $this->steptype->execute($value);
        ++$this->iterationcount;
        $this->step->log('Iteration ' . $this->iterationcount . ': ' . json_encode($newvalue));
        return $newvalue;
    }
}
