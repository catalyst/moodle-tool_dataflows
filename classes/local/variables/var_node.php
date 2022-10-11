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
 * Base class for var objects.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

abstract class var_node {
    /** @var string The local name of the node. */
    protected $name;
    /** @var var_object|null  The parent node. */
    protected $parent;

    /** @var var_object The root of the variable tree. */
    protected $root;

    /** @var var_object The root for localised variable names. */
    protected $localroot;

    /**
     * Construct a variable object.
     *
     * @param string $name
     * @param var_object|null $parent
     * @param var_object|null $root
     * @param var_object|null $localroot
     */
    protected function __construct(string $name, ?var_object $parent = null, ?var_object $root = null, ?var_object $localroot = null) {
        $this->name = $name;
        $this->parent = $parent;

        if (is_null($root)) {
            if (is_null($parent)) {
                $root = $this;
            } else {
                $root = $parent->root;
            }
        }
        $this->root = $root;

        if (is_null($localroot)) {
            if (is_null($parent)) {
                $localroot = $this;
            } else {
                $localroot = $parent->localroot;
            }
        }
        $this->localroot = $localroot;
    }

    /**
     * Retrieve the qualified name, in dot format.
     *
     * @param var_object $root The node the name is relative to.
     * @return string
     * @throws \moodle_exception
     */
    private function get_qualified_name(var_object $root) {
        $name = [];
        $node = $this;
        while ($node !== $root) {
            $name[] = $node->name;
            $node = $node->parent;
        }
        if (is_null($node)) {
            throw new \moodle_exception('root is not in ancestry.');
        }
        return implode('.', array_reverse($name));
    }

    /**
     * Searches for a node.
     *
     * @param array $levels The name of the node, relative to this node, exploded out into an array.
     * @param bool $fill If true, then nodes will be created if they don't already exist.
     * @param bool $isobj If filling, then the node referred to should be an object.
     * @return var_node|null The variable node, or null if none was found.
     */
    abstract protected function find(array $levels, bool $fill = false, bool $isobj = false): ?var_node;

    /**
     * Returns the source (expressions unevaluated) value.
     *
     * @return mixed
     */
    abstract protected function get_source();

    /**
     * Returns the value with expressions evaluated.
     *
     * @return mixed
     */
    abstract protected function get_resolved();

    /**
     * Get a property.
     *
     * @param string $p
     * @return string
     * @throws \moodle_exception
     */
    public function __get(string $p) {
        switch ($p) {
            case 'name':
                return $this->name;
            case 'fullname':
                // The fully qualified name, in dot format, relative to the root.
                return $this->get_qualified_name($this->root);
            case 'localname':
                // The qualified name relative to the local root.
                return $this->get_qualified_name($this->localroot);
            default:
                throw new \moodle_exception("Property $p not supported");
        }
    }
}
