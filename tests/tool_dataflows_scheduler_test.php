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
     * Tests the get_next_scheduled_time() ans get_last_scheduled_time() functions.
     */
    public function test_get_scheduled_time() {
        global $DB;

        $this->assertEquals(time(), scheduler::get_next_scheduled_time(2));
        $this->assertEquals(0, scheduler::get_last_scheduled_time(2));

        $DB->insert_record(scheduler::TABLE, (object) ['dataflowid' => 23, 'lastruntime' => 123, 'nextruntime' => 150]);
        $this->assertEquals(150, scheduler::get_next_scheduled_time(23));
        $this->assertEquals(123, scheduler::get_last_scheduled_time(23));

        $this->assertEquals((object)['lastruntime' => 123, 'nextruntime' => 150], scheduler::get_scheduled_times(23));
    }

    /**
     * Tests the determine_next_scheduled_time() function.
     */
    public function test_determine_next_scheduled_time() {
        $time = scheduler::determine_next_scheduled_time('+5 min', 170, 180);
        $this->assertEquals(470, $time);
        $time = scheduler::determine_next_scheduled_time('+5 min', 170, 800);
        $this->assertEquals(1070, $time);

        $time = scheduler::determine_next_scheduled_time('next tuesday');
        $this->assertEquals(strtotime('next tuesday'), $time);
    }

    /**
     * Tests the update_next_time() function.
     */
    public function test_update_next_scheduled_time() {
        scheduler::set_scheduled_times(33, 160, 120);
        $this->assertEquals(160, scheduler::get_next_scheduled_time(33));
        $this->assertEquals(120, scheduler::get_last_scheduled_time(33));

        scheduler::set_scheduled_times(33, 180);
        $this->assertEquals(180, scheduler::get_next_scheduled_time(33));
        $this->assertEquals(120, scheduler::get_last_scheduled_time(33));

        scheduler::set_scheduled_times(33, 220, 160);
        $this->assertEquals(220, scheduler::get_next_scheduled_time(33));
        $this->assertEquals(160, scheduler::get_last_scheduled_time(33));
    }

    /**
     * Tests the get_due_dataflwos() function.
     */
    public function test_get_due_dataflows() {
        global $DB;

        $DB->insert_record(scheduler::TABLE, (object) ['dataflowid' => 23, 'lastruntime' => 123, 'nextruntime' => 150]);
        $DB->insert_record(scheduler::TABLE, (object) ['dataflowid' => 24, 'lastruntime' => 123, 'nextruntime' => 151]);
        $DB->insert_record(scheduler::TABLE, (object) ['dataflowid' => 25, 'lastruntime' => 123, 'nextruntime' => 152]);
        $DB->insert_record(scheduler::TABLE, (object) ['dataflowid' => 26, 'lastruntime' => 123, 'nextruntime' => 153]);

        $ids = scheduler::get_due_dataflows(152);
        $this->assertEquals(3, count($ids));
        $this->assertTrue(in_array(23, $ids));
        $this->assertTrue(in_array(24, $ids));
        $this->assertTrue(in_array(25, $ids));
    }
}
