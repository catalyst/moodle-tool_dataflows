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
use tool_dataflows\dataflow;
use tool_dataflows\local\execution\engine;
use tool_dataflows\step;
use tool_dataflows\local\step\flow_sql;

defined('MOODLE_INTERNAL') || die();

// Include lib.php functions that aren't included automatically for Moodle 37- and below.
require_once(dirname(__FILE__) . '/../lib.php');

/**
 * Unit test for the SQL flow step.
 *
 * @package   tool_dataflows
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_flow_sql_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('permitted_dirs', '/tmp', 'tool_dataflows');

        // Create a test dataflow.
        $dataflow = new dataflow();
        $dataflow->name = 'sql-flow';
        $dataflow->enabled = true;
        $dataflow->save();

        $this->inputpath = tempnam('', 'tool_dataflows/input');
        $reader = new step();
        $reader->name = 'reader';
        $reader->type = 'tool_dataflows\local\step\reader_json';
        $reader->config = Yaml::dump([
            'pathtojson' => $this->inputpath,
            'arrayexpression' => 'data',
            'arraysortexpression' => '',
        ]);
        $dataflow->add_step($reader);

        $flow = new step();
        $flow->name = 'flow';
        $flow->type = 'tool_dataflows\local\step\flow_sql';

        $flow->config = Yaml::dump(['sql' => 'SELECT * FROM {course}']);
        $flow->depends_on([$reader]);
        $dataflow->add_step($flow);

        $this->outputpath = tempnam('', 'tool_dataflows/output');
        $writer = new step();
        $writer->name = 'stream-writer';
        $writer->type = 'tool_dataflows\local\step\writer_stream';
        $writer->config = Yaml::dump([
            'format' => 'json',
            'streamname' => $this->outputpath,
        ]);
        $writer->depends_on([$flow]);
        $dataflow->add_step($writer);

        $this->flow = $flow;
        $this->writer = $writer;
        $this->dataflow = $dataflow;
    }

    /**
     * Creates a desired number of iterations in the test flow's json_reader step.
     *
     * @param int $rows number of rows to generate.
     */
    private function create_iterator_data(int $rows) {
        $data = array_map(function ($rownum) {
            return (object) ['id' => $rownum];
        }, range(1, $rows));

        $json = json_encode((object) [
            'data' => $data,
            'modified' => [1654058940],
            'errors' => [],
        ]);

        // Write initial contents to file for reader to pick up.
        $stream = fopen($this->inputpath, 'w');
        fwrite($stream, $json);
        fclose($stream);
    }

    /**
     * Tests normal execution of the step.
     *
     * @covers \tool_dataflows\local\step\flow_sql::execute
     */
    public function test_execute() {
        global $DB;

        // By default 1 course always exists.
        $numcourses = $DB->count_records('course');
        $this->assertEquals(1, $numcourses);

        $iteratorcount = 5;
        $this->create_iterator_data($iteratorcount);

        // Run the dataflow unmodified from setUp().
        ob_start();
        $engine = new engine($this->dataflow);
        $engine->execute();
        ob_get_clean();

        // Check they were queried back successfully.
        $stepoutput = json_decode(file_get_contents($this->outputpath));
        $this->assertCount($iteratorcount, $stepoutput);

        // Check the variables of the engine on the last iteration, all iterations will have the same data anyway.
        $variables = $engine->get_variables_root()->get();
        $lastiterationrowcount = $variables->steps->flow->count;
        $this->assertEquals($numcourses, $lastiterationrowcount);
    }

    /**
     * Tests execution with expressions within the SQL.
     *
     * @covers \tool_dataflows\local\step\flow_sql::execute
     */
    public function test_execute_with_expressions() {
        // Create a course and set its ID as a config variable to use in a query.
        $course = $this->getDataGenerator()->create_course();
        $iteratorcount = 5;
        $this->create_iterator_data($iteratorcount);

        $this->dataflow->vars = Yaml::dump([
            'courseid' => $course->id,
        ]);
        $this->dataflow->save();

        $this->flow->config = Yaml::dump([
            'sql' => 'SELECT * FROM {course} WHERE id = ${{dataflow.vars.courseid}}',
        ]);
        $this->flow->save();

        ob_start();
        $engine = new engine($this->dataflow);
        $engine->execute();
        ob_get_clean();

        // Check they were queried back successfully.
        $stepoutput = json_decode(file_get_contents($this->outputpath));
        $this->assertCount($iteratorcount, $stepoutput);

        // Check the variables of the engine on the last iteration, all iterations will have the same data anyway.
        $variables = $engine->get_variables_root()->get();
        $lastiterationflow = $variables->steps->flow;
        $this->assertEquals($course->id, $lastiterationflow->data->id);
        $this->assertEquals(1, $lastiterationflow->count);
    }

    /**
     * Tests execution when the sql is not well formed.
     *
     * @covers \tool_dataflows\local\step\flow_sql::execute
     */
    public function test_execute_bad_sql() {
        $this->create_iterator_data(1);

        // Execute a query where the sql is not well formed.
        // This should return null and a count of 0.
        $this->flow->config = Yaml::dump([
            'sql' => 'SELECT *',
        ]);
        $this->flow->save();

        ob_start();
        try {
            $engine = new engine($this->dataflow);
            $engine->execute();
        } catch (\Exception $e) {
            $this->assertInstanceOf('dml_read_exception', $e);
        }

        ob_get_clean();
    }

    /**
     * Tests execution when there are no records found.
     * Should return null data and 0 count.
     *
     * @covers \tool_dataflows\local\step\flow_sql::execute
     */
    public function test_execute_no_records() {
        $this->create_iterator_data(1);

        // Execute a query where no records are returned.
        // This should return null and a count of 0.
        $this->flow->config = Yaml::dump([
            'sql' => 'SELECT * FROM {course} WHERE id = -1',
        ]);
        $this->flow->save();

        ob_start();
        $engine = new engine($this->dataflow);
        $engine->execute();
        ob_get_clean();

        $variables = $engine->get_variables_root()->get();
        $lastiterationflow = $variables->steps->flow;
        $this->assertEquals([], $lastiterationflow->data);
        $this->assertEquals(0, $lastiterationflow->count);
    }

    /**
     * Tests executing when there are multiple records found.
     * Should return null data and the correct count.
     *
     * @covers \tool_dataflows\local\step\flow_sql::execute
     */
    public function test_execute_multiple_records() {
        global $DB;
        $this->create_iterator_data(1);

        // Create multiple courses, and trigger the dataflow.
        // This should return a correct count but a record of null.
        $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_course();
        $numcourses = $DB->count_records('course');

        ob_start();
        $engine = new engine($this->dataflow);
        $engine->execute();
        ob_get_clean();

        $variables = $engine->get_variables_root()->get();
        $lastiterationflow = $variables->steps->flow;
        $this->assertEquals([], $lastiterationflow->data);
        $this->assertEquals($numcourses, $lastiterationflow->count);
    }

    /**
     * Tests config validation.
     *
     * @covers \tool_dataflows\local\step\flow_sql::validate_config
     */
    public function test_validate_config() {
        // Test valid configuration.
        $config = (object) ['sql' => 'SELECT * FROM {course}'];
        $flow = new flow_sql();
        $this->assertTrue($flow->validate_config($config));

        // Test missing sql value.
        $config = (object) ['notsql' => 'SELECT * FROM {course}'];
        $result = $flow->validate_config($config);
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('config_sql', $result);
        $this->assertEquals(get_string('config_field_missing', 'tool_dataflows', 'sql'), $result['config_sql']);
    }
}
