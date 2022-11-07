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

use tool_dataflows\local\step\trigger_event;
use tool_dataflows\local\event_processor;

/**
 * Unit test for the Moodle event reader step.
 *
 * @package   tool_dataflows
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_trigger_event_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('permitted_dirs', '/tmp', 'tool_dataflows');
    }

    /**
     * Test validate_config().
     *
     * @covers \tool_dataflows\local\step\trigger_event::validate_config
     */
    public function test_validate_config() {
        $reader = new trigger_event();

        // Test a valid configuration.
        $config = (object) [
            'eventname' => '\core\event\course_viewed',
            'executionpolicy' => event_processor::EXECUTE_IMMEDIATELY
        ];
        $this->assertTrue($reader->validate_config($config));

        // Test missing event name.
        $config = (object) [
            'executionpolicy' => event_processor::EXECUTE_IMMEDIATELY
        ];
        $result = $reader->validate_config($config);
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('config_eventname', $result);
        $this->assertEquals(get_string(
            'config_field_missing',
            'tool_dataflows',
            get_string('trigger_event:form:eventname', 'tool_dataflows'),
            true
        ), $result['config_eventname']);

        // Test missing execution policy.
        $config = (object) [
            'eventname' => '\core\event\course_viewed',
        ];
        $result = $reader->validate_config($config);
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('config_executionpolicy', $result);
        $this->assertEquals(get_string(
            'config_field_missing',
            'tool_dataflows',
            get_string('trigger_event:form:executionpolicy', 'tool_dataflows'),
            true
        ), $result['config_executionpolicy']);

        // Test invalid execution policy.
        $config = (object) [
            'eventname' => '\core\event\course_viewed',
            'executionpolicy' => 'foobar'
        ];
        $result = $reader->validate_config($config);
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('config_executionpolicy', $result);
        $this->assertEquals(get_string(
            'config_field_invalid',
            'tool_dataflows',
            get_string('trigger_event:form:executionpolicy', 'tool_dataflows'),
            true
        ), $result['config_executionpolicy']);
    }
}
