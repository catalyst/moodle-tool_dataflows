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

namespace tool_dataflows\execution;

use tool_dataflows\dataflow;
use tool_dataflows\step;

defined('MOODLE_INTERNAL') || die();

/** Exposes all private fields for the purpose of testing them. */
class test_engine extends engine {
    public function __get($p) {
        return $this->$p;
    }
}

/**
 * Unit tests covering the execution engine.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_engine_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test the construction of the engine to ensure it mirrors the dataflow defined by the user.
     */
    public function test_engine_construction() {

        list($dataflow, $steps, $expectedsinks, $expectedflowsinks) = $this->dataflow_provider();

        // Create the engine.
        $engine = new test_engine($dataflow);

        // Test that the engine has been created correctly.
        $this->assertEquals(engine::STATUS_NEW, $engine->status);
        $this->assertEquals($dataflow, $engine->dataflow);
        $this->assertEquals(count($steps), count($engine->enginesteps));

        foreach ($engine->enginesteps as $id => $enginestep) {
            // Test the identity of the engine step.
            $this->assertEquals(engine::STATUS_NEW, $enginestep->status);
            $this->assertInstanceOf('\tool_dataflows\execution\engine_step', $enginestep);
            $this->assertEquals($id, $enginestep->id);
            $this->assertArrayHasKey($id, $steps);
            $this->assertEquals($steps[$id]->id, $enginestep->stepdef->id);

            // Test that the upstreams mirror the dependencies.
            $upstreams = $enginestep->upstreams;
            // Extract the IDs.
            $deps = array_map(
                function($dep) {
                    return $dep->id;
                },
                $steps[$enginestep->id]->dependencies()
            );
            $this->assertEquals(count($deps), count($upstreams));
            foreach ($upstreams as $upstream) {
                // Test upstream set correctly.
                $this->assertInstanceOf('\tool_dataflows\execution\engine_step', $upstream);
                $this->assertTrue(in_array($upstream->id, $deps));
                // Test downstream set correctly.
                $this->assertArrayHasKey($id, $upstream->downstreams);
                $this->assertEquals($enginestep, $upstream->downstreams[$id]);
            }
        }

        // Test that sinks are properly found.
        $sinks = array_map(
            function($s) {
                return $s->id;
            },
            $engine->sinks
        );
        $this->assertEquals($expectedsinks, $sinks);

        // Test flow sinks.
        // TODO: These tests may be weak.
        $flowsinks = [];
        $flowcaps = $engine->flowcaps;
        foreach ($flowcaps as $cap) {
            $flowsinks[] = array_values(
                array_map(
                    function($s) {
                        return (int) $s->id;
                    },
                    $cap->upstreams
                )
            );
        }
        $this->assertEquals(count($expectedflowsinks), count($flowsinks));

        foreach ($flowsinks as $flowsink) {
            $this->assertTrue(in_array($flowsink, $expectedflowsinks));
        }

        // Test initialisation.
        $engine->initialise();
        foreach ($engine->enginesteps as $id => $enginestep) {
            $this->assertEquals(engine::STATUS_INITIALISED, $enginestep->status);
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
        $dataflow->save();

        $steps = [];
        $reader = new step();
        $reader->name = 'reader';
        $reader->type = 'tool_dataflows\step\mtrace';
        $dataflow->add_step($reader);
        $steps[$reader->id] = $reader;

        $writer = new step();
        $writer->name = 'writer';
        $writer->type = 'tool_dataflows\step\debugging';

        $writer->depends_on([$reader]);
        $dataflow->add_step($writer);
        $steps[$writer->id] = $writer;

        $expectedsinks = [$writer->id];

        $expectedflowsinks = [[$writer->id]];

        return [$dataflow, $steps, $expectedsinks, $expectedflowsinks];
    }
}