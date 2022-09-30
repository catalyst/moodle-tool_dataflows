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

use tool_dataflows\step;

/**
 * Manager for step variables.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class variables_step extends variables_base {
    /** @var variables_root The root variables object. */
    private $root;

    /** @var string Name of the node. */
    private $name;

    /**
     * Constructs the object, setting up the structure.
     *
     * @param step $stepdef
     * @param variables_root $root
     */
    public function __construct(step $stepdef, variables_root $root) {
        parent::__construct();
        $this->root = $root;
        $this->name = $stepdef->alias;

        // Define the structure of the variables.
        $this->sourcetree->name = $stepdef->name;
        $this->sourcetree->alias = $stepdef->alias;
        $this->sourcetree->description = $stepdef->description;
        $this->sourcetree->depends_on = $stepdef->get_dependencies_cleaned();
        $this->sourcetree->type = $stepdef->type;
        $this->sourcetree->config = $stepdef->get_raw_config();
        $this->sourcetree->vars = $stepdef->get_raw_vars();
        // Initialise all the possible states.
        $this->sourcetree->states = (object) array_combine(
            // All the labels.
            engine::STATUS_LABELS,
            // Filled with null.
            array_fill(0, count(engine::STATUS_LABELS), null)
        );
        // TODO outputs?
    }

    /**
     * Localise this step.
     *
     * @param bool $set
     */
    public function localise(bool $set = true) {
        $this->root->localise($set ? $this->name : null);
    }

    /**
     * Sets a variable in the tree.
     *
     * @param string $name The name of the variable in dot format, relative to this tree's root (e.g. 'config.destination').
     * @param mixed $value.
     */
    public function set(string $name, $value) {
        parent::set($name, $value);
        $this->root->invalidate();
    }

    /**
     * Get a variable's value with expressions resolved.
     *
     * @param string $name The name of the variable using dot format, relative to the step root. (e.g. vars.abc).
     * @return mixed The value, or null if the variable is not defined.
     */
    public function get_resolved(string $name) {
        return $this->root->get_resolved("steps.$this->name.$name");
    }
}
