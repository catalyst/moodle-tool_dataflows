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
use tool_dataflows\execution\engine;
use tool_dataflows\step;
use tool_dataflows\step\reader_sql;

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
     * @covers \tool_dataflows\step\reader_sql::construct_query
     * @covers \tool_dataflows\step\reader_sql::validate_config
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
        $dataflow->save();

        $reader = new step();
        $reader->name = 'reader';
        $reader->type = 'tool_dataflows\step\reader_sql';

        // Set the SQL query via a YAML config string.
        $reader->config = Yaml::dump([
            'sql' => "SELECT plugin, name, value
                        FROM {config_plugins}
                       WHERE plugin = '--phantom_plugin--'"]);
        $dataflow->add_step($reader);

        // TODO: When better writers are available, change this to use one.
        $writer = new step();
        $writer->name = 'writer';
        $writer->type = 'tool_dataflows\step\writer_debugging';

        $writer->depends_on([$reader]);
        $dataflow->add_step($writer);

        // Execute.
        $engine = new engine($dataflow);
        $engine->execute();
        $this->assertDebuggingCalledCount(2, [json_encode($input[0]), json_encode($input[1])]);
    }

    /**
     * Test validate_config().
     *
     * @covers \tool_dataflows\step\reader_sql::validate_config
     * @throws \coding_exception
     */
    public function test_validate_config() {
        // Test valid configuration.
        $config = (object)['sql' => 'SELECT * FROM {config}'];
        $reader = new reader_sql();
        $this->assertTrue($reader->validate_config($config));

        // Test missing sql value.
        $config = (object)['notsql' => 'SELECT * FROM {config}'];
        $result = $reader->validate_config($config);
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('config_field_missing', $result);
        $this->assertEquals(get_string('config_field_missing', 'tool_dataflows', 'sql'), $result['config_field_missing']);
    }
}
