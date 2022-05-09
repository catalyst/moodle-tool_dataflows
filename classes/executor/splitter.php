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
 * Splits an object input item into its elements and supplies each one to a separate output.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class splitter extends step {
    protected $cache;
    protected $vars;
    protected $usedids = [];

    public function __construct($outputids) {
        $this->maxoutputs = 0; // Cannot use add_input().
        $this->outputids = $outputids; // Links must use these IDs.
    }

    public function reset() {
        $this->usedids = [];
        $this->cache = null;
    }

    public function is_ready($id): bool {
        return parent::is_ready($id) && !in_array($id, $this->usedids);
    }

    public function is_empty(): bool {
        return parent::is_empty() && $this->cache === null;
    }

    public function next($id) {
        if ($this->is_ready($id)) {
            if ($this->cache === null) {
                $this->cache = $this->inputs[0]->next();
                $this->vars = get_object_vars($this->cache);
            }
            $val = $this->vars[$id];
            $this->usedids[] = $id;
            if (count($this->usedids) === count($this->outputs)) {
                $this->reset();
            }
            return (object) [$val];
        }
        return false;
    }
}
