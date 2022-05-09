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
 * A dataflow object, encompasing all steps and dependencies.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class dataflow {
    protected $steps = [];
    protected $endpoints = [];
    protected $currentendpoint;
    protected $integritystatus;

    public function add_step(step $step) {
        $this->steps[] = $step;
        $this->integritystatus = null;
    }
    
    public function check_integrity() {
        $this->integritystatus = true;
    }

    public function find_endpoints() {
        foreach ($this->steps as $step) {
            if ($step->num_outputs() == 0) {
                // Create a dummy output to facilitate pulling data.
                $this->endpoints[] = $step->add_output();
            }
        }
    }

    public function is_empty() {
        $isempty = true;
        foreach ($this->endpoints as $endpoint) {
            if (!$endpoint->is_empty()) {
                $isempty = false;
            }
        }
        return $isempty;
    }
    
    public function is_ready() {
        if ($this->integritystatus !== true) {
            return false;
        }
        foreach ($this->endpoints as $endpoint) {
            if ($endpoint->is_ready()) {
                return true;
            }
        }
        return false;
    }

    public function run_full() {
        while (!$this->is_empty()) {
            $this->run_round();
        }
    }

    public function run_round() {
        $this->currentendpoint = 0;
        while ($this->currentendpoint < count($this->endpoints)) {
            $this->run_turn();
        }
    }

    public function run_turn() {
        if ($this->endpoints[$this->currentendpoint]->is_ready()) {
            $this->endpoints[$this->currentendpoint]->next();
        }
        ++$this->currentendpoint;
    }
}

