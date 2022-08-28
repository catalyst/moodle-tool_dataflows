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

use tool_dataflows\local\scheduler;

/**
 * Unit tests for scheduler class.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_scheduler_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests the get_scheduled_times() function.
     *
     * @covers \tool_dataflows\local\scheduler::get_scheduled_times
     */
    public function test_get_scheduled_time() {
        global $DB;

        $this->assertEquals(false, scheduler::get_scheduled_times(23));

        $DB->insert_record(
            scheduler::TABLE,
            (object) ['dataflowid' => 23, 'stepid' => 10, 'lastruntime' => 123, 'nextruntime' => 150]
        );

        $this->assertEquals((object) ['lastruntime' => 123, 'nextruntime' => 150], scheduler::get_scheduled_times(10));
    }

    /**
     * Tests the set_scheduled_times() function.
     *
     * @covers \tool_dataflows\local\scheduler::set_scheduled_times
     */
    public function test_update_next_scheduled_time() {
        scheduler::set_scheduled_times(33, 11, 160, 120);
        $this->assertEquals((object) ['lastruntime' => 120, 'nextruntime' => 160], scheduler::get_scheduled_times(11));

        scheduler::set_scheduled_times(33, 11, 180);

        scheduler::set_scheduled_times(33, 12, 220, 160);
        $this->assertEquals((object) ['lastruntime' => 120, 'nextruntime' => 180], scheduler::get_scheduled_times(11));
        $this->assertEquals((object) ['lastruntime' => 160, 'nextruntime' => 220], scheduler::get_scheduled_times(12));
    }

    /**
     * Tests the get_due_dataflows() function.
     *
     * @covers \tool_dataflows\local\scheduler::get_due_dataflows
     */
    public function test_get_due_dataflows() {
        global $DB;

        $DB->insert_record(
            scheduler::TABLE,
            (object) ['dataflowid' => 23, 'stepid' => 1, 'lastruntime' => 123, 'nextruntime' => 150]
        );
        $DB->insert_record(
            scheduler::TABLE,
            (object) ['dataflowid' => 24, 'stepid' => 2, 'lastruntime' => 123, 'nextruntime' => 151]
        );
        $DB->insert_record(
            scheduler::TABLE,
            (object) ['dataflowid' => 25, 'stepid' => 3, 'lastruntime' => 123, 'nextruntime' => 152]
        );
        $DB->insert_record(
            scheduler::TABLE,
            (object) ['dataflowid' => 26, 'stepid' => 4, 'lastruntime' => 123, 'nextruntime' => 153]
        );

        $ids = scheduler::get_due_dataflows(152);
        $this->assertEquals(3, count($ids));
        $this->assertArrayHasKey(23, $ids);
        $this->assertArrayHasKey(24, $ids);
        $this->assertArrayHasKey(25, $ids);
    }
}
