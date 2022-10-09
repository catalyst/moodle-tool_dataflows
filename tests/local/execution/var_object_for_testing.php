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

namespace tool_dataflows;

use tool_dataflows\local\variables\var_object_visible;

/**
 * A var node for use in testing.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class var_object_for_testing extends var_object_visible {
    /**
     * Create it.
     *
     * @param $name
     */
    public function __construct($name) {
        parent::__construct($name, null, $this);
    }

    /**
     * Creates a visible node for this name.
     *
     * @param $name
     * @return var_object_visible
     */
    public function create_local_node($name) {
        $levels = explode('.', $name);
        $localname = array_pop($levels);
        $node = $this->find($levels, true);
        $obj = new var_object_visible($localname, $node, $this);
        $node->children[$localname] = $obj;
        return $obj;
    }

    public function tfind($name) {
        return $this->find(explode('.', $name));
    }
}
