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

use Symfony\Component\Yaml\Yaml;
use tool_dataflows\local\execution\engine;
use tool_dataflows\local\step\reader_json;

defined('MOODLE_INTERNAL') || die();

// Include lib.php functions that aren't included automatically for Moodle 37- and below.
require_once(dirname(__FILE__) . '/../lib.php');

/**
 * Unit test for the Escape dot method.
 *
 * @package   tool_dataflows
 * @author    Peter Sistrom <petersistrom@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_dot_escape_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Data provider of serialized string.
     *
     * @return array
     */
    public function escape_dot_dataprovider() {
        return [
            ['Test name', 'Test name'],
            ['"', '\"'],
            [<<<'INPUT'
                '  "  \ / \\ SQL
             INPUT,
             <<<'EXPECTED'
                \'  \"  \\ / \\\\ SQL
             EXPECTED],
            [<<<'INPUT'
                jaVasCript:/*-/*`/*\`/*'/*"/**/(/* */oNcliCk=alert() )//%0D%0A%0d%0a//
             INPUT,
            <<<'EXPECTED'
                jaVasCript:/*-/*`/*\\`/*\'/*\"/**/(/* */oNcliCk=alert() )//%0D%0A%0d%0a//
             EXPECTED],
        ];
    }
    /**
     * Test which tables and column should be replaced.
     *
     * @dataProvider escape_dot_dataprovider
     * @covers ::escape_dot
     * @param string $input
     * @param string $expected
     */
    public function test_escape_dot(string $input, string $expected) {
        $dataflow = new dataflow();
        $this->assertSame($dataflow->escape_dot($input), $expected);
    }
}
