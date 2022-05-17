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

namespace tool_dataflows\executor;

use tool_dataflows\executor\iterators\iterator;

/**
 * Execution manager for flow steps.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow_step_executor extends step_executor {
    public function is_flow(): bool {
        return true;
    }

    public function start() {
        $this->status = dataflow_executor::STATUS_WAITING;
        if (count($this->upstreams) == 0) {
            // No dependencies, therefore can immediately start.
            $this->begin_flow();
        } else {
            foreach ($this->upstreams as $upstream) {
                $upstream->step->start();
            }
        }
    }

    public function on_proceed(step_executor $step, iterator $iterator) {
        $this->upstreams[$step->get_id()]->status = dataflow_executor::STATUS_FLOWING;
        $this->upstreams[$step->get_id()]->iterator = $iterator;
        // TODO: check that all upstreams are green.
        $this->begin_flow();
    }

    public function on_cancel(step_executor $step) {
        $this->upstreams[$step->get_id()]->status = dataflow_executor::STATUS_CANCELLED;
        if ($this->check_all_cancelled()) {
            foreach ($this->downstreams as $downstream) {
                $downstream->on_cancel($this);
            }
        } else {
            $this->begin_flow();
        }
    }

    public function on_finished(step_executor $step) {
        $this->upstreams[$step->get_id()]->status = dataflow_executor::STATUS_FINISHED;
        $this->begin_flow();
    }

    /**
     * Prepares the iterators and signals to upstreams that it is ready to flow.
     */
    protected function begin_flow() {
        $this->status = dataflow_executor::STATUS_FLOWING;
        $iterator = $this->steptype->get_iterator($this);
        foreach ($this->downstreams as $downstream) {
            $downstream->on_proceed($this, $iterator);
        }
    }

    /**
     * Checks if all upsrteams have cancelled. If this is true, then this step is cancelled.
     * @return bool
     */
    protected function check_all_cancelled() {
        foreach ($this->upstreams as $upstream) {
            if ($upstream->status != dataflow_executor::STATUS_CANCELLED) {
                return false;
            }
        }
        return true;
    }

    public function abort() {
        $this->status = dataflow_executor::STATUS_ABORTED;
        foreach ($this->upstreams as $upstream) {
            $upstream->iterator->abort();
        }
    }
}
