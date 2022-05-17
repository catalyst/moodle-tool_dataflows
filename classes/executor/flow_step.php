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
class flow_step extends step {
    public function is_flow(): bool {
        return true;
    }

    public function start() {
        $this->status = dataflow::STATUS_WAITING;
        if (count($this->upstreams) == 0) {
            // No dependencies, therefore can immediately start.
            $this->flow();
        } else {
            foreach ($this->upstreams as $upstream) {
                $upstream->step->start();
            }
        }
    }

    public function on_proceed(step $step, iterator $iterator) {
        $this->upstreams[$step->get_id()]->status = dataflow::STATUS_FLOWING;
        $this->upstreams[$step->get_id()]->iterator = $iterator;
        // TODO: check that all upstreams are green.
        $this->flow();
    }

    public function on_cancel(step $step) {
        $this->upstreams[$step->get_id()]->status = dataflow::STATUS_CANCELLED;
        if ($this->check_all_cancelled()) {
            foreach ($this->downstreams as $downstream) {
                $downstream->on_cancel($this);
            }
        } else {
            $this->flow();
        }
    }

    public function on_finished(step $step) {
        $this->upstreams[$step->get_id()]->status = dataflow::STATUS_FINISHED;
        $this->flow();
    }

    protected function flow() {
        $this->status = dataflow::STATUS_FLOWING;
        $iterator = $this->steptype->get_iterator($this);
        foreach ($this->downstreams as $downstream) {
            $downstream->on_proceed($this, $iterator);
        }
    }

    protected function check_all_cancelled() {
        foreach ($this->upstreams as $upstream) {
            if ($upstream->status != dataflow::STATUS_CANCELLED) {
                return false;
            }
        }
        return true;
    }

    public function abort() {
        $this->status = dataflow::STATUS_ABORTED;
        foreach ($this->upstreams as $upstream) {
            $upstream->iterator->abort();
        }
    }
}
