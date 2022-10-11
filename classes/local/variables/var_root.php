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

use Symfony\Component\Yaml\Yaml;
use tool_dataflows\dataflow;
use tool_dataflows\helper;
use tool_dataflows\step;

/**
 * The root node of the variables tree.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class var_root extends var_object_visible {
    /**
     * Creates the variable tree.
     *
     * @param dataflow $dataflow
     */
    public function __construct(dataflow $dataflow) {
        parent::__construct($dataflow->name);

        // Define the top level trees, in order.
        $this->children['global'] = new var_object('global', $this);
        $this->children['dataflow'] = null; // Placeholder to ensure correct ordering.
        $steps = $this->find(['steps'], true, true);

        // Create the visible nodes first to ensure correct localisation.
        $this->children['dataflow'] = new var_dataflow($dataflow, $this);
        foreach ($dataflow->get_steps() as $step) {
            $steps->children[$step->alias] = new var_step($step, $steps, $this);
        }

        // Fill out the trees and initialise.
        $this->set('global.cfg', helper::get_cfg_vars());

        $vars = Yaml::parse(get_config('tool_dataflows', 'global_vars'), Yaml::PARSE_OBJECT_FOR_MAP)
            ?? new \stdClass();
        $this->set('global.vars', $vars);

        $this->children['dataflow']->init();
        foreach ($steps->children as $child) {
            $child->init();
        }

    }

    /**
     * Gets the var_object for the dataflow.
     *
     * @return var_dataflow
     */
    public function get_dataflow_variables(): var_dataflow {
        return $this->children['dataflow'];
    }

    /**
     * Gets the var_object for a step.
     *
     * @param string $alias The alias of the step.
     * @return var_step
     */
    public function get_step_variables(string $alias): var_step {
        return $this->children['steps']->children[$alias];
    }

    public function add_step(step $step) {
        $steps = $this->children['steps'];
        $steps->children[$step->alias] = new var_step($step, $steps, $this);
        $steps->children[$step->alias]->init();
    }
}
