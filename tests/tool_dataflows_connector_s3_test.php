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

use tool_dataflows\local\step\connector_s3;

/**
 * Unit tests for connector s3.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_connector_s3_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test path s3 matching is okay
     *
     * @covers \tool_dataflows\local\step\connector_s3
     * @dataProvider path_in_s3_data_provider
     * @param string $path
     * @param bool $isins3
     */
    public function test_path_is_in_s3_or_not(string $path, bool $isins3) {
        $steptype = new connector_s3;
        $this->assertEquals($isins3, $steptype->has_s3_path($path));
    }

    /**
     * Data provider for tests.
     *
     * @return array
     */
    public function path_in_s3_data_provider(): array {
        return [
            ['s3://path/to/file', true],
            ['s3://path/to/', true],
            ['path/to/file', false],
            ['path', false],
        ];
    }
}
