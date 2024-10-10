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

use tool_dataflows\local\step\connector_directory_file_count;

/**
 * Test for connector_directory_file_count step.
 *
 * @covers    \tool_dataflows\local\step\connector_directory_file_count
 * @package   tool_dataflows
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright Catalyst IT, 2022
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_connector_directory_file_count_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Description of what this does
     *
     * @param   string $name
     * @return  string $path
     */
    private function create_temp_directory($name) {
        global $CFG;

        $basedir = sys_get_temp_dir();
        $folderpath = $basedir . DIRECTORY_SEPARATOR . $name;
        if (!file_exists($folderpath)) {
            mkdir($folderpath, $CFG->directorypermissions, true);
        }
        return $folderpath;
    }

    /**
     * Test file dump step.
     *
     * @param int $expected
     * @dataProvider count_provider
     */
    public function test_file_counts_for_directory($expected) {
        $directory = $this->create_temp_directory('tool_dataflows_' . __NAMESPACE__);
        for ($i = 0; $i < $expected; ++$i) {
            tempnam($directory, 'tool_dataflows');
        }
        $step = new connector_directory_file_count;
        $count = $step->run($directory);
        $this->assertEquals($expected, $count);

        // Clean up and delete directory.
        remove_dir($directory, true);
    }

    /**
     * Test that a directory that does not exist also returns zero.
     */
    public function test_non_existent_directory_also_returns_zero() {
        $step = new connector_directory_file_count;
        $count = $step->run('/this/path/should/not/exist');
        $this->assertEquals(0, $count);
    }

    /**
     * Number of files to create and test against.
     *
     * @return  array of expected counts
     */
    public static function count_provider(): array {
        return [
            [0],
            [10],
            [5],
            [2],
        ];
    }
}
