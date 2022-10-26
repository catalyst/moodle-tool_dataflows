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
use tool_dataflows\local\execution\array_in_type;
use tool_dataflows\local\execution\array_out_type;
use tool_dataflows\local\execution\engine;
use tool_dataflows\local\step\flow_abort;
use tool_dataflows\local\step\flow_set_variable;
use tool_dataflows\test_dataflows;

defined('MOODLE_INTERNAL') || die();

// This is needed. File will not be automatically included.
require_once(__DIR__ . '/test_dataflows.php');

/**
 * Unit tests for flow_set_variable step.
 *
 * @covers     \tool_dataflows\local\step\flow_set_variable
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_flow_set_variable_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();

        // Clear the static values which could have been set from previous tests.
        array_in_type::$source = [];
        array_out_type::$dest = [];

        $this->resetAfterTest();
    }

    /**
     * Ensure the variable step correctly sets variables as expected and persists them
     *
     * @covers \tool_dataflows\local\step\flow_set_variable
     */
    public function test_variable_set_step() {
        // Set up the dataflow sequence.
        [$dataflow, $steps] = test_dataflows::sequence([
            'reader' => array_in_type::class,
            'abort' => flow_abort::class,
            'set_variable' => flow_set_variable::class,
            'writer' => array_out_type::class,
        ]);

        // Initially set the "something" vars to zero.
        $dataflow->set_dataflow_vars(['something' => 0]);
        $dataflow->save();

        // For each iteration, set the "something" vars to the value held in the "createdAt" key of the record.
        $steps['set_variable']->config = Yaml::dump([
            'field' => 'dataflow.vars.something',
            'value' => '${{ record.b }}',
        ]);

        // Abort the flow if the previous run has already processed these "createdAt" values.
        // This is calculated with the assumption B values are unique, and will always be in ascending order.
        $steps['abort']->config = Yaml::dump(['condition' => '${{ record.b < dataflow.vars.something }}']);

        // Define the input for the first run.
        $json = '[{"a": 1, "createdAt": 2, "c": 3}, {"a": 1, "createdAt": 3, "c": 3}, {"a": 4, "createdAt": 5, "c": 6}]';
        array_in_type::$source = json_decode($json);

        // Execute.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        // Sanity check the first run went as expected.
        $this->assertEquals(json_encode(json_decode($json)), json_encode(array_out_type::$dest));
        $this->assertEquals(engine::STATUS_FINALISED, $engine->status);

        // Variable set step, check to ensure the last "createdAt" value has been recorded.
        $vars = $engine->get_variables_root();
        $this->assertEquals(5, $vars->get('dataflow.vars.something'));

        // Ensure it also has been persisted to the dataflows record (as we want this to be a value we use in subsequent runs).
        $tmpdataflow = new dataflow($dataflow->id);
        $this->assertEquals(5, $tmpdataflow->vars->something);

        // Run again (expect an early stop - e.g. it doesn't finalise as it would normally).
        array_out_type::$dest = [];
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();
        // This is indicated by nothing moving to the $dest writer bucket.
        $this->assertEquals(json_encode([]), json_encode(array_out_type::$dest));

        // Tweak the input, allowing for further processing. Check the new vars.
        $json = '[{"a": 1, "createdAt": 7, "c": 3}, {"a": 1, "createdAt": 9, "c": 3}, {"a": 4, "createdAt": 500, "c": 6}]';
        array_in_type::$source = json_decode($json);

        // Run again (expect it to finish).
        array_out_type::$dest = [];
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();
        $this->assertEquals(json_encode(json_decode($json)), json_encode(array_out_type::$dest));
        $this->assertEquals(engine::STATUS_FINALISED, $engine->status);

        $vars = $engine->get_variables_root();
        $this->assertEquals(500, $vars->get('dataflow.vars.something'));
    }

    /**
     * Ensure the variable step correctly sets variables as expected and persists them
     *
     * This time with date comparisons - replicating actual usage.
     *
     * @covers \tool_dataflows\local\step\flow_set_variable
     */
    public function test_variable_set_step_with_date_comparisons() {
        // Set up the dataflow sequence.
        [$dataflow, $steps] = test_dataflows::sequence([
            'reader' => array_in_type::class,
            'abort' => flow_abort::class,
            'set_variable' => flow_set_variable::class,
            'writer' => array_out_type::class,
        ]);

        // Initially set the "something" vars to zero.
        $dataflow->set_dataflow_vars(['something' => 0]);
        $dataflow->save();

        // For each iteration, set the "something" vars to the value held in the "createdAt" key of the record.
        $steps['set_variable']->config = Yaml::dump([
            'field' => 'dataflow.vars.something',
            'value' => '${{ record.createdAt }}',
        ]);

        // Abort the flow if the previous run has already processed these "createdAt" values.
        // This is calculated with the assumption B values are unique, and will always be in ascending order.
        $steps['abort']->config = Yaml::dump(['condition' => '${{ record.createdAt < dataflow.vars.something }}']);

        // Define the input for the first run.
        $json = '[{
            "a": 1,
            "createdAt": "2022-01-01T01:02:03"
        }, {
            "a": 1,
            "createdAt": "2022-01-02T01:02:03"
        }, {
            "a": 4,
            "createdAt": "2022-01-03T01:02:03"
        }]';
        array_in_type::$source = json_decode($json);

        // Execute.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        // Sanity check the first run went as expected.
        $this->assertEquals(json_encode(json_decode($json)), json_encode(array_out_type::$dest));
        $this->assertEquals(engine::STATUS_FINALISED, $engine->status);

        // Variable set step, check to ensure the last "createdAt" value has been recorded.
        $vars = $engine->get_variables_root();
        $this->assertEquals('2022-01-03T01:02:03', $vars->get('dataflow.vars.something'));

        // Ensure it also has been persisted to the dataflows record (as we want this to be a value we use in subsequent runs).
        $tmpdataflow = new dataflow($dataflow->id);
        $this->assertEquals('2022-01-03T01:02:03', $tmpdataflow->vars->something);

        // Run again (expect an early stop - e.g. it doesn't finalise as it would normally).
        array_out_type::$dest = [];
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();
        // This is indicated by nothing moving to the $dest writer bucket.
        $this->assertEquals(json_encode([]), json_encode(array_out_type::$dest));

        // Tweak the input, allowing for further processing. Check the new vars.
        $json = '[{
            "a": 1,
            "createdAt": "2022-02-01T01:02:03",
            "c": 3
        }, {
            "a": 1,
            "createdAt": "2022-03-01T01:02:03",
            "c": 3
        }, {
            "a": 4,
            "createdAt": "2022-12-01T01:02:03"
        }]';
        array_in_type::$source = json_decode($json);

        // Run again (expect it to finish).
        array_out_type::$dest = [];
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();
        $this->assertEquals(json_encode(json_decode($json)), json_encode(array_out_type::$dest));
        $this->assertEquals(engine::STATUS_FINALISED, $engine->status);

        $vars = $engine->get_variables_root();
        $this->assertEquals('2022-12-01T01:02:03', $vars->get('dataflow.vars.something'));
    }
}
