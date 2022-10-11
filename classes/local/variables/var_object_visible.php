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

namespace tool_dataflows\local\variables;

/**
 * Node intended to be visible to the outside world.
 * Variable references within this tree can be relative to this tree as well as relative to the root.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class var_object_visible extends var_object {

    /**
     * Creates the node, but make this node the root for localised referencing.
     *
     * @param string $name
     * @param var_object|null $parent
     * @param var_object|null $root
     */
    protected function __construct(string $name, ?var_object $parent = null, ?var_object $root = null) {
        parent::__construct($name, $parent, $root, $this);
    }

    /**
     * Gets a (resolved) value.
     *
     * @param string $name The fully qualified (dot separated) name relative to this node. If empty, will return the whole tree.
     * @return mixed|\stdClass|null
     */
    public function get(string $name = '') {
        if (trim($name) === '') {
            $obj = $this;
        } else {
            $obj = $this->find(explode('.', $name));
            if (is_null($obj)) {
                return null; // TODO: throw exception?
            }
        }
        return $obj->get_resolved();
    }

    /**
     * Gets a raw value.
     *
     * @param string $name The fully qualified (dot separated) name relative to this node. If empty, will return the whole tree.
     * @return mixed|\stdClass|null
     */
    public function get_raw(string $name = '') {
        if (trim($name) === '') {
            $obj = $this;
        } else {
            $obj = $this->find(explode('.', $name));
            if (is_null($obj)) {
                return null; // TODO: throw exception?
            }
        }
        return $obj->get_source();
    }

    /**
     * Sets a value.
     *
     * @param string $name The fully qualified (dot separated) name relative to this node. If empty, will return the whole tree.
     * @param mixed $value
     * @throws \moodle_exception
     */
    public function set(string $name, $value) {
        if (trim($name) === '') {
            $obj = $this;
        } else {
            $obj = $this->find(explode('.', $name), true, is_object($value));
        }
        if (is_object($value)) {
            $obj->fill_tree($value);
            return;
        }

        if ($obj instanceof var_object) {
            throw new \moodle_exception('Cannot set an object node to a non-object value');
        }
        $obj->set($value);
    }

    /**
     * Directly evaluate an expression.
     *
     * @param string $expression
     * @return mixed
     */
    public function evaluate(string $expression, ?callable $errorhandler = null) {
        $varvalue = new var_value('', $this);
        $varvalue->set($expression);
        $varvalue->evaluate($errorhandler);
        $result = $varvalue->get_resolved();
        $varvalue->set(null); // Clear out dependencies that were created.
        return $result;
    }
}
