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

namespace tool_dataflows;

/**
 * A linking class to connect steps in a way to simplify the interface. A link only needs to deal with a
 * single iterator interface.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class output_port implements iterator{
    private $step;
    public $id;

    public function __construct($step) {
        $this->step = $step;
    }

    public function is_empty(): bool {
        return $this->step->is_empty();
    }

    public function is_ready(): bool {
        return $this->step->is_ready();
    }

    public function next() {
        if ($this->step->is_ready()) {
            return $this->step->next();
        }
    }
}
