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

namespace tool_dataflows\task;

use tool_dataflows\dataflow;

/**
 * Ad hoc task for running a dataflow.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_dataflow_ad_hoc extends \core\task\adhoc_task {

    /**
     * Run the dataflow.
     */
    public function execute() {
        $dataflowrecord = $this->get_custom_data();
        \tool_dataflows\task\process_dataflows::execute_dataflow($dataflowrecord->dataflowid);
    }

    /**
     * Create an ad-hoc task for the given dataflow record
     * and schedules for it to be run.
     *
     * @param object $dataflowrecord
     * @return void
     */
    public static function execute_from_record($dataflowrecord): void {
        $dataflow = new dataflow($dataflowrecord->dataflowid);
        $task = new process_dataflow_ad_hoc();
        $task->set_custom_data($dataflowrecord);

        // For concurrent tasks, queue them up as an independant adhoc task.
        if ($dataflow->is_concurrency_enabled()) {
            \core\task\manager::queue_adhoc_task($task);
            return;
        }

        // Only available 3.7 onwards.
        if (function_exists('\core\task\manager::reschedule_or_queue_adhoc_task')) {
            \core\task\manager::reschedule_or_queue_adhoc_task($task);
            return;
        }

        \core\task\manager::queue_adhoc_task($task);
    }
}
