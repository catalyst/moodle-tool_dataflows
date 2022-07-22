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
use tool_dataflows\local\step\flow_logic_case;
use tool_dataflows\local\step\writer_stream;
use tool_dataflows\local\execution\array_in_type;

// This is needed. File will not be automatically included.
require_once(__DIR__ . '/local/execution/array_in_type.php');

/**
 * Unit tests for flow_logic_case step.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_flow_logic_case_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Dataflow creation helper function
     *
     * @return  array of the resulting dataflow and steps in the format [dataflow, steps]
     */
    public function create_dataflow() {
        $dataflow = new dataflow();
        $dataflow->name = 'case step test';
        $dataflow->enabled = true;
        $dataflow->save();

        /*
         * To test case steps, we ideally want at least a branch in the flow,
         * that is more than one level deep on both sides, and a check to ensure
         * the results are different. Note there is a 'reader' before the case step.
         *
         * ┌────────►  number even ───────► even.json
         * Case
         * └────────►  number odd  ───────► odds.json
         *
         * As shown in the example above.
        */

        $steps = [];

        // Reader step.
        $reader = new step();
        $reader->name = 'reader';
        $reader->type = array_in_type::class;
        $dataflow->add_step($reader);

        // Define the reader records / inputs.
        $source = [];
        // Set up the reader with 5 ones, and 4 zeros. Then randomise the order because ultimately, it shouldn't matter.
        array_push($source, ...array_fill(0, 5, 1));
        array_push($source, ...array_fill(0, 4, 0));

        // Randomise the order - since it shouldn't matter.
        shuffle($source);

        // Convert the flat array into an array of objects, holding the key of 'value', for the reader.
        array_in_type::$source = array_map(function ($value) {
            return ['value' => $value];
        }, $source);

        // Case step.
        $case = new step();
        $case->name = 'case';
        $case->type = flow_logic_case::class;
        $case->config = Yaml::dump([
            'cases' => [
                'even numbers' => 'record["value"] % 2 == 0',
                'odd numbers' => 'record["value"] % 2 == 1',
            ],
        ]);
        $case->depends_on([$reader]);
        $dataflow->add_step($case);
        $steps[$case->id] = $case;

        // Writer step for evens.
        $even = new step();
        $even->name = 'even (writer)';
        $even->type = writer_stream::class;
        $even->config = Yaml::dump([
            'streamname' => 'even.json',
            'format' => 'json',
        ]);
        $even->depends_on(['case' . step::DEPENDS_ON_POSITION_SPLITTER . '1']);
        $dataflow->add_step($even);
        $steps[$even->id] = $even;

        // Writer step for odds.
        $odd = new step();
        $odd->name = 'odd (writer)';
        $odd->type = writer_stream::class;
        $odd->config = Yaml::dump([
            'streamname' => 'odd.json',
            'format' => 'json',
        ]);
        $odd->depends_on(['case' . step::DEPENDS_ON_POSITION_SPLITTER . '2']);
        $dataflow->add_step($odd);
        $steps[$odd->id] = $odd;

        return [$dataflow, $steps];
    }

    /**
     * Test case step is processed as expected
     *
     * @covers \tool_dataflows\local\step\flow_logic_case
     */
    public function test_path_is_resolved_as_expected() {
        [$dataflow, $steps] = $this->create_dataflow();
        $isdryrun = false;
        $this->assertTrue($dataflow->validate_dataflow());

        ob_start();
        $engine = new engine($dataflow, $isdryrun);
        $engine->execute();
        ob_get_clean();

        $odd = $engine->resolve_path('odd.json');
        $odd = file_get_contents($odd);
        $odd = json_decode($odd);
        $even = $engine->resolve_path('even.json');
        $even = file_get_contents($even);
        $even = json_decode($even);

        $even = array_column($even, 'value');
        $this->assertCount(4, $even);
        $this->assertEquals(0, array_sum($even));
        $odd = array_column($odd, 'value');
        $this->assertCount(5, $odd);
        $this->assertEquals(5, array_sum($odd));
    }
}
