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

/**
 * Unit tests for the helper class.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_helper_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests the get_permitted_dirs() function.
     *
     * @covers \tool_dataflows\helper::get_permitted_dirs
     */
    public function test_get_permitted_dirs() {
        global $CFG;

        set_config('permitted_dirs', "/home/me/tmp\n[dataroot]/tmp", 'tool_dataflows');
        $dirs = helper::get_permitted_dirs();
        $this->assertCount(2, $dirs);
        $this->assertEquals('/home/me/tmp', $dirs[0]);
        $this->assertEquals($CFG->dataroot . '/tmp', $dirs[1]);
    }
}
