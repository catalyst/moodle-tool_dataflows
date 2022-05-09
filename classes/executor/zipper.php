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

namespace tool_dataflows\executor;

/**
 * Passes a value from each input to the output in round robin fashion.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zipper extends step {

    public function __construct() {
        $this->maxinputs = null;
    }

    public function reset() {
        reset($this->inputs);
    }

    public function is_ready($id): bool {
        return $this->are_dependencies_satisfied() && current($this->inputs)->is_ready();
    }

    public function next($id) {
        $val = current($this->inputs)->next();
        if (next($this->inputs) === false) {
            reset($this->inputs);
        }
        return $val;
    }
}
