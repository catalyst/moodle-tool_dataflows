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

use \tool_dataflows\admin\admin_setting_cfg_list;

/**
 * Tests the admin_setting_cfg_list class
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_cfg_list_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test validate with valid values.
     *
     * @dataProvider valid_data_provider
     * @covers \tool_dataflows\admin_setting_permitted_directories::validate
     * @param string $data
     */
    public function test_validate_valid(string $data) {
        $setting = new admin_setting_cfg_list('tool_dataflows/config_vars', '', '', '');
        $this->assertTrue($setting->validate($data));
    }

    /**
     * Provides valid data.
     *
     * @return \string[][]
     */
    public function valid_data_provider(): array {
        return [
            [''],
            ['/* A comment */'],
            ['# a comment'],
            ['wwwroot' . PHP_EOL . PHP_EOL . 'dataroot'],
        ];
    }

    /**
     * Test validate with invalid values.
     *
     * @dataProvider invalid_data_provider
     * @covers \tool_dataflows\admin_setting_permitted_directories::validate
     * @param string $data
     */
    public function test_validate_invalid(string $data) {
        $setting = new admin_setting_cfg_list('tool_dataflows/config_vars', '', '', '');
        $result = $setting->validate($data);
        $this->assertEquals(get_string('cfg_value_undefined', 'tool_dataflows', $data), $result);
    }

    /**
     * Provides invalid data, along with the error lang index.
     *
     * @return \string[][]
     */
    public function invalid_data_provider(): array {
        return [
            ['wwwroot /* A comment'],
            ['wwwrootx'],
        ];
    }
}
