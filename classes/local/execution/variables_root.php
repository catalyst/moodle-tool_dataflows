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

use Symfony\Component\Yaml\Yaml;
use tool_dataflows\dataflow;
use tool_dataflows\helper;
use tool_dataflows\parser;
use tool_dataflows\step;

/**
 * Class for storing and managing the whole variables tree for a dataflow execution.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class variables_root extends variables_base {
    /** Used to detect cyclic dependencies. */
    private const PLACEHOLDER = '__PLACEHOLDER__';
    /** Limit to number of repeats of evaluating the tree. */
    private const REPEAT_LIMIT = 100;

    /** @var dataflow The dataflow this variable object is for. */
    private $dataflow;

    /** @var object The current state of the variables tree with expressions evaluated. */
    private $tree;

    /** @var string|null Localised step. */
    private $localstep = null;
    /** @var bool Reconstruct the tree on next read. */
    private $isvalid = false;

    /**
     * Construct a variables object for a dataflow.
     *
     * @param dataflow $dataflow
     */
    public function __construct(dataflow $dataflow) {
        parent::__construct();

        // Global variables. These should be immutable.
        $globalvars = new \stdClass();
        $globalvars->cfg = helper::get_cfg_vars();
        $globalvars->vars = Yaml::parse(get_config('tool_dataflows', 'global_vars'), Yaml::PARSE_OBJECT_FOR_MAP)
                ?? new \stdClass();
        $this->sourcetree->global = $globalvars;

        // The variables for dataflow.
        $this->sourcetree->dataflow = new variables_dataflow($dataflow, $this);

        // The variables for each step.
        $this->sourcetree->steps = new \stdClass();
        foreach ($dataflow->steps as $stepdef) {
            $this->sourcetree->steps->{$stepdef->alias} = new variables_step($stepdef, $this);
        }
    }

    /**
     * Directly creates a variable tree for the step. Normally not needed, but is a concession to flowcaps,
     * which are created during execution.
     *
     * @param step $stepdef The step. Must be a flow cap.
     * @returns variables_step The variables tree for the flow cap.
     */
    public function add_step(step $stepdef): variables_step {
        if ($stepdef->type !== \tool_dataflows\local\step\flow_cap::class) {
            throw new \moodle_exception('Only flow caps should be added directly to variables.');
        }
        $this->sourcetree->steps->{$stepdef->alias} = new variables_step($stepdef, $this);
        return $this->sourcetree->steps->{$stepdef->alias};
    }

    /**
     * Gets the dataflow variables object.
     *
     * @return variables_dataflow
     */
    public function get_dataflow_variables(): variables_dataflow {
        return $this->sourcetree->dataflow;
    }

    /**
     * Get the step variables object.
     *
     * @param string $name Name of the node
     * @return variables_step
     */
    public function get_step_variables(string $name): variables_step {
        return $this->sourcetree->steps->{$name};
    }

    /**
     * Makes a step to be localised. This allows its subtree to be accessed relatively.
     * E.g. Set alias to 'xyz', and 'steps.xyz.vars.someval' can now be accessed as 'vars.someval'.
     * Set to null to remove localisation.
     *
     * @param string|null $alias
     */
    public function localise(?string $name = null) {
        $this->localstep = $name;
        $this->isvalid = false;
    }

    /**
     * Evaluate an expression against the resolved variables.
     *
     * @param string $expression
     * @return string
     */
    public function evaluate(string $expression): string {
        $parser = new parser();
        return $parser->evaluate_or_fail($expression, (array) $this->get_tree());
    }

    /**
     * Get the variables tree with expression resolution.
     *
     * @return object
     */
    public function get_tree(): object {
        if (!$this->isvalid) {
            $this->reconstruct();
        }
        return $this->tree;
    }

    /**
     * Get a variable's value with expressions resolved.
     *
     * @param string $name The name of the variable using dot format (e.g. dataflow.vars.abc).
     * @return mixed The value, or null if the variable is not defined.
     */
    public function get_resolved(string $name) {
        if (!$this->isvalid) {
            $this->reconstruct();
        }
        $levels = explode('.', $name);
        $root = $this->tree;
        $child = array_shift($levels);
        if (!isset($root->$child)) {
            return null;
        }
        while (count($levels) !== 0) {
            $root = $root->$child;
            $child = array_shift($levels);
            if (!isset($root->$child)) {
                return null;
            }
        }
        return $root->$child;
    }

    /**
     * Reconstructs the tree.
     */
    public function reconstruct() {
        // Make a clean copy of the variable definitions.
        $this->tree = new \stdClass();
        $this->clone($this->tree, $this->sourcetree);

        // Go through the tree and resolve expressions. Do this repeatedly to catch expressions that resolve into expressions.
        for ($i = 0; $i < self::REPEAT_LIMIT; ++$i) {
            if (!$this->tree_walk('', $this->tree)) {
                break;
            }
        }
        $this->isvalid = true;
    }

    /**
     * Copy the object over.
     *
     * @param object $newtree
     * @param object $oldtree
     */
    private function clone(object $newtree, object $oldtree) {
        foreach ($oldtree as $key => $value) {
            if (is_object($value)) {
                $newtree->$key = new \stdClass();
                $this->clone($newtree->$key, $value);
            } else {
                $newtree->$key = $value;
            }
        }
    }

    /**
     * Walk through the tree, resolving expressions.
     *
     * @param string $name
     * @param object $tree
     * @return bool
     */
    private function tree_walk(string $name, object $tree): bool {
        $foundexpression = false;
        foreach ($tree as $key => &$value) {
            if (is_object($value)) {
                $foundexpression |= $this->tree_walk("$name.$key", $value);
            } else if (is_string($value)) {
                $foundexpression |= $this->resolve_expression("$name.$key", $value);
            }
        }
        return $foundexpression;
    }

    /**
     * Resolves the expressions in the value.
     *
     * @param string $name
     * @param mixed $value
     * @return bool
     * @throws \moodle_exception
     */
    private function resolve_expression(string $name, &$value): bool {
        $parser = new parser();

        if (!$parser->has_expression($value)) {
            return false;
        }

        // Apply localising.
        $tree = $this->localstep ? array_merge((array) $this->tree, (array) $this->tree->steps->{$this->localstep}) : (array) $this->tree;

        $resolved = $parser->evaluate($value, $tree);
        if ($resolved === self::PLACEHOLDER) {
            throw new \moodle_exception(
                'recursiveexpressiondetected',
                'tool_dataflows',
                '',
                ltrim($name, '.')
            );
        }
        if (isset($resolved)) {
            $value = $resolved;
        }
        return true;
    }

    /**
     * Invalidates the tree, ensuring it will get rebuilt.
     */
    public function invalidate() {
        $this->isvalid = false;
    }
}
