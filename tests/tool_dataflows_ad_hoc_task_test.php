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

namespace tool_dataflows;

use core\task\manager;
use tool_dataflows\local\scheduler;
use tool_dataflows\task\process_dataflow_ad_hoc;
use tool_dataflows\task\process_dataflows;

/**
 * Tests the dataflow ad hoc task.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_ad_hoc_task_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test ad hoc task execution.
     *
     * @covers \tool_dataflows\task\process_dataflow_ad_hoc::execute
     */
    public function test_task_execute() {
        global $DB;

        $dataflowrecord = $this->create_dataflow();

        $task = new process_dataflow_ad_hoc();
        $task->set_custom_data($dataflowrecord);
        ob_start();
        $task->execute();
        $this->assertDebuggingCalledCount(1);
        ob_get_clean();

        // If it ran, there should be a record in the database.
        $this->assertNotFalse($DB->get_record('tool_dataflows_runs', ['dataflowid' => $dataflowrecord->dataflowid]));
    }

    /**
     * Tests adhoc tasks are created correctly by the scheduled task.
     *
     * @covers \tool_dataflows\task\process_dataflows::execute
     */
    public function test_cron_adhoc_task_creation() {
        // Create three CRON triggered dataflows.
        array_map(function($i) {
            return $this->create_dataflow();
        }, range(0, 2));

        // Initially there should be no adhoc tasks waiting.
        $this->assertEmpty(manager::get_adhoc_tasks(process_dataflow_ad_hoc::class));

        // Run the scheduled task, which should spawn adhoc tasks to process the three dataflows.
        $scheduledtask = new process_dataflows();
        $scheduledtask->execute();

        $this->assertCount(3, manager::get_adhoc_tasks(process_dataflow_ad_hoc::class));
    }

    /**
     * Creates a dataflow for use with testing.
     *
     * @return object An object of the same format as scheduler::get_due_dataflows().
     */
    private function create_dataflow() {
        $dataflow = new dataflow();
        $dataflow->name = 'two-step';
        $dataflow->enabled = true;
        $dataflow->save();

        $cron = new step();
        $cron->name = 'cron';
        $cron->type = 'tool_dataflows\local\step\trigger_cron';
        $cron->config = "minute: '*'\nhour: '*'\nday: '*'\nmonth: '*'\ndayofweek: '*'";
        $dataflow->add_step($cron);

        $reader = new step();
        $reader->name = 'reader';
        $reader->type = 'tool_dataflows\local\step\reader_sql';
        $reader->config = '{sql: SELECT 1}';
        $reader->depends_on([$cron]);
        $dataflow->add_step($reader);

        $writer = new step();
        $writer->name = 'writer';
        $writer->type = 'tool_dataflows\local\step\writer_debugging';

        $writer->depends_on([$reader]);
        $dataflow->add_step($writer);

        // Set CRON trigger scheduled time to the past, to ensure it is ready to run.
        scheduler::set_scheduled_times($dataflow->id, $cron->id, time() - 100, 0);

        return (object) ['dataflowid' => $dataflow->id];
    }
}
