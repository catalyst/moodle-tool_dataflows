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

use tool_dataflows\task\process_dataflow_ad_hoc;

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
     * Creates a dataflow for use with testing.
     *
     * @return object An object of the same format as scheduler::get_due_dataflows().
     */
    private function create_dataflow() {
        $dataflow = new dataflow();
        $dataflow->name = 'two-step';
        $dataflow->enabled = true;
        $dataflow->save();

        $reader = new step();
        $reader->name = 'reader';
        $reader->type = 'tool_dataflows\local\step\reader_sql';
        $reader->config = '{sql: SELECT 1}';
        $dataflow->add_step($reader);

        $writer = new step();
        $writer->name = 'writer';
        $writer->type = 'tool_dataflows\local\step\writer_debugging';

        $writer->depends_on([$reader]);
        $dataflow->add_step($writer);

        return (object) ['dataflowid' => $dataflow->id, 'nextruntime' => time() ];
    }
}
