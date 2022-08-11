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
use tool_dataflows\local\step\connector_curl;
use tool_dataflows\local\step\connector_debugging;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/local/execution/reader_sql_variable_setter.php');
require_once(__DIR__ . '/application_trait.php');

/**
 * Unit tests for dataflow variables and setting them via the dataflow engine.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_variables_test extends \advanced_testcase {
    use application_trait;

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

        // Init the engine.
        ob_start();
        $engine = new engine($dataflow);
        $variables = $engine->get_variables();
        ob_get_clean();

        $parser = new parser;
        $expressedvalue = $parser->evaluate('${{steps.reader.config.sql}}', $variables);
        $this->assertEquals('select 23', $expressedvalue);
        $expressedvalue = $parser->evaluate('${{steps.reader.config.something}}', $variables);
        $this->assertEquals(1, $expressedvalue);
        $expressedvalue = $parser->evaluate('${{steps.reader.config.counterfield}}', $variables);
        $this->assertEquals(11, $expressedvalue);
        $expressedvalue = $parser->evaluate('${{steps.reader.config.countervalue}}', $variables);
        $this->assertEquals(11 * 2, $expressedvalue);
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
        ob_start();
        $engine = new engine($dataflow);

        // Expecting it to throw an exception during execution, particularly
        // when preparing the SQL and finding out it contains an unparsed
        // expression.
        $this->compatible_expectError();
        try {
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
                [[ AND ' . $DB->sql_cast_char2int('value') . ' > ${{countervalue}} ]]
              ORDER BY ' . $DB->sql_cast_char2int('value') . ' ASC
                 LIMIT 10';

        // Create the dataflow.
        $dataflow = new dataflow();
        $dataflow->name = 'readwrite';
        $dataflow->enabled = true;
        $dataflow->concurrencyenabled = false;
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
        ob_start();
        $engine = new engine($dataflow);

        // Check before state.
        $variables = $engine->get_variables();
        $this->assertEquals(new \stdClass, $variables['global']->vars);
        $this->assertEquals(new \stdClass, $variables['dataflow']->vars);
        $reader->read();
        $this->assertEmpty($reader->config->countervalue ?? null);

        // Set the expected outputs.
        $dataflowvalue = 'dataflow test value';
        $globalvalue = 'global (plugin scope) test value';
        execution\reader_sql_variable_setter::$dataflowvar = $dataflowvalue;
        execution\reader_sql_variable_setter::$globalvar = $globalvalue;

        // Execute.
        $engine->execute();
        ob_get_clean();
        $this->assertDebuggingCalledCount(5);

        // Check expected after state.
        $variables = $engine->get_variables();
        $this->assertEquals($dataflowvalue, $variables['dataflow']->vars->dataflowvar);
        $this->assertTrue($variables['dataflow']->config->enabled);
        $this->assertFalse($variables['dataflow']->config->concurrencyenabled);
        $this->assertEquals($globalvalue, $variables['global']->vars->globalvar);
        $this->assertEquals(5, $variables['steps']->reader->config->countervalue);

        // Check persistence for dataflow and step scopes (global is always persisted).
        $reader->read();
        $this->assertEquals(5, $reader->config->countervalue);
        $dataflow->read();
        $this->assertEquals($dataflowvalue, $dataflow->vars->dataflowvar);

        // Throw in some expression tests as well.
        $expressionlanguage = new ExpressionLanguage();
        $expressedglobalvalue = $expressionlanguage->evaluate('global.vars.globalvar', $variables);
        $this->assertEquals($globalvalue, $expressedglobalvalue);
        $expresseddataflowvalue = $expressionlanguage->evaluate('dataflow.vars.dataflowvar', $variables);
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

    /**
     * Tests custom outputs based on a step's exposed variables.
     *
     * The gist is, each step type can expose data which it was responsible in
     * handling. This data is then mapped to a user defined hash map which can
     * then be referenced later on by downstream steps (step.outputs.myalias).
     *
     * @covers  \tool_dataflows\parser
     * @covers  \tool_dataflows\parser::evaluate_recursive
     * @covers  \tool_dataflows\local\step\base_step::prepare_outputs
     * @covers  \tool_dataflows\local\step\base_step::set_variables
     */
    public function test_output_variables() {
        // Create curl step.
        $testgeturl = $this->get_mock_url('/h5pcontenttypes.json');

        $stepdef = new step();
        $dataflow = new dataflow();
        $dataflow->name = 'connector-step';
        $dataflow->enabled = true;
        $dataflow->save();

        // Tests get method.
        $stepdef->config = Yaml::dump([
            'curl' => $testgeturl,
            'destination' => '',
            'headers' => '',
            'method' => 'get',
            // Sets expressed values typically for values in scope with this step, so they can be accessed from other steps.
            'outputs' => ['customOutputKey' => '${{ fromJSON(response.result).contentTypes[0].id }}'],
        ]);
        $stepdef->name = 'connector';
        $stepdef->type = connector_curl::class;
        $dataflow->add_step($stepdef);
        ob_start();
        $engine = new engine($dataflow, false, false);
        $engine->execute();
        ob_get_clean();

        // Test an expression targetting the new custom mapping (which is how you might reference it from another step).
        $expressionlanguage = new ExpressionLanguage();
        $variables = $engine->get_variables();
        // Test the shorthand version also.
        $result = $expressionlanguage->evaluate("steps.{$stepdef->name}.vars.customOutputKey", $variables);
        $this->assertEquals('H5P.Accordion', $result);
    }

    /**
     * Test to ensure that array values are not converted to objects.
     *
     * @covers \tool_dataflows\dataflow::get_variables
     */
    public function test_var_arrays() {
        $dataflow = new dataflow();
        $dataflow->name = 'connector-step';
        $dataflow->enabled = true;
        $dataflow->vars = Yaml::dump(['abc' => [1, 2, 3]]);

        $stepdef = new step();
        $stepdef->name = 'deb';
        $stepdef->type = connector_debugging::class;
        $stepdef->config = Yaml::dump([
            'outputs' => ['out' => '${{ dataflow.vars.abc}}'],
        ]);
        $dataflow->add_step($stepdef);

        ob_start();
        $engine = new engine($dataflow, false, false);
        $engine->execute();
        ob_get_clean();

        $vars = $dataflow->variables;
        $this->assertEquals([1, 2, 3], $vars['steps']->deb->vars->out);
    }

    /**
     * Test the functions available through the parser
     *
     * @param  string $expression
     * @param  array $variables
     * @param  mixed $expected
     *
     * @covers        \tool_dataflows\parser
     * @dataProvider  parser_functions_data_provider
     */
    public function test_parser_functions(string $expression, array $variables, $expected) {
        $parser = new parser;
        $result = $parser->evaluate('${{'.$expression.'}}', $variables);
        $this->assertEquals($expected, $result);
    }

    /**
     * Ensure these expressions return the expected values (happy path)
     *
     * @return array of data
     */
    public function parser_functions_data_provider() {
        $example = [
            'a' => [
                'b' => null,
                'c' => null,
                'd' => (object) ['e' => 'f'],
            ],
        ];
        return [
            // Counts.
            ['count(a)', ['a' => [3, 2, 1]], 3],
            ['count(a["b"])', ['a' => ['b' => [1, 2]]], 2],

            // Issets.
            ['isset(a)', ['a' => [3, 2, 1]], true],
            ['isset(a[0])', ['a' => [3, 2, 1]], true],
            ['isset(a[4]["id"])', ['a' => [3, 2, 1, 2, ['id' => 1]]], true],
            ['isset(a[4]["somefield"])', ['a' => [3, 2, 1, 2, ['id' => 1]]], false],
            ['isset(a["d"].e)', $example, true],
            ['isset(a["e"])', $example, false], // Note: a["e"].id won't be resolved.
            ['isset(a["d"].f)', $example, false], // Works because "d" object exists.
            ['isset(a["something"])', ['a' => [3, 2, 1]], false], // Note: b["anything"] won't even be resolved.

            // From JSON.
            ['fromJSON(a)', ['a' => json_encode([3, 2, 1])], [3, 2, 1]],
            ['fromJSON(a)', ['a' => json_encode($example)], json_decode(json_encode($example))],
        ];
    }
}
