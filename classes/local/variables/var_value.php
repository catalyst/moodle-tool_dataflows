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

use tool_dataflows\parser;

/**
 * Leaf node. Holds a standard PHP value.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class var_value extends var_node {
    /** @var bool If true, then the value will be evaluated before being returned. */
    private $dirty = true;
    /** @var bool Marks this as a secret variable. This means that it will be redacted when retrieved unless specified otherwise. */
    private $secret;

    /** @var array[var_value] All variables referenced by this. */
    private $references = [];
    /** @var array[var_value] All variables immediately dependent on this. */
    private $dependents = [];

    /** @var mixed the original value with expressions unresolved. If null, then is 'unset'.  */
    private $raw = null;
    /** @var mixed The resolved value. */
    private $resolved;

    /**
     * Make this value, and all dependents, as dirty (will be reevaluated).
     */
    protected function make_dirty() {
        $this->dirty = true;
        foreach ($this->dependents as $dep) {
            $dep->make_dirty();
        }
    }

    /**
     * 'Finds' this object. A convenience method to work with var_object::find().
     *
     * @param array $levels
     * @param bool $fill
     * @param bool $isobj
     * @return var_node|null
     * @throws \moodle_exception
     */
    protected function find(array $levels, bool $fill = false, bool $isobj = false): ?var_node {
        if (!empty($levels)) {
            throw new \moodle_exception(
                'variables:reference_lead_as_branch',
                'tool_dataflows',
                '',
                var_object_visible::levels_to_name($levels)
            );
        }
        return $this;
    }

    /**
     * Sets the raw value. References will be updated, and dependents will be made dirty.
     *
     * @param mixed $value
     */
    public function set($value) {
        $this->raw = $value;

        // Remove the existing references.
        foreach ($this->references as $ref) {
            $ref->clear_dependent($this);
        }
        $this->references = [];

        $this->make_dirty();

        // If the value is not a string, then it is guaranteed to not have expressions, and so we can resolve it now.
        // TODO: Could also call parser::has_expression().
        if (!is_string($value)) {
            $this->resolved = $value;
            $this->dirty = false;
            return;
        }

        $varnames = parser::get_parser()->find_variable_names($value);

        foreach ($varnames as $name) {
            $levels = var_object_visible::name_to_levels($name);

            // If the first identifier can be found in the top level, then the name is considered an absolute reference.
            if (!is_null($this->root->find([$levels[0]]))) {
                // Use absolute root.
                $root = $this->root;
            } else if (!is_null($this->localroot)) {
                // Use local root.
                $root = $this->localroot;
            } else {
                throw new \moodle_exception('variables:cannot_resolve_ref', 'tool_dataflows', '', $name);
            }

            $node = $root->find($levels, true);
            $this->references[$name] = $node;
            $node->add_dependent($this);
        }
    }

    /**
     * Returns the source (expressions unevaluated) value.
     *
     * @return mixed
     */
    protected function get_source() {
        return $this->raw;
    }

    /**
     * Returns the value with expressions evaluated.
     *
     * @return mixed
     */
    protected function get_resolved() {
        if (is_null($this->raw)) {
            return null;
        }
        if ($this->dirty) {
            $this->evaluate();
        }
        return $this->resolved;
    }

    /**
     * Evaluates the expressions in this value. Any referenced value that is dirty will also be evaluated.
     *
     * @param callable|null $errorhandler A function to if the parsing fails. If null, then errors will be ignored.
     */
    public function evaluate(?callable $errorhandler = null) {
        $tree = $this->make_reference_tree();
        if (is_null($errorhandler)) {
            $this->resolved = parser::get_parser()->evaluate($this->raw, (array) $tree);
        } else {
            $this->resolved = parser::get_parser()->evaluate_or_fail($this->raw, (array) $tree, $errorhandler);
        }
        $this->dirty = false;
    }

    /**
     * Remove a dependant from the list.
     *
     * @param var_value $var
     */
    protected function clear_dependent(var_value $var) {
        $this->dependents = array_udiff(
            $this->dependents,
            [$var],
            function($a, $b) {
                return $a === $b;
            }
        );
    }

    /**
     * Adds a dependant to the list.
     *
     * @param var_value $var
     */
    protected function add_dependent(var_value $var) {
        if (!in_array($var, $this->dependents)) {
            $this->dependents[] = $var;
        }
    }

    /**
     * Makes a tree of values using this node's references, for use with the Symfony parser.
     *
     * @return \stdClass
     */
    private function make_reference_tree(): \stdClass {
        $tree = new \stdClass();
        foreach ($this->references as $name => $obj) {
            $value = $obj->get_resolved(false);

            // Do not add the value if it is not set. This shoudl result in the expression remaining unresolved in the evalutaion.
            if (is_null($value)) {
                continue;
            }

            $levels = var_object_visible::name_to_levels($name);

            // Fill out the tree, creating a object for each level of the name (except the last).
            $node = $tree;
            $name = array_shift($levels);
            while (count($levels) !== 0) {
                if (!isset($node->$name)) {
                    $node->$name = new \stdClass();
                }
                $node = $node->$name;
                $name = array_shift($levels);
            }
            $node->$name = $value;
        }

        return $tree;
    }
}
