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

namespace tool_dataflows\task;

use tool_dataflows\dataflow;
use tool_dataflows\local\execution\engine;
use tool_dataflows\local\scheduler;
use tool_dataflows\local\event_processor;
use tool_dataflows\task\process_dataflow_ad_hoc;

/**
 * Process queued dataflows.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_dataflows extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:processdataflows', 'tool_dataflows');
    }

    /**
     * Run the dataflows.
     */
    public function execute() {
        $scheduledrecords = scheduler::get_due_dataflows();
        $eventrecords = event_processor::get_flows_awaiting_run();

        $dataflowrecords = array_merge($scheduledrecords, $eventrecords);

        // Queue all dataflows to be run as adhoc tasks.
        foreach ($dataflowrecords as $record) {
            $dataflow = new dataflow($record->dataflowid);
            process_dataflow_ad_hoc::queue_adhoctask($dataflow);
        }
    }

    /**
     * Executes the given dataflow.
     *
     * @param int $dataflowid
     */
    public static function execute_dataflow(int $dataflowid) {
        try {
            $dataflow = new dataflow($dataflowid);
            if ($dataflow->enabled) {
                mtrace("Running dataflow $dataflow->name (ID: $dataflow->id)");
                $engine = new engine($dataflow, false);
                $engine->execute();
                $metadata = $engine->is_blocked();
                if ($metadata) {
                    $str = "Dataflow $dataflow->name locked (ID: $dataflow->id). Lock data, time: ".
                            userdate($metadata->timestamp) . ", process ID: $metadata->processid.";

                    if (isset($dataflow->nextruntime)) {
                        $str .= "(Next run time: $dataflow->nextruntime)";
                    }

                    mtrace($str);
                }
            }
        } catch (\Throwable $thrown) {
            mtrace("Dataflow run failed for ID: $dataflow->id, " . $thrown->getMessage());
        }
    }
}
