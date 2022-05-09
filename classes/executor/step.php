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

namespace tool_dataflows\executor;

/**
 * Base class for execution steps.
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

    protected $mininputs = 1;
    protected $maxinputs = 1;

    protected $minoutputs = 1;
    protected $maxoutputs = 1;
    protected $outputids = [];

    public function num_outputs() {
        return count($this->outputs);
    }

    public function num_inputs() {
        return count($this->inputs);
    }

    public function add_output(): ?output_port {
        if (is_null($this->maxoutputs) || (count($this->outputs) < $this->maxoutputs)) {
            $output = new output_port($this);
            $this->outputs[] = $output;
            end($this->outputs);
            $output->id = key($this->outputs);
            return $output;
        } else {
            return null; // TODO: how to handle violations.
        }
    }

    public function set_output($id): ?output_port {
        if (in_array($id, $this->outputids)) {
            $output = new output_port($this);
            $this->outputs[$id] = $output;
            $output->id = $id;
            return $output;
        } else {
            return null; // TODO: how to handle violations.
        }
    }

    /**
     * Adds an input if the constraints are OK.
     * @param iterator $iter
     */
    public function add_input(iterator $iter) {
        if (is_null($this->maxinputs) || (count($this->inputs) < $this->maxinputs)) {
            $this->inputs[] = $iter;
        }
        // TODO: how to handle violations.
    }

    public function add_dependency(dependency $dep) {
        $this->dependencies[] = $dep;
    }

    public function are_dependencies_satisfied(): bool {
        foreach ($this->dependencies as $dependency) {
            if (!$dependency->is_ready()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns true if a value can be supplied to the output port identified by $id.
     *
     * @param $id - The id of the output port.
     * @return bool
     */
    public function is_ready($id): bool {
        if ($this->are_dependencies_satisfied()) {
            foreach ($this->inputs as $input) {
                if ($input->is_ready()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Returns true if no more items can be pulled.
     *
     * @return bool
     */
    public function is_empty(): bool {
        foreach ($this->inputs as $input) {
            if (!$input->is_empty()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Requests the next item in the iteration.
     *
     * @param $id - The id of the output port. Can be used to determine what output to give.
     * @return mixed
     */
    abstract public function next($id);

    public function check_integrity($visitedsteps) {
        if (in_array($this, $visitedsteps)) {
            return false;
        }
        $visitedsteps[] = $this;
        foreach ($this->inputs as $input) {
            if ($input->get_step()->check_integrity($visitedsteps) === false) {
                return false;
            };
        }
        return true;
    }
}
