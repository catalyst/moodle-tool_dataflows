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

namespace tool_dataflows\executor\readers;

/**
 * Source that draws from a PHP Iterator.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class php_iterator extends \tool_dataflows\executor\step {
    protected $source = null;

    public function __construct() {
        $this->mininputs = 0;
    }

    public function reset() {
        $this->source->rewind();
    }

    public function set_iterator(\Iterator $source) {
        $this->source = $source;
    }

    public function is_empty(): bool {
        return !$this->source->valid();
    }

    public function is_ready($id): bool {
        return ($this->source !== null);
    }

    /**
     * @return object|bool A JSON compatible object, or false if nothing returned.
     */
    public function next($id) {
        $value = $this->source->current();
        $this->source->next();
        return $value;
    }
}
