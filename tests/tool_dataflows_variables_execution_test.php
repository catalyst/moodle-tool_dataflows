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

use tool_dataflows\local\execution\array_in_type;
use tool_dataflows\local\execution\array_out_type;
use tool_dataflows\local\execution\engine;
use tool_dataflows\local\execution\flow_callback_step;
use tool_dataflows\local\step\base_step;

defined('MOODLE_INTERNAL') || die();

// This is needed. File will not be automatically included.
require_once(__DIR__ . '/dataflows/test_dataflows.php');

/**
 * Tests the variables code within a dataflow execution.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_variables_execution_test extends \advanced_testcase {
    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_record() {
        $tester = $this;
        $callback = function ($inputs, base_step $step) use ($tester) {
            $tester->assertTrue(true);
            $tester->assertEquals($inputs, $step->get_engine_step()->get_variables()->get_resolved('record'));
        };

        [$dataflow, $steps] = test_dataflows::reader_callback_writer(
            array_in_type::class,
            array_out_type::class,
            $callback
        );

        // Define the input.
        $json = '[{"a": 1, "b": 2, "c": 3}, {"a": 4, "b": 5, "c": 6}]';

        array_in_type::$source = json_decode($json);
        array_out_type::$dest = [];

        // Execute.
        ob_start();
        $engine = new engine($dataflow);
        $this->assertEquals(engine::STATUS_NEW, $engine->status);
        $engine->execute();
        $this->assertEquals(engine::STATUS_FINALISED, $engine->status);
        ob_get_clean();

        $this->assertEquals(json_encode(array_in_type::$source), json_encode(array_out_type::$dest));
        $this->assertEquals(engine::STATUS_FINALISED, $engine->status);
    }
}

