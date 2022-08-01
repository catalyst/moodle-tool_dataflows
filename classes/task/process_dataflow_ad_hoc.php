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
use tool_dataflows\local\execution\engine;

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

        try {
            $dataflow = new dataflow($dataflowrecord->dataflowid);
            if ($dataflow->enabled) {
                mtrace("Running dataflow $dataflow->name as ad-hoc task (ID: $dataflowrecord->dataflowid), time due: " .
                    userdate($dataflowrecord->nextruntime));
                $engine = new engine($dataflow, false);
                $engine->execute();
                $metadata = $engine->is_blocked();
                if ($metadata) {
                    mtrace("Dataflow $dataflow->name locked (ID: $dataflowrecord->dataflowid). Lock data, time: " .
                            userdate($metadata->timestamp) . ", process ID: $metadata->processid.");
                }
            }
        } catch (\Throwable $thrown) {
            mtrace("Dataflow run failed for ID: $dataflowrecord->dataflowid, " . $thrown->getMessage());
        }
    }
}
