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

use tool_dataflows\executor\dataflow_executor;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . "/array_in_type.php"); // This is needed. File will not be automatically included.
require_once(__DIR__ . "/array_out_type.php"); // This is needed. File will not be automatically included.

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
     * Tests the minimal ability for the execution engine. Reads in array data from a reader,
     * and passes it on to a writer.
     *
     * @throws \moodle_exception
     */
    public function test_in_and_out() {
        $this->resetAfterTest();

        // Crate the dataflow.
        $dataflow = new dataflow();
        $dataflow->name = 'two-step';
        $dataflow->save();

        $reader = new step();
        $reader->name = 'reader';
        $reader->type = 'tool_dataflows\array_in_type';
        $dataflow->add_step($reader);

        $writer = new step();
        $writer->name = 'writer';
        $writer->type = 'tool_dataflows\array_out_type';

        $writer->depends_on([$reader]);
        $dataflow->add_step($writer);

        // Define the input.
        $json = '[{"a": 1, "b": 2, "c": 3}, {"a": 4, "b": 5, "c": 6}]';

        array_in_type::$source = json_decode($json);
        array_out_type::$dest = [];

        // Execute.
        $executor = new dataflow_executor($dataflow);
        $executor->start();

        $this->assertEquals(array_in_type::$source, array_out_type::$dest);
    }
}
