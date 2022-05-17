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
 * Pulls the data along a flow block.
 *
 * A flow block is group of contiguously connected flow blocks. In order for the iterator chain to
 * operate, there needs to be something to pull the data. This class fills that role.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow_puller implements downstream {

    /** @var dataflow_executor */
    protected $dataflow;

    /** @var array|upstream The sinks of the flow block */
    protected $flowsinks = [];

    public function __construct(dataflow_executor $dataflow, array $flowsinks) {
        $this->dataflow = $dataflow;
        foreach ($flowsinks as $flowsink) {
            $this->flowsinks[$flowsink->stepdef->id] = new upstream($flowsink);
            $flowsink->downstreams['puller'] = $this;
        }
    }

    public function on_cancel(step_executor $step) {}

    public function on_finished(step_executor $step) {}

    /**
     * Called when the upstreams are ready to flow. If all upstreams are OK,
     * then this object will start pulling values from the streams.
     *
     * @param step_executor $step
     * @param iterator $iterator
     */
    public function on_proceed(step_executor $step, iterator $iterator) {
        $this->flowsinks[$step->get_id()]->status = dataflow_executor::STATUS_FLOWING;
        $this->flowsinks[$step->get_id()]->iterator = $iterator;
        // TODO: check that all upstreams are green.

        while (!$iterator->is_finished()) {
            $iterator->next();
        }
    }
}
