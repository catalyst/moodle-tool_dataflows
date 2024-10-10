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
use tool_dataflows\local\step\reader_sql;

defined('MOODLE_INTERNAL') || die();

// Include lib.php functions that aren't included automatically for Moodle 37- and below.
require_once(dirname(__FILE__) . '/../lib.php');

/**
 * Unit test for the SQL reader step.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_sql_reader_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests the reader's ability to read from a database.
     *
     * @covers \tool_dataflows\local\step\reader_sql::construct_query
     * @covers \tool_dataflows\local\step\reader_sql::validate_config
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_reader() {
        global $DB;

        $input = [
            ['plugin' => '--phantom_plugin--', 'name' => 'bsv1', 'value' => '1'],
            ['plugin' => '--phantom_plugin--', 'name' => 'bsv2', 'value' => '2'],
        ];
        $DB->insert_record('config_plugins', (object) $input[0]);
        $DB->insert_record('config_plugins', (object) $input[1]);

        // Crate the dataflow.
        $dataflow = new dataflow();
        $dataflow->name = 'sql-step';
        $dataflow->enabled = true;
        $dataflow->save();

        $reader = new step();
        $reader->name = 'reader';
        $reader->type = 'tool_dataflows\local\step\reader_sql';

        // Set the SQL query via a YAML config string.
        $reader->config = Yaml::dump([
            'sql' => "SELECT plugin, name, value
                        FROM {config_plugins}
                       WHERE plugin = '--phantom_plugin--'",
        ]);
        $dataflow->add_step($reader);

        // TODO: When better writers are available, change this to use one.
        $writer = new step();
        $writer->name = 'writer';
        $writer->type = 'tool_dataflows\local\step\writer_debugging';

        $writer->depends_on([$reader]);
        $dataflow->add_step($writer);

        // Execute.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();
        $this->assertDebuggingCalledCount(2, [json_encode($input[0]), json_encode($input[1])]);
    }

    /**
     * Test validate_config().
     *
     * @covers \tool_dataflows\local\step\reader_sql::validate_config
     * @throws \coding_exception
     */
    public function test_validate_config() {
        // Test valid configuration.
        $config = (object) ['sql' => 'SELECT * FROM {config}'];
        $reader = new reader_sql();
        $this->assertTrue($reader->validate_config($config));

        // Test missing sql value.
        $config = (object) ['notsql' => 'SELECT * FROM {config}'];
        $result = $reader->validate_config($config);
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('config_sql', $result);
        $this->assertEquals(get_string('config_field_missing', 'tool_dataflows', 'sql'), $result['config_sql']);
    }

    /**
     * Test expression usage throughout a engine lifecycle/run
     *
     * @covers \tool_dataflows\local\step\reader_sql
     */
    public function test_expressions_within_a_run() {
        global $DB;

        // Insert test records.
        $template = ['plugin' => '--phantom_plugin--'];
        foreach (range(1, 10) as $value) {
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
                [[
                    AND ' . $DB->sql_cast_char2int('value') . ' > ${{config.countervalue}}
                ]]
              ORDER BY ' . $DB->sql_cast_char2int('value') . ' ASC
                 LIMIT 3';

        // Create the dataflow.
        $dataflow = new dataflow();
        $dataflow->name = 'readwrite';
        $dataflow->enabled = true;
        $dataflow->save();

        $reader = new step();
        $reader->name = 'reader';
        $reader->type = 'tool_dataflows\local\step\reader_sql';

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

        // Execute.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();
        $this->assertDebuggingCalledCount(3);
        // Reload the step from the DB, the counter value should be updated.
        $reader->read();
        $this->assertEquals(3, $reader->config->countervalue);

        // Repeat.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $this->assertDebuggingCalledCount(3);
        // Reload the step from the DB, the counter value should be updated again.
        $reader->read();
        $this->assertEquals(6, $reader->config->countervalue);

        // Recreate the engine and rerun the flow, it should be the same result.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $this->assertDebuggingCalledCount(3);
        // Reload the step from the DB, the counter value should be updated again.
        $reader->read();
        $this->assertEquals(9, $reader->config->countervalue);
        $previousvalue = $reader->config->countervalue;

        // Now test out a dry-run, it should not persist anything, but everything else should appear as expected.
        $isdryrun = true;
        ob_start();
        $engine = new engine($dataflow, $isdryrun);
        $engine->execute();
        ob_get_clean();

        // Since there are only 10 records in total, this last batch should only yield one result.
        $this->assertDebuggingCalledCount(1);
        // Reload the step from the DB, the counter value should stay the same since it's a dry run.
        $reader->read();
        $this->assertEquals($previousvalue, $reader->config->countervalue);
        $this->assertEquals(engine::STATUS_FINALISED, $engine->status);
    }

    /**
     * Tests that reader_sql throws an exception when the SQL does not evaluate to a string.
     *
     * @covers \tool_dataflows\local\step\reader_sql::construct_query
     */
    public function test_sql_bad_type() {
        $dataflow = new dataflow();
        $dataflow->name = 'sql-bad';
        $dataflow->enabled = true;
        $dataflow->vars = Yaml::dump([
            'badvalue' => [1, 2, 3],
        ]);
        $dataflow->save();

        $reader = new step();
        $reader->name = 'reader';
        $reader->type = 'tool_dataflows\local\step\reader_sql';

        // Set the SQL query via a YAML config string.
        $reader->config = Yaml::dump(['sql' => '${{dataflow.vars.badvalue}}']);
        $dataflow->add_step($reader);

        $writer = new step();
        $writer->name = 'writer';
        $writer->type = 'tool_dataflows\local\step\writer_debugging';

        $writer->depends_on([$reader]);
        $dataflow->add_step($writer);

        // Array is not valid for String value field.
        $this->expectException('TypeError');

        // Execute.
        ob_start();
        try {
            $engine = new engine($dataflow);
            $engine->execute();
        } finally {
            ob_get_clean();
        }
    }
}
