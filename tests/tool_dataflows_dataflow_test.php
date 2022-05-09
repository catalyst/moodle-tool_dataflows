<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Unit test for dataflow
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_dataflow_test extends \advanced_testcase {

    public function test_basic() {
        $dataflow = new dataflow();

        $step1 = new sources\php_iterator();
        $step2 = new loaders\php_array();

        $source = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

        $step1->set_iterator(new \ArrayIterator($source));
        $step2->add_input($step1->add_output());

        $dataflow->add_step($step1);
        $dataflow->add_step($step2);

        $dataflow->find_endpoints();
        $dataflow->run_full();

        $this->assertEquals($source, $step2->output);
    }
}
