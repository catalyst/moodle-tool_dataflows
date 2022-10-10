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
 * Object node for a variables tree.
 *
 * Known weaknesses
 *
 * 1. Top level objects must be defined first.
 * 2. Nodes are created when referenced. Not when set.
 * 3. Localised node need to be created before any of its decendants are referenced.
 * 4. No way to easily inject derived classes like var_step.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class var_object extends var_node {
    /** @var array[var_obj] The node's children.  */
    protected $children = [];

    /**
     * Searches for a node.
     *
     * @param array $levels The name of the node, relative to this node, exploded out into an array.
     * @param bool $fill If true, then nodes will be created if they don't already exist.
     * @param bool $isobj If filling, then the node referred to should be an object.
     * @return var_node|null The variable object, or null if none was found.
     */
    protected function find(array $levels, bool $fill = false, bool $isobj = false): ?var_node {
        if (empty($levels)) {
            return $this;
        }
        $name = array_shift($levels);
        if (!isset($this->children[$name])) {
            if (!$fill) {
                return null;
            }
            if (empty($levels)) {
                // We are at the end leaf, so create a var_value and return it.
                $classname = $isobj ? var_object::class : var_value::class;
                $this->children[$name] = new $classname($name, $this, $this->root, $this->localroot);
                return $this->children[$name];
            } else {
                $this->children[$name] = new var_object($name, $this, $this->root, $this->localroot);
            }
        }
        return $this->children[$name]->find($levels, $fill);
    }

    /**
     * Returns the source (expressions unevaluated) values for this tree.
     * Creates a tree of stdClass to match this one.
     *
     * @return \stdClass
     */
    protected function get_source() {
        $obj = new \stdClass();
        foreach ($this->children as $name => $child) {
            $obj->$name = $child->get_source();
        }
        return $obj;
    }

    /**
     * Returns the values for this tree with expressions evaluated.
     * Creates a tree of stdClass to match this one.
     *
     * @return \stdClass
     */
    protected function get_resolved() {
        $obj = new \stdClass();
        foreach ($this->children as $name => $child) {
            $obj->$name = $child->get_resolved();
        }
        return $obj;
    }

    /**
     * Fills out the subtree with values.
     *
     * @param object $parent
     */
    protected function fill_tree(object $parent) {
        foreach ($parent as $name => $value) {
            if (is_object($value)) {
                $node = $this->find([$name], true, true);
                $node->fill_tree($value);
            } else {
                $node = $this->find([$name], true);
                $node->set($value);
            }
        }
    }
}
