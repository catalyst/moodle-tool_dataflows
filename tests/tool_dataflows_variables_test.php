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

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Yaml\Yaml;
use tool_dataflows\dataflow;
use tool_dataflows\local\execution\engine;
use tool_dataflows\step;
use tool_dataflows\local\execution;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/local/execution/reader_sql_variable_setter.php');

/**
 * Unit tests for dataflow variables and setting them via the dataflow engine.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_variables_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test self referencing variables, (but with valid and complete values)
     *
     * @covers \tool_dataflows\dataflow::get_variables
     * @covers \tool_dataflows\local\execution\engine::get_variables
     */
    public function test_variable_parsing_involving_self_recursion() {
        // Create the dataflow.
        $dataflow = new dataflow();
        $dataflow->name = 'readwrite';
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

        // Init the engine.
        $engine = new engine($dataflow);
        $variables = $engine->get_variables();

        $expressionlanguage = new ExpressionLanguage();
        $expressedvalue = $expressionlanguage->evaluate('steps.reader.config.something', $variables);
        $this->assertEquals(1, $expressedvalue);
        $expressedvalue = $expressionlanguage->evaluate('steps.reader.config.counterfield', $variables);
        $this->assertEquals(11, $expressedvalue);
        $expressedvalue = $expressionlanguage->evaluate('steps.reader.config.countervalue', $variables);
        $this->assertEquals(11 * 2, $expressedvalue);
        $expressedvalue = $expressionlanguage->evaluate('steps.reader.config.sql', $variables);
        $this->assertEquals('select 23', $expressedvalue);
    }

    /**
     * Testing for indirect self recursion, e.g. interdependant steps, one expected to pass, one expected to fail.
     *
     * @covers \tool_dataflows\dataflow::get_variables
     * @covers \tool_dataflows\local\execution\engine::get_variables
     */
    public function test_variable_parsing_involving_indirect_self_recursion() {
        // Create the dataflow.
        $dataflow = new dataflow();
        $dataflow->name = 'selfinflicted';
        $dataflow->save();

        $reader = new step();
        $reader->name = 'reader';
        $reader->type = execution\reader_sql_variable_setter::class;

        // Set the SQL query via a YAML config string.
        $reader->config = Yaml::dump([
            'sql' => 'select ${{ steps.reader.config.countervalue }}',
            'countervalue' => '${{ steps.reader.config.counterfield + steps.reader.config.counterfield }}',
            'something' => '${{ steps.writer.config.varb }}',
            'counterfield' => '${{ steps.reader.config.something + 10 }}',
        ]);
        $dataflow->add_step($reader);

        $writer = new step();
        $writer->name = 'writer';
        $writer->type = 'tool_dataflows\local\step\writer_debugging';
        $writer->config = Yaml::dump([
            'vara' => '${{ steps.writer.config.varc }}', // This is not, since it references something in a loop.
            'varb' => '${{ steps.writer.config.vara }}',
            'varc' => '${{ steps.existnope.config.vara + steps.existnope.config.varb }}',
        ]);
        $writer->depends_on([$reader]);
        $dataflow->add_step($writer);
        $dataflow->enabled = true;

        // Init the engine.
        $engine = new engine($dataflow);

        // Expecting it to throw an exception during execution, particularly
        // when preparing the SQL and finding out it contains an unparsed
        // expression.
        $this->expectError();
        try {
            ob_start();
            $engine->execute();
        } finally {
            ob_get_clean();
        }
    }

    /**
     * Test variables being set within a dataflow engine run, at different scopes
     *
     * @covers \tool_dataflows\local\execution\engine_step::set_var
     * @covers \tool_dataflows\local\execution\engine_step::set_global_var
     * @covers \tool_dataflows\local\execution\engine_step::set_dataflow_var
     * @covers \tool_dataflows\local\execution\engine::set_global_var
     * @covers \tool_dataflows\local\execution\engine::set_dataflow_var
     */
    public function test_variables_set_at_different_scopes() {
        global $DB;

        // Insert test records.
        $template = ['plugin' => '--phantom_plugin--'];
        foreach (range(1, 5) as $value) {
            $input = array_merge($template, [
                'name' => 'test_' . $value,
                'value' => $value,
            ]);
            $DB->insert_record('config_plugins', (object) $input);
        }

        // Prepare query, with an optional fragment which is included if the
        // expression field is present. Otherwise it is skipped.
        $sql = 'SELECT *
                  FROM {config_plugins}
                 WHERE plugin = \'' . $template['plugin'] . '\'
                [[ AND CAST(value as int) > ${{countervalue}} ]]
              ORDER BY CAST(value as int) ASC
                 LIMIT 10';

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
            'sql' => $sql,
            'counterfield' => 'value',
            'countervalue' => '',
        ]);
        $dataflow->add_step($reader);

        $writer = new step();
        $writer->name = 'writer';
        $writer->type = 'tool_dataflows\local\step\writer_debugging';
        $writer->depends_on([$reader]);
        $dataflow->add_step($writer);

        // Init the engine.
        $engine = new engine($dataflow);

        // Check before state.
        $variables = $engine->get_variables();
        $this->assertEquals(new \stdClass, $variables['global']);
        $this->assertEquals(new \stdClass, $variables['dataflow']->config);
        $reader->read();
        $this->assertEmpty($reader->config->countervalue ?? null);

        // Set the expected outputs.
        $dataflowvalue = 'dataflow test value';
        $globalvalue = 'global (plugin scope) test value';
        execution\reader_sql_variable_setter::$dataflowvar = $dataflowvalue;
        execution\reader_sql_variable_setter::$globalvar = $globalvalue;
        // Execute.
        ob_start();
        $engine->execute();
        ob_get_clean();
        $this->assertDebuggingCalledCount(5);

        // Check expected after state.
        $variables = $engine->get_variables();
        $this->assertEquals($dataflowvalue, $variables['dataflow']->config->dataflowvar);
        $this->assertEquals($globalvalue, $variables['global']->globalvar);
        $this->assertEquals(5, $variables['steps']->reader->config->countervalue);

        // Check persistence for dataflow and step scopes (global is always persisted).
        $reader->read();
        $this->assertEquals(5, $reader->config->countervalue);
        $dataflow->read();
        $this->assertEquals($dataflowvalue, $dataflow->config->dataflowvar);

        // Throw in some expression tests as well.
        $expressionlanguage = new ExpressionLanguage();
        $expressedglobalvalue = $expressionlanguage->evaluate('global.globalvar', $variables);
        $this->assertEquals($globalvalue, $expressedglobalvalue);
        $expresseddataflowvalue = $expressionlanguage->evaluate('dataflow.config.dataflowvar', $variables);
        $this->assertEquals($dataflowvalue, $expresseddataflowvalue);
        $expressedstepvalue = $expressionlanguage->evaluate('steps.reader.config.countervalue', $variables);
        $this->assertEquals(5, $expressedstepvalue);

        // Get and check state timings of the dataflow.
        $expressedstepvalue = $expressionlanguage->evaluate('dataflow.states.initialised', $variables);
        $this->assertNotEmpty($expressedstepvalue);
        $expressedstepvalue = $expressionlanguage->evaluate('dataflow.states.processing', $variables);
        $this->assertNotEmpty($expressedstepvalue);
        $expressedstepvalue = $expressionlanguage->evaluate('dataflow.states.finished', $variables);
        $this->assertNotEmpty($expressedstepvalue);
        $expressedstepvalue = $expressionlanguage->evaluate('dataflow.states.finalised', $variables);
        $this->assertNotEmpty($expressedstepvalue);

        // State timings of each step. Just ensure they aren't empty.
        $expressedstepvalue = $expressionlanguage->evaluate('steps.reader.states.new', $variables);
        $this->assertNotEmpty($expressedstepvalue);
        $expressedstepvalue = $expressionlanguage->evaluate('steps.reader.states.initialised', $variables);
        $this->assertNotEmpty($expressedstepvalue);
        $expressedstepvalue = $expressionlanguage->evaluate('steps.reader.states.flowing', $variables);
        $this->assertNotEmpty($expressedstepvalue);
        $expressedstepvalue = $expressionlanguage->evaluate('steps.reader.states.finalised', $variables);
        $this->assertNotEmpty($expressedstepvalue);
        $expressedstepvalue = $expressionlanguage->evaluate('steps.writer.states.new', $variables);
        $this->assertNotEmpty($expressedstepvalue);
        $expressedstepvalue = $expressionlanguage->evaluate('steps.writer.states.initialised', $variables);
        $this->assertNotEmpty($expressedstepvalue);
        $expressedstepvalue = $expressionlanguage->evaluate('steps.writer.states.flowing', $variables);
        $this->assertNotEmpty($expressedstepvalue);
        $expressedstepvalue = $expressionlanguage->evaluate('steps.writer.states.finalised', $variables);
        $this->assertNotEmpty($expressedstepvalue);

        $this->assertEquals(engine::STATUS_FINALISED, $engine->status);
    }
}
