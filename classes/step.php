<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Base class for steps.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class step {
    protected $outputs = [];
    protected $inputs = [];
    protected $dependencies = [];

    public function num_outputs() {
        return count($this->outputs);
    }

    public function num_inputs() {
        return count($this->inputs);
    }

    public function add_output(): output_port {
        $output = new output_port($this);
        $this->outputs[] = $output;
        end($this->outputs);
        $output->id = key($this->outputs);
        return $output;
    }

    public function set_output($id): output_port {
        $output = new output_port($this);
        $this->outputs[$id] = $output;
        $output->id = $id;
        return $output;
    }

    public function add_input(iterator $iter) {
        $this->inputs[] = $iter;
    }

    public function add_dependency(dependency $dep) {
        $this->dependencies[] = $dep;
    }

    function are_dependencies_satisfied(): bool {
        foreach ($this->dependencies as $dependency) {
            if (!$dependency->is_ready()) {
                return false;
            }
        }
        return true;
    }
    
    public function is_ready(): bool {
        if ($this->are_dependencies_satisfied()) {
            foreach ($this->inputs as $input) {
                if ($input->is_ready()) {
                    return true;
                }
            }
        }
        return false;
    }

    public function is_empty(): bool {
        foreach ($this->inputs as $input) {
            if (!$input->is_empty()) {
                return false;
            }
        }
        return true;
    }

    abstract public function next();
}
