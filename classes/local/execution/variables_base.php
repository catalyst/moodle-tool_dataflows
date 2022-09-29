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

namespace tool_dataflows\local\execution;

/**
 * Base class for variables objects.
 *
 * @package   tool_daaflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class variables_base implements \IteratorAggregate {
    /** @var \stdClass The variables store. */
    protected $sourcetree;

    /**
     * Make the object.
     */
    public function __construct() {
        $this->sourcetree = new \stdClass();
    }

    /**
     * Get a variable's value.
     *
     * @param string $name The name of the variable using dot format (e.g. dataflow.vars.abc).
     * @return mixed The value, or null if the variable is not defined.
     */
    public function get(string $name) {
        $levels = explode('.', $name);
        $root = $this->sourcetree;
        $child = array_shift($levels);
        if (!isset($root->$child)) {
            return null;
        }
        while (count($levels) !== 0) {
            if ($root->$child instanceof variables_base) {
                return $root->$child->get(implode('.', $levels));
            }
            $root = $root->$child;
            $child = array_shift($levels);
            if (!isset($root->$child)) {
                return null;
            }
        }
        return $root->$child;
    }

    /**
     * Sets a variable in the tree.
     *
     * @param string $name The name of the variable in dot format, relative to this tree's root (e.g. 'config.destination').
     * @param mixed $value.
     */
    public function set(string $name, $value) {
        $levels = explode('.', $name);
        $root = $this->sourcetree;
        $child = array_shift($levels);
        while (count($levels) !== 0) {
            if (!isset($root->$child)) {
                $root->$child = new \stdClass();
            }
            if ($root->$child instanceof variables_base) {
                $root->$child->set(implode('.', $levels), $value);
                return;
            }
            if (!is_object($root->$child)) {
                throw new \moodle_exception('trying to dereference through a non-object.');
            }
            $root = $root->$child;
            $child = array_shift($levels);
        }
        $root->$child = $value;
    }

    /**
     * Returns an iterator over the variables' top elements.
     */
    public function getIterator(): \Traversable {
        return new \ArrayIterator($this->sourcetree);
    }
}

