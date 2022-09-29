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
use tool_dataflows\local\execution\variables_root;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/local/execution/reader_sql_variable_setter.php');
require_once(__DIR__ . '/application_trait.php');

/**
 * Tests the variabels functionality.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_variables_root_test extends \advanced_testcase {
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
     * @covers \tool_dataflows\local\execution\variables_root
     * @covers \tool_dataflows\local\execution\variables_dataflow
     * @covers \tool_dataflows\local\execution\variables_step
     */
    public function test_basic_functionality() {
        $dataflow = new dataflow();
        $dataflow->name = 'basic';
        $dataflow->enabled = true;
        $dataflow->save();

        $reader = new step();
        $reader->name = 'wait';
        $reader->type = 'tool_dataflows\local\step\connector_wait';
        $reader->set_var('timesec', 1);
        $dataflow->add_step($reader);

        $vars = new variables_root($dataflow);
        $this->assertEquals(1, $vars->get('steps.wait.config.timesec'));
        $this->assertEquals('wait', $vars->get('steps.wait.name'));

        $dfvars = $vars->get('dataflow');
        $this->assertEquals('basic', $dfvars->get('name'));

        $stepvars = $vars->get_step_variables('wait');
        $this->assertEquals(1, $stepvars->get('config.timesec'));
        $stepvars->set('vars.dunno', '${{dataflow.name}}');

        $this->assertEquals('basic', $vars->get_resolved('steps.wait.vars.dunno'));
        $vars->set('dataflow.name', 'advanced');
        $this->assertEquals('advanced', $vars->get_resolved('steps.wait.vars.dunno'));
    }

    /**
     * Test self referencing variables, (but with valid and complete values)
     *
     * @covers \tool_dataflows\local\execution\variables_root::get_tree
     */
    public function test_variable_parsing_involving_self_recursion() {
        // Create the dataflow.
        $dataflow = new dataflow();
        $dataflow->name = 'readwrite';
        $dataflow->enabled = true;
        $dataflow->save();

        $reader = new step();
        $reader->name = 'reader';
        $reader->type = execution\reader_sql_variable_setter::class;

        // Set the SQL query via a YAML config string.
        $reader->config = Yaml::dump([
            'sql' => 'select ${{ steps.reader.config.something + steps.reader.config.countervalue }}',
            'countervalue' => '${{ steps.reader.config.counterfield + steps.reader.config.counterfield }}',
            'counterfield' => '${{ steps.reader.config.something + 10 }}',
            'something' => '1',
        ]);
        $dataflow->add_step($reader);

        $writer = new step();
        $writer->name = 'do-nothing-writer';
        $writer->type = 'tool_dataflows\local\step\writer_debugging';
        $writer->depends_on([$reader]);
        $dataflow->add_step($writer);

        $variables = new variables_root($dataflow);
        $tree = $variables->get_tree();

        $parser = new parser();
        $expressedvalue = $parser->evaluate('${{steps.reader.config.sql}}', (array) $tree);
        $this->assertEquals('select 23', $expressedvalue);
        $expressedvalue = $parser->evaluate('${{steps.reader.config.something}}', (array) $tree);
        $this->assertEquals(1, $expressedvalue);
        $expressedvalue = $parser->evaluate('${{steps.reader.config.counterfield}}', (array) $tree);
        $this->assertEquals(11, $expressedvalue);
        $expressedvalue = $parser->evaluate('${{steps.reader.config.countervalue}}', (array) $tree);
        $this->assertEquals(11 * 2, $expressedvalue);
    }
}
