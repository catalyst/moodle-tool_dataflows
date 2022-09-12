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

use tool_dataflows\local\execution\test_engine;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/local/execution/test_engine.php');

/**
 * Tests aborting the dataflow.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_abort_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests the abort step.
     *
     * @covers \tool_dataflows\local\step\flow_abort
     */
    public function test_abort_call() {
        list($dataflow, $steps) = $this->dataflow_provider();

        // Create the engine.
        ob_start();
        $engine = new test_engine($dataflow);
        $this->assertFalse($engine->is_aborted());
        $engine->execute();
        ob_get_clean();

        $this->assertTrue($engine->is_aborted());
        $this->assertEquals(test_engine::STATUS_ABORTED, $engine->status);

        foreach ($engine->enginesteps as $step) {
            $this->assertEquals(test_engine::STATUS_ABORTED, $step->status);
            $this->assertTrue($step->iterator->is_finished());
            $this->assertFalse($step->iterator->next($step));
        }
        foreach ($engine->flowcaps as $step) {
            $this->assertEquals(test_engine::STATUS_ABORTED, $step->status);
        }
    }

    /**
     * Create a dataflow for testing.
     *
     * @return array
     */
    public function dataflow_provider(): array {
        $dataflow = new dataflow();
        $dataflow->name = 'two-step';
        $dataflow->enabled = true;
        $dataflow->save();

        $steps = [];
        $reader = new step();
        $reader->name = 'reader';
        $reader->type = 'tool_dataflows\local\step\reader_sql';
        $reader->config = '{sql: SELECT 1}';
        $dataflow->add_step($reader);
        $steps[$reader->id] = $reader;

        $abort = new step();
        $abort->name = 'abort';
        $abort->type = 'tool_dataflows\local\step\flow_abort';

        $abort->depends_on([$reader]);
        $dataflow->add_step($abort);
        $steps[$abort->id] = $abort;

        return [$dataflow, $steps];
    }

}
