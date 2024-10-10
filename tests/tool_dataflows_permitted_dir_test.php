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

use tool_dataflows\admin\admin_setting_permitted_directories;

defined('MOODLE_INTERNAL') || die();

// Needed because CI complains.
global $CFG;
require_once($CFG->libdir.'/adminlib.php');

/**
 * Tests the admin_setting_permitted_directories class.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_permitted_dir_test extends \advanced_testcase {

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
        $setting = new admin_setting_permitted_directories('tool_dataflows/permitted_dirs', '', '', '');
        $this->assertTrue($setting->validate($data));
    }

    /**
     * Provides valid data.
     *
     * @return \string[][]
     */
    public static function valid_data_provider(): array {
        return [
            [''],
            ['/* A comment */'],
            ['/tmp' . PHP_EOL . PHP_EOL . '/var'],
            ['/tmp'],
            ['/tmp ' . PHP_EOL . '  ' . PHP_EOL . '[dataroot]/var ' . PHP_EOL],
            ['file:///tmp'],
            ['# a comment'],
            ['/var/tmp  # another comment'],
        ];
    }

    /**
     * Test validate with invalid values.
     *
     * @dataProvider invalid_data_provider
     * @covers \tool_dataflows\admin_setting_permitted_directories::validate
     * @param string $data
     * @param string $error
     */
    public function test_validate_invalid(string $data, string $error) {
        $setting = new admin_setting_permitted_directories('tool_dataflows/permitted_dirs', '', '', '');
        $result = $setting->validate($data);
        $this->assertEquals(get_string($error, 'tool_dataflows', $data), $result);
    }

    /**
     * Provides invalid data, along with the error lang index.
     *
     * @return \string[][]
     */
    public static function invalid_data_provider(): array {
        return [
            ['/tmp/* A comment', 'path_invalid'],
            ['http://tmp', 'path_invalid'],
            ['tmp', 'path_not_absolute'],
        ];
    }
}
