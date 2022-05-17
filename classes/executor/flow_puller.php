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
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow_puller implements downstream {

    protected $dataflow;
    protected $endflows;

    public function __construct(dataflow $dataflow, array $endflows) {
        $this->dataflow = $dataflow;
        $this->endflows = $endflows;
        foreach ($endflows as $endflow) {
            $this->endflows[$endflow->stepdef->id] = new upstream($endflow);
            $endflow->downstreams['puller'] = $this;
        }
    }

    public function on_cancel(step $step) {}
    public function on_finished(step $step) {}

    public function on_proceed(step $step, iterator $iterator) {
        $this->endflows[$step->get_id()]->status = dataflow::STATUS_FLOWING;
        $this->endflows[$step->get_id()]->iterator = $iterator;
        // TODO: check that all upstreams are green.

        while (!$iterator->is_finished()) {
            $iterator->next();
        }
    }
}
