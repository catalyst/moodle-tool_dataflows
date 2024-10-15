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

namespace tool_dataflows\local\execution;

use Symfony\Component\Yaml\Yaml;
use tool_dataflows\dataflow;
use tool_dataflows\step;
use tool_dataflows\test_dataflows;

defined('MOODLE_INTERNAL') || die();

// This is needed. File will not be automatically included.
require_once(__DIR__ . '/../../test_dataflows.php');

/**
 * Unit tests for the execution engine
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_basic_execution_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests the minimal ability for the execution engine. Reads in array data from a reader,
     * and passes it on to a writer.
     *
     * @covers \tool_dataflows\local\execution\engine::execute
     * @covers \tool_dataflows\local\execution\engine::execute_step
     * @covers \tool_dataflows\local\execution\engine::finalise
     * @covers \tool_dataflows\local\execution\engine::initialise
     * @throws \moodle_exception
     */
    public function test_in_and_out() {
        // Create the dataflow.
        [$dataflow, $steps] = test_dataflows::sequence([
            'reader' => array_in_type::class,
            'writer' => array_out_type::class,
        ]);

        // Define the input.
        $json = '[{"a": 1, "b": 2, "c": 3}, {"a": 4, "b": 5, "c": 6}]';

        array_in_type::$source = json_decode($json);
        array_out_type::$dest = [];

        // Execute.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $this->assertEquals(json_encode(array_in_type::$source), json_encode(array_out_type::$dest));
        $this->assertEquals(engine::STATUS_FINALISED, $engine->status);
    }

    /**
     * Tests using direct_in step type.
     *
     * @covers \tool_dataflows\local\execution\direct_in_type
     */
    public function test_direct_and_out() {
        // Create the dataflow.
        [$dataflow, $steps] = test_dataflows::sequence([
            'reader' => direct_in_type::class,
            'writer' => array_out_type::class,
        ]);

        // Define the input.
        $source = [
            ['a' => 9, 'b' => 8, 'c' => 7],
            ['a' => 6, 'b' => 5, 'c' => 4],
        ];

        $steps['reader']->config = Yaml::dump(['source' => $source]);
        array_out_type::$dest = [];

        // Execute.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $this->assertEquals(json_encode($source), json_encode(array_out_type::$dest));
        $this->assertEquals(engine::STATUS_FINALISED, $engine->status);
    }
}
