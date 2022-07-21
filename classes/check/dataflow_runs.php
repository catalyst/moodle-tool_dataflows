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

namespace tool_dataflows\check;

use core\check\check;
use core\check\result;
use tool_dataflows\dataflow;
use tool_dataflows\local\execution\engine;

/**
 * Dataflows generic check
 *
 * @package     tool_dataflows
 * @author      Peter Sistrom <petersistrom@catalyst-au.net>
 * @copyright   Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dataflow_runs extends check {

    /**
     * Get the short check name
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('pluginname', 'tool_dataflows');
    }

    /**
     * Getter for a link to page with more information.
     *
     * @return \action_link|null
     */
    public function get_action_link(): ?\action_link {
        $url = new \moodle_url('/admin/dataflows/index.php');
        return new \action_link($url, get_string('pluginname', 'tool_dataflows'));
    }

    /**
     * Getter for the status of the check.
     *
     * @return result
     */
    public function get_result(): result {
        global $DB;

        $enableddataflows = $DB->get_records('tool_dataflows', ['enabled' => 1]);
        $status = result::OK;
        $details = '';
        $summary = '';
        $runs = false;

        foreach ($enableddataflows as $flow) {
            $dataflow = new dataflow($flow->id);
            $dataflowlink = \html_writer::link(new \moodle_url('/admin/tool/dataflows/view.php',
                ['id' => $dataflow->id]), $dataflow->name);

            // Get array of runs limited to 1, the most recent run.
            $lastrunarray = $dataflow->get_runs(1);
            if (empty($lastrunarray)) {
                // No runs exist for this dataflow, add info to details.
                $details .= $dataflowlink.': '.get_string('check:dataflows_no_runs', 'tool_dataflows');
                $details .= \html_writer::empty_tag('br');
                continue;
            }
            // Run exists, get run and create result link.
            $lastrun = $lastrunarray[0];
            $runs = true;
            $runstate = engine::STATUS_LABELS[$lastrun->status];
            $runresultlink = \html_writer::link(new \moodle_url('/admin/tool/dataflows/view-run.php',
                ['id' => $lastrun->id]), get_string('check:dataflows_run_status', 'tool_dataflows',
                ['name' => $lastrun->name, 'state' => $runstate]));

            if ($runstate === 'aborted') {
                // Last run aborted, return error and add run info to summary.
                $summary .= $dataflowlink.': '.$runresultlink.\html_writer::empty_tag('br');
                $status = result::ERROR;
            }
            // Add run info to details.
            $details .= $dataflowlink.': '.$runresultlink;
            $details .= \html_writer::empty_tag('br');
        }

        if (empty($enableddataflows)) {
            // No dataflows enabled.
            $summary = get_string('check:dataflows_not_enabled', 'tool_dataflows');
        } else if (!$runs) {
            // Dataflows enabled but no runs executed.
            $summary = get_string('check:dataflows_no_runs', 'tool_dataflows');
        } else if ($runs && $status == result::OK) {
            // Runs exist and result OK, i.e. no aborted runs.
            $summary = get_string('check:dataflows_completed_successfully', 'tool_dataflows');
        }

        return new result($status, $summary, $details);
    }
}
