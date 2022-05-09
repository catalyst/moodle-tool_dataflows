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

namespace tool_dataflows\executor;

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

        $step1 = new readers\php_iterator();
        $step2 = new writers\php_array();

        $source = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

        $step1->set_iterator(new \ArrayIterator($source));
        $step2->add_input($step1->add_output());

        $dataflow->add_step($step1);
        $dataflow->add_step($step2);

        $dataflow->find_endpoints();
        $this->assertTrue($dataflow->check_integrity());
        $dataflow->run_full();

        $this->assertEquals($source, $step2->output);
    }

    public function test_cloner() {
        $dataflow = new dataflow();

        $step1 = new readers\php_iterator();
        $step2 = new cloner();
        $step3 = new writers\php_array();
        $step4 = new writers\php_array();

        $source = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

        $step1->set_iterator(new \ArrayIterator($source));
        $step2->add_input($step1->add_output());
        $step3->add_input($step2->add_output());
        $step4->add_input($step2->add_output());

        $dataflow->add_step($step1);
        $dataflow->add_step($step2);
        $dataflow->add_step($step3);
        $dataflow->add_step($step4);

        $dataflow->find_endpoints();
        $this->assertTrue($dataflow->check_integrity());
        $dataflow->run_full();

        $this->assertEquals($source, $step3->output);
        $this->assertEquals($source, $step4->output);
    }

    public function test_zipper() {
        $dataflow = new dataflow();

        $step1 = new readers\php_iterator();
        $step2 = new readers\php_iterator();
        $step3 = new zipper();
        $step4 = new writers\php_array();

        $source1 = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        $source2 = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j'];
        $expected = [1, 'a', 2, 'b', 3, 'c', 4, 'd', 5, 'e', 6, 'f', 7, 'g', 8, 'h', 9, 'i', 10, 'j'];

        $step1->set_iterator(new \ArrayIterator($source1));
        $step2->set_iterator(new \ArrayIterator($source2));
        $step3->add_input($step1->add_output());
        $step3->add_input($step2->add_output());
        $step4->add_input($step3->add_output());

        $dataflow->add_step($step1);
        $dataflow->add_step($step2);
        $dataflow->add_step($step3);
        $dataflow->add_step($step4);

        $dataflow->find_endpoints();
        $this->assertTrue($dataflow->check_integrity());
        $dataflow->run_full();

        $this->assertEquals($expected, $step4->output);
    }

    public function test_splitter() {
        $dataflow = new dataflow();

        $step1 = new readers\php_iterator();
        $step2 = new splitter(['a', 'b', 'c']);
        $step3 = new writers\php_array();
        $step4 = new writers\php_array();

        $source = [
            (object) ['a' => 1, 'b' => 2, 'c' => 3],
            (object) ['a' => 4, 'b' => 5, 'c' => 6],
            (object) ['a' => 7, 'b' => 8, 'c' => 9],
        ];
        $expected1 = [
            (object) [1],
            (object) [4],
            (object) [7],
        ];
        $expected2 = [
            (object) [2],
            (object) [5],
            (object) [8],
        ];

        $step1->set_iterator(new \ArrayIterator($source));
        $step2->add_input($step1->add_output());
        $step3->add_input($step2->set_output('a'));
        $step4->add_input($step2->set_output('b'));

        $dataflow->add_step($step1);
        $dataflow->add_step($step2);
        $dataflow->add_step($step3);
        $dataflow->add_step($step4);

        $dataflow->find_endpoints();
        $this->assertTrue($dataflow->check_integrity());
        $dataflow->run_full();

        $this->assertEquals($expected1, $step3->output);
        $this->assertEquals($expected2, $step4->output);
    }
}
