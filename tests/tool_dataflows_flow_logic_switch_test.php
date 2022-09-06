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
use tool_dataflows\local\step\flow_logic_switch;
use tool_dataflows\local\step\writer_stream;
use tool_dataflows\local\step\connector_debug_file_display;
use tool_dataflows\local\execution\array_in_type;
use tool_dataflows\local\service\step_service;

defined('MOODLE_INTERNAL') || die();

// This is needed. File will not be automatically included.
require_once(__DIR__ . '/local/execution/array_in_type.php');

/**
 * Unit tests for flow_logic_switch step.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_flow_logic_switch_test extends \advanced_testcase {

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
        $dataflow->name = 'switch step test';
        $dataflow->enabled = true;
        $dataflow->save();

        /*
         * To test switch steps, we ideally want at least a branch in the flow,
         * that is more than one level deep on both sides, and a check to ensure
         * the results are different. Note there is a 'reader' before the switch step.
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
        $switch = new step();
        $switch->name = 'switch';
        $switch->type = flow_logic_switch::class;
        $switch->config = Yaml::dump([
            'cases' => [
                'even numbers' => 'record["value"] % 2 == 0',
                'odd numbers' => 'record["value"] % 2 == 1',
            ],
        ]);
        $switch->depends_on([$reader]);
        $dataflow->add_step($switch);
        $steps[$switch->id] = $switch;

        // Writer step for evens.
        $even = new step();
        $even->name = 'even (writer)';
        $even->alias = 'even_writer';
        $even->type = writer_stream::class;
        $even->config = Yaml::dump([
            'streamname' => 'even.json',
            'format' => 'json',
        ]);
        $even->depends_on(['switch' . step::DEPENDS_ON_POSITION_SPLITTER . '1']);
        $dataflow->add_step($even);
        $steps[$even->id] = $even;

        // Writer step for odds.
        $odd = new step();
        $odd->name = 'odd (writer)';
        $odd->alias = 'odd_writer';
        $odd->type = writer_stream::class;
        $odd->config = Yaml::dump([
            'streamname' => 'odd.json',
            'format' => 'json',
        ]);
        $odd->depends_on(['switch' . step::DEPENDS_ON_POSITION_SPLITTER . '2']);
        $dataflow->add_step($odd);
        $steps[$odd->id] = $odd;

        return [$dataflow, $steps];
    }

    /**
     * Test switch step is processed as expected
     *
     * @covers \tool_dataflows\local\step\flow_logic_switch
     */
    public function test_path_is_resolved_as_expected() {
        [$dataflow] = $this->create_dataflow();
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

    /**
     * Test switch step is resolving matched cases in the expected order.
     *
     * @covers \tool_dataflows\local\step\flow_logic_switch
     */
    public function test_case_matching_happens_in_order() {
        [$dataflow] = $this->create_dataflow();
        $isdryrun = false;
        $this->assertTrue($dataflow->validate_dataflow());

        $stepsbyalias = $dataflow->get_steps();
        $switch = $stepsbyalias->switch;
        $switch->config = Yaml::dump([
            'cases' => [
                'even numbers' => '1',
                'odd numbers' => '1',
            ],
        ]);

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

        // Everything should sink into the evens file, as it is the first matching case (in order).
        $even = array_column($even, 'value');
        $this->assertCount(9, $even);
        $this->assertEquals(5, array_sum($even));
        $odd = array_column($odd, 'value');
        $this->assertCount(0, $odd);
        $this->assertEquals(0, array_sum($odd));
    }

    /**
     * Description of what this does
     *
     * @covers \tool_dataflows\local\service\step_service
     */
    public function test_flows_in_same_flow_group() {
        [$dataflow] = $this->create_dataflow();

        $stepsbyalias = $dataflow->get_steps();
        $oddwriter = $stepsbyalias->odd_writer;
        $evenwriter = $stepsbyalias->even_writer;

        // Add a connector and another flow group in a downstream branch.
        $dump = new step();
        $dump->name = 'dump step';
        $dump->alias = 'dump';
        $dump->type = connector_debug_file_display::class;
        $dump->config = Yaml::dump(['streamname' => 'even.json']);
        $dump->depends_on([$oddwriter]);
        $dataflow->add_step($dump);
        $steps[$dump->id] = $dump;

        // Add another flow (SQL -> SQL Writer).
        $sql = new step();
        $sql->name = 'sql';
        $sql->type = 'tool_dataflows\local\step\reader_sql';

        // Set the SQL query via a YAML config string.
        $sql->config = Yaml::dump([
            'sql' => 'SELECT 1',
            'counterfield' => 'value',
            'countervalue' => '',
        ]);
        $sql->depends_on([$dump]);
        $dataflow->add_step($sql);
        $steps[$sql->id] = $sql;

        // Add a writer for the SQL.
        $sqlwriter = new step();
        $sqlwriter->name = 'sqlwriter';
        $sqlwriter->alias = 'sqlwriter';
        $sqlwriter->type = writer_stream::class;
        $sqlwriter->config = Yaml::dump([
            'streamname' => 'sql.json',
            'format' => 'json',
        ]);
        $sqlwriter->depends_on([$sql]);
        $dataflow->add_step($sqlwriter);
        $steps[$sqlwriter->id] = $sqlwriter;

        ob_start();
        $isdryrun = false;
        $engine = new engine($dataflow, $isdryrun);
        $engine->execute();
        ob_get_clean();

        // Check and ensure it executes as expected.
        $this->assertEquals(engine::STATUS_FINALISED, $engine->status);

        // Test flow groups.
        $stepservice = new step_service;
        // Odd writer and even writer ARE part of the same flow group.
        $this->assertTrue(
            $stepservice->is_part_of_same_flow_group(
                $evenwriter->steptype->get_engine_step(),
                $oddwriter->steptype->get_engine_step()
            )
        );
        // Different order.
        $this->assertTrue(
            $stepservice->is_part_of_same_flow_group(
                $oddwriter->steptype->get_engine_step(),
                $evenwriter->steptype->get_engine_step()
            )
        );

        // The new flow group created in this test is NOT part of the same flow group as the original.
        $this->assertFalse(
            $stepservice->is_part_of_same_flow_group(
                $sql->steptype->get_engine_step(),
                $oddwriter->steptype->get_engine_step()
            )
        );
        $this->assertFalse(
            $stepservice->is_part_of_same_flow_group(
                $oddwriter->steptype->get_engine_step(),
                $sqlwriter->steptype->get_engine_step()
            )
        );

        // The new flow group is a valid flow group in of itself.
        $this->assertTrue(
            $stepservice->is_part_of_same_flow_group(
                $sql->steptype->get_engine_step(),
                $sqlwriter->steptype->get_engine_step()
            )
        );
    }

    /**
     * Test switch step is resolving no matched cases.
     *
     * @covers \tool_dataflows\local\step\flow_logic_switch
     */
    public function test_switch_no_matching_cases() {
        [$dataflow] = $this->create_dataflow();
        $isdryrun = false;
        $this->assertTrue($dataflow->validate_dataflow());

        $stepsbyalias = $dataflow->get_steps();
        $switch = $stepsbyalias->switch;
        $switch->config = Yaml::dump([
            'cases' => [
                'even numbers' => 'record["value"] % 2 == 1.5',
                'odd numbers' => 'record["value"] % 2 == 1',
            ],
        ]);

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

        // Even numbers don't match any case file should be empty, odd file should contain 5 1s.
        $even = array_column($even, 'value');
        $this->assertCount(0, $even);
        $this->assertEquals(0, array_sum($even));
        $odd = array_column($odd, 'value');
        $this->assertCount(5, $odd);
        $this->assertEquals(5, array_sum($odd));

        $this->assertEquals(engine::STATUS_FINALISED, $engine->status);
    }

    /**
     * Test that variables outside of the flow group can be utilised in the expressions if needed
     *
     * @covers \tool_dataflows\local\step\flow_logic_switch
     */
    public function test_switch_variables_available_from_full_context() {
        [$dataflow] = $this->create_dataflow();
        $isdryrun = false;
        $this->assertTrue($dataflow->validate_dataflow());

        $stepsbyalias = $dataflow->get_steps();
        $stepsbyalias->reader->config = Yaml::dump(['somefield' => 22]);

        $switch = $stepsbyalias->switch;
        $switch->config = Yaml::dump([
            'cases' => [
                'even numbers' => 'steps.reader.config.somefield == 22',
                'odd numbers' => '0',
            ],
        ]);

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

        // Everything should be thrown into even.json.
        $even = array_column($even, 'value');
        $this->assertCount(9, $even);
        $this->assertEquals(5, array_sum($even));
        $odd = array_column($odd, 'value');
        $this->assertCount(0, $odd);
        $this->assertEquals(0, array_sum($odd));

        $this->assertEquals(engine::STATUS_FINALISED, $engine->status);
    }
}
