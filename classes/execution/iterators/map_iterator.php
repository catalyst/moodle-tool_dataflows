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

use tool_dataflows\execution\step_executor;

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
    private $steptype;
    private $finished = false;
    private $input;

    public function __construct(step_executor $step, iterator $input) {
        $this->step = $step;
        $this->input = $input;
        $this->steptype = $step->steptype;
    }

    public function is_finished(): bool {
        return $this->finished;
    }

    public function is_ready(): bool {
        return !$this->finished && $this->input->is_ready();
    }

    public function abort() {
        $this->finished = true;
    }

    public function next() {
        if ($this->finished) {
            return false;
        }
        try {
            $value = $this->input->next();
            if ($this->input->is_finished()) {
                $this->abort();
                $this->step->iterator_finished();
            }
            $newvalue = $this->steptype->execute($value);
            return $newvalue;
        } catch (\Throwable $exception) {
            $this->step->dataflow->abort($this->step, $exception);
        }
    }
}
