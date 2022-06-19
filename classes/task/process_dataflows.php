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
use tool_dataflows\manager;
use tool_dataflows\local\execution\engine;
use tool_dataflows\local\scheduler;
use tool_dataflows\local\step\trigger_cron;

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
     * Processes dataflows.
     */
    public function execute() {
        $dataflowids = scheduler::get_due_dataflows();
        foreach ($dataflowids as $id) {
            try {
                $dataflow = new dataflow($id);
                if ($dataflow->enabled) {
                    mtrace("Running dataflow $dataflow->name (ID: $id), time due: " . scheduler::get_next_scheduled_time($id));
                    $engine = new engine($dataflow, false);
                    $engine->execute();
                }
            } catch (\Throwable $thrown) {
                mtrace("Dataflow run failed for ID: $id, " . $thrown->getMessage());
            }
        }
    }
}
