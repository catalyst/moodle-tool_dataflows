<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Testing state for the dataflow
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_workflow_state_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Testing the export function to ensure it does export expected state
     * changes before and after execution
     *
     * @covers \tool_dataflows\local\execution\engine::get_export_data
     * @covers \tool_dataflows\local\execution\engine::get_export
     */
    public function test_exporting_state_before_and_after_execution(): void {
        global $DB;

        // Set up dataflow and prerequisite data.
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
                    AND ' . $DB->sql_cast_char2int('value') . ' > ${{config.countervalue}}]]
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

        // Create engine.
        ob_start();
        $engine = new engine($dataflow);
        // Dump initial state of engine.
        $initialdata = $engine->get_export_data();
        // Execute engine.
        $engine->execute();
        $this->assertDebuggingCalledCount(3);
        // Dump final state of engine - expecting differences and timestamps in the future.
        $finaldata = $engine->get_export_data();
        ob_get_clean();

        // Test they are different.
        $this->assertNotEquals(json_encode($initialdata), json_encode($finaldata));
        // Yet the same.
        $this->assertEquals($initialdata['dataflow']['id'], $finaldata['dataflow']['id']);
        // Only the "new" state should be set.
        $this->assertCount(1, array_filter($initialdata['steps']['reader']['states']));
        // But by the end, more state timestamps should have been added.
        $this->assertGreaterThan(1, $finaldata['steps']['reader']['states']);
        // But it should still hold the same new value it once had.
        $this->assertEquals($initialdata['steps']['reader']['states']['new'], $finaldata['steps']['reader']['states']['new']);
    }
}
