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

namespace tool_dataflows\loaders;

/**
 * Loader that outputs to an array.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class php_array extends \tool_dataflows\step {
    public $output = [];

    protected $robin = 0;

    public function is_ready(): bool {
        return ($this->are_dependencies_satisfied() &&
            $this->inputs[$this->robin]->is_ready());
    }

    public function next() {
        if ($this->is_empty() || !$this->is_ready()) {
            return false;
        }
        $value = $this->inputs[$this->robin]->next();
        $this->output[] =  $value;
        $this->robin = ($this->robin + 1) % count($this->inputs);
        return $value;
    }
}
