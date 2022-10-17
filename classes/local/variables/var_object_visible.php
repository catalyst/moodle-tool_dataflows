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
 * Variable node intended to be visible to the outside world.
 *
 * Variable references within this tree can be relative to this tree as well as relative to the root.
 *
 * This class defines the API to be used by other code.
 *
 * Variables.
 *
 * A store of values that can contain expressions, and reference other values.
 * Variables are identified by a list of names separated by dots.(e.g. 'steps.one.vars.result').
 * Expressions are designated by surrounding them with '${{' and '}}'.
 * This module uses the Symfony expression language module to resolve expressions.
 *
 * The module uses an internal reference/dependency system to ensure that the right expressions get evaluated when
 * needed. Expressions are resolved lazily.
 *
 * The module supports relative referencing. A value stored under a var_object_visible node can reference other
 * values stored under the same node relative to that node.
 * e.g. Two variables 'top.one.two', 'top.one.three', with a local node at 'top.one'. 'top.one.two' can use 'three'
 * as a localised reference.
 *
 * Known weaknesses
 *
 * - Top level objects must be defined first.
 * - Children of local nodes cannot use the same names as the top level (it confuses localisation).
 * - Nodes are created when referenced, not when set.
 * - Local root nodes nodes need to be created before any of its decendents are referenced.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class var_object_visible extends var_object {

    /** The separator used in fully qualified variable names. */
    public const LEVEL_SEPARATER = '.';

    /**
     * Splits a fully qualified (dot separated) name into an array of segments or 'levels'.
     *
     * @param string $name
     * @return array
     */
    public static function name_to_levels(string $name): array {
        return explode(self::LEVEL_SEPARATER, $name);
    }

    /**
     * Creates a fully qualified (dot separated) variable name from a list of levels.
     *
     * @param array $levels
     * @return string
     */
    public static function levels_to_name(array $levels): string {
        return implode(self::LEVEL_SEPARATER, $levels);
    }

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
            $obj = $this->find(self::name_to_levels($name));
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
        $name = trim($name);
        if ($name === '') {
            $obj = $this;
        } else {
            $obj = $this->find(self::name_to_levels($name));
            if (is_null($obj)) {
                return null; // TODO: throw exception?
            }
        }
        return $obj->get_source();
    }

    /**
     * Sets a value.
     *
     * @param string $name The fully qualified (dot separated) name relative to this node. An empty name refers to the tree root.
     * @param mixed $value
     * @throws \moodle_exception
     */
    public function set(string $name, $value) {
        $name = trim($name);
        if ($name === '') {
            $obj = $this;
        } else {
            $obj = $this->find(self::name_to_levels($name), true, is_object($value));
        }

        if (is_object($value)) {
            if (!($obj instanceof var_object)) {
                throw new \moodle_exception('assign_object_to_value', 'tool_dataflows', '', $name);
            }
            $obj->fill_tree($value);
            return;
        }

        if (!($obj instanceof var_value)) {
            throw new \moodle_exception('assign_value_to_object', 'tool_dataflows', '', $name);
        }
        $obj->set($value);
    }

    /**
     * Directly evaluate an expression, using the stored variables as references.
     *
     * @param string $expression
     * @param callable|null $errorhandler A function to if the parsing fails. If null, then errors will be ignored.
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
