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
use tool_dataflows\local\step\connector_wait;

/**
 * Unit tests for connector connector_wait.
 *
 * @package    tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_connector_wait_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Dataflow creation helper function
     *
     * @param int $timesec Time in seconds.
     * @return dataflow
     */
    public function create_dataflow(int $timesec): dataflow {
        $dataflow = new dataflow();
        $dataflow->name = 'wait';
        $dataflow->enabled = true;
        $dataflow->save();

        $steps = [];
        $waiter = new step();
        $waiter->name = 'wait';
        $waiter->type = connector_wait::class;
        $waiter->config = Yaml::dump(['timesec' => $timesec]);
        $dataflow->add_step($waiter);
        $steps[$waiter->id] = $waiter;

        return $dataflow;
    }

    /**
     * Tests connector_wait
     *
     * @covers \tool_dataflows\local\step\connector_wait
     */
    public function test_wait() {
        $timesec = 2;
        $dataflow = $this->create_dataflow($timesec);

        ob_start();
        $engine = new engine($dataflow);
        $starttime = microtime(true);
        $engine->execute();
        $finishtime = microtime(true);
        ob_end_clean();
        $this->assertTrue($finishtime - $starttime > $timesec);
    }
}
