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
use tool_dataflows\step;
use tool_dataflows\dataflow;

/**
 * tests some of the code around concurrency.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_concurrency_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests dataflow concurrency functions.
     *
     * @covers \tool_dataflows\dataflow::is_concurrency_enabled
     * @covers \tool_dataflows\dataflow::is_concurrency_supported
     * @covers \tool_dataflows\local\step\reader_sql::is_concurrency_supported
     */
    public function test_is_concurrency_supported() {
        [$dataflow, $reader] = $this->create_dataflow();

        $this->assertTrue($dataflow->is_concurrency_supported());
        $this->assertFalse($dataflow->is_concurrency_enabled());

        $dataflow->concurrencyenabled = true;
        $this->assertTrue($dataflow->is_concurrency_supported());
        $this->assertTrue($dataflow->is_concurrency_enabled());

        $reader->config = Yaml::dump(['sql' => "SELECT 1", 'counterfield' => 'id']);
        $this->assertNotTrue($dataflow->is_concurrency_supported());
        $this->assertFalse($dataflow->is_concurrency_enabled());
        $this->assertEquals(get_string('reader_sql:counterfield_not_empty', 'tool_dataflows'),
                $reader->steptype->is_concurrency_supported());
    }

    /**
     * Create a dataflow for testing.
     *
     * @return array
     */
    protected function create_dataflow(): array {
        // Create the dataflow.
        $dataflow = new dataflow();
        $dataflow->name = 'concurrent';
        $dataflow->enabled = true;
        $dataflow->save();

        $reader = new step();
        $reader->name = 'reader';
        $reader->type = 'tool_dataflows\local\step\reader_sql';

        $reader->config = Yaml::dump(['sql' => "SELECT 1"]);
        $dataflow->add_step($reader);

        return [$dataflow, $reader];
    }
}
