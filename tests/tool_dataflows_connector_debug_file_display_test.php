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

/**
 * Test for connector_debug_file_display step.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_connector_debug_file_display_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test file dump step.
     *
     * @covers \tool_dataflows\local\step\connector_debug_file_display::execute
     */
    public function test_connector_debug_file_display() {
        [$dataflow, $steps] = $this->create_dataflow();

        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        $content = ob_get_clean();

        $this->assertNotFalse(strpos($content, '1,2,3'));
    }

    /**
     * Create a dataflow for use in testing.
     *
     * @return array
     */
    public function create_dataflow(): array {
        $dataflow = new dataflow();
        $dataflow->name = 'two-step';
        $dataflow->enabled = true;
        $dataflow->save();

        $steps = [];
        $reader = new step();
        $reader->name = 'reader';
        $reader->type = 'tool_dataflows\local\step\reader_sql';
        $reader->config = Yaml::dump(['sql' => "SELECT '1' as claris, '2' as daris, '3' as malice"]);
        $dataflow->add_step($reader);
        $steps[$reader->id] = $reader;

        $writer = new step();
        $writer->name = 'writer';
        $writer->type = 'tool_dataflows\local\step\writer_stream';
        $writer->config = Yaml::dump([
            'streamname' => 'out.csv',
            'format' => 'csv',
        ]);
        $writer->depends_on([$reader]);
        $dataflow->add_step($writer);
        $steps[$writer->id] = $writer;

        $dumper = new step();
        $dumper->name = 'dumper';
        $dumper->type = 'tool_dataflows\local\step\connector_debug_file_display';
        $dumper->config = Yaml::dump(['streamname' => 'out.csv']);
        $dumper->depends_on([$writer]);
        $dataflow->add_step($dumper);
        $steps[$dumper->id] = $dumper;

        return [$dataflow, $steps];
    }
}
