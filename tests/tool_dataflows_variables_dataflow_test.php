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
use tool_dataflows\local\execution;
use tool_dataflows\local\execution\array_in_type;
use tool_dataflows\local\execution\array_out_type;
use tool_dataflows\local\execution\engine;
use tool_dataflows\local\step\connector_wait;
use tool_dataflows\local\step\connector_debugging;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/test_dataflows.php');
require_once(__DIR__ . '/application_trait.php');
require_once(__DIR__ . '/local/execution/array_in_type.php');
require_once(__DIR__ . '/local/execution/array_out_type.php');

/**
 * Test the dataflows use of variables.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_variables_dataflow_test extends \advanced_testcase {
    use application_trait;

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests basic functionality of variables_root.
     *
     * @covers \tool_dataflows\local\variables\var_root
     * @covers \tool_dataflows\local\variables\var_dataflow
     * @covers \tool_dataflows\local\variables\var_step
     */
    public function test_basic_functionality() {
        $dataflow = new dataflow();
        $dataflow->name = 'basic';
        $dataflow->enabled = true;
        $dataflow->save();

        $wait = new step();
        $wait->name = 'wait';
        $wait->type = 'tool_dataflows\local\step\connector_wait';
        $wait->set('config', Yaml::dump(['timesec' => 1]));
        $dataflow->add_step($wait);

        $vars = $dataflow->get_variables_root();
        $this->assertEquals(1, $vars->get('steps.wait.config.timesec'));
        $this->assertEquals('wait', $vars->get('steps.wait.name'));
        $this->assertEquals('basic', $vars->get('dataflow.name'));

        $dfvars = $vars->get_dataflow_variables();
        $this->assertEquals('basic', $dfvars->get('name'));

        $stepvars = $vars->get_step_variables('wait');
        $this->assertEquals(1, $stepvars->get('config.timesec'));
        $stepvars->set('vars.dunno', '${{dataflow.name}}');
        $this->assertEquals('basic', $vars->get('steps.wait.vars.dunno'));

        $vars->set('dataflow.name', 'advanced');
        $this->assertEquals('advanced', $vars->get('steps.wait.vars.dunno'));
    }

    /**
     * Tests variable handling through a dataflow execution.
     *
     * @covers \tool_dataflows\local\variables\var_root
     * @covers \tool_dataflows\local\variables\var_dataflow
     * @covers \tool_dataflows\local\variables\var_step
     */
    public function test_execution() {
        $dataflow = new dataflow();
        $dataflow->name = 'basic';
        $dataflow->enabled = true;
        $dataflow->save();

        $wait = new step();
        $wait->name = 'wait';
        $wait->type = connector_wait::class;
        $wait->set('config', Yaml::dump(['timesec' => 1]));
        $dataflow->add_step($wait);

        $debug = new step();
        $debug->name = 'dbg';
        $debug->type = connector_debugging::class;
        $debug->depends_on([$wait]);
        $debug->set('vars', Yaml::dump(['waittime' => '${{steps.wait.config.timesec}}']));
        $dataflow->add_step($debug);

        $reader = new step();
        $reader->name = 'reader';
        $reader->type = 'tool_dataflows\local\execution\array_in_type';
        $reader->depends_on([$debug]);
        $dataflow->add_step($reader);

        $writer = new step();
        $writer->name = 'writer';
        $writer->type = 'tool_dataflows\local\execution\array_out_type';
        $writer->set('vars', Yaml::dump(['c' => '${{record.c}}']));

        $writer->depends_on([$reader]);
        $dataflow->add_step($writer);

        // Define the input.
        $json = '[{"a": 1, "b": 2, "c": 3}, {"a": 4, "b": 5, "c": 6}]';

        array_in_type::$source = json_decode($json);
        array_out_type::$dest = [];

        // Init the engine.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_end_clean();

        $vars = $dataflow->get_variables_root();
        $this->assertEquals(1, $vars->get('steps.dbg.vars.waittime'));

        // Variable vars.c should be set every iteration. We test for the final iteration.
        $this->assertEquals(6, $vars->get('steps.writer.vars.c'));
    }

    /**
     * Test that the dataflows vars persist after an execution.
     *
     * @covers \tool_dataflows\local\variables\var_dataflow::persist
     */
    public function test_persistence() {
        [$dataflow, $steps] = test_dataflows::array_in_array_out();

        // Define the input.
        $json = '[{"a": 1, "b": 2, "c": 3}, {"a": 4, "b": 5, "c": 6}]';

        array_in_type::$source = json_decode($json);
        array_out_type::$dest = [];
        $dataflow->set('vars', Yaml::dump(['a' => '${{steps.writer.record.c}}']));

        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_end_clean();

        // Refresh the dataflow from the database.
        $dataflow->read();
        $vars = $dataflow->vars;
        $this->assertEquals(6, $vars->a);
    }
}
