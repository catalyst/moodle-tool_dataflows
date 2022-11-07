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

use tool_dataflows\local\event_processor;
use Symfony\Component\Yaml\Yaml;
use tool_dataflows\local\execution\engine;

defined('MOODLE_INTERNAL') || die();

// Include lib.php functions that aren't included automatically for Moodle 37- and below.
require_once(dirname(__FILE__) . '/../lib.php');

/**
 * Unit tests for event processor class.
 *
 * @package   tool_dataflows
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_event_processor_test extends \advanced_testcase {
    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Tests adding and consuming an event.
     *
     * @covers \tool_dataflows\local\event_processor::add_triggered_event
     * @covers \tool_dataflows\local\event_processor::consume_event
     */
    public function test_add_and_consume_event() {
        global $DB;
        event_processor::add_triggered_event(1, 1, 'data');
        $events = $DB->get_records(event_processor::TABLE);
        $this->assertcount(1, $events);

        event_processor::consume_event(current($events)->id);
        $events = $DB->get_records(event_processor::TABLE);
        $this->assertcount(0, $events);
    }

    /**
     * Tests event getter functions
     *
     * @covers \tool_dataflows\local\event_processor::add_triggered_event
     * @covers \tool_dataflows\local\event_processor::consume_event
     */
    public function test_getting_events() {
        event_processor::add_triggered_event(1, 1, 'data');
        event_processor::add_triggered_event(1, 1, 'data');
        event_processor::add_triggered_event(2, 1, 'data');

        // There are only 2 unique dataflows (1 & 2) queued.
        $uniquedataflows = event_processor::get_flows_awaiting_run();
        $this->assertCount(2, $uniquedataflows);

        // But getting the events for each specifically should return all the events.
        $firstdataflowevents = event_processor::get_events_for_dataflow(1);
        $this->assertCount(2, $firstdataflowevents);

        $seconddataflowevents = event_processor::get_events_for_dataflow(2);
        $this->assertCount(1, $seconddataflowevents);

        $nodataflowevents = event_processor::get_events_for_dataflow(99);
        $this->assertCount(0, $nodataflowevents);
    }

    /**
     * Tests event observer function
     * with the adhoc queued execution policy
     *
     * @covers \tool_dataflows\local\event_processor::process_event
     * @covers \tool_dataflows\local\event_processor::add_triggered_event
     * @covers \tool_dataflows\local\event_processor::consume_event
     */
    public function test_process_event_adhocqueued() {
        set_config('permitted_dirs', '/tmp', 'tool_dataflows');

        $dataflow = $this->create_dataflow(event_processor::EXECUTE_ADHOCQUEUED);

        // Trigger two events.
        $event1 = $this->trigger_event();
        $event2 = $this->trigger_event();

        // The dataflow should have recorded the events for this dataflow and marked it waiting to run.
        $awaitingrun = event_processor::get_flows_awaiting_run();
        $this->assertCount(1, $awaitingrun);
        $this->assertContains((string) $dataflow->id, array_column($awaitingrun, 'dataflowid'));

        $eventsrecorded = event_processor::get_events_for_dataflow($dataflow->id);
        $this->assertCount(2, $eventsrecorded);

        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $stepoutput = json_decode(file_get_contents($this->outputpath));
        $this->assertCount(2, $stepoutput);
        $this->assertEquals((object) $event1->get_data(), $stepoutput[0]->eventdata);
        $this->assertEquals((object) $event2->get_data(), $stepoutput[1]->eventdata);

        // Ensure the events were consumed and the dataflow is no longer waiting to be run.
        $awaitingrun = event_processor::get_flows_awaiting_run();
        $this->assertCount(0, $awaitingrun);
        $eventsrecorded = event_processor::get_events_for_dataflow($dataflow->id);
        $this->assertCount(0, $eventsrecorded);
    }

    /**
     * Tests event observer function
     * with the adhoc execution policy.
     *
     * @covers \tool_dataflows\local\event_processor::process_event
     * @covers \tool_dataflows\local\event_processor::add_triggered_event
     * @covers \tool_dataflows\local\event_processor::consume_event
     */
    public function test_process_event_adhoc() {
        set_config('permitted_dirs', '/tmp', 'tool_dataflows');

        $dataflow = $this->create_dataflow(event_processor::EXECUTE_ADHOC);

        // Trigger an event.
        $event = $this->trigger_event();

        // The dataflow should have recorded an event for this dataflow and marked it waiting to run.
        $awaitingrun = event_processor::get_flows_awaiting_run();
        $this->assertCount(1, $awaitingrun);
        $this->assertContains((string) $dataflow->id, array_column($awaitingrun, 'dataflowid'));

        $eventsrecorded = event_processor::get_events_for_dataflow($dataflow->id);
        $this->assertCount(1, $eventsrecorded);

        // This should have made an adhoc task to execute this dataflow.
        $tasks = \core\task\manager::get_adhoc_tasks('\tool_dataflows\task\process_dataflow_ad_hoc');
        $this->assertCount(1, $tasks);

        // Executing the task should run the dataflow and consume the event.
        $task = array_pop($tasks);
        ob_start();
        $task->execute();
        ob_get_clean();

        // Check that it executed correctly.
        $stepoutput = json_decode(file_get_contents($this->outputpath));
        $this->assertCount(1, $stepoutput);
        $this->assertEquals((object) $event->get_data(), $stepoutput[0]->eventdata);

        // Dataflow should no longer be waiting to be run and event should have been consumed.
        $awaitingrun = event_processor::get_flows_awaiting_run();
        $this->assertCount(0, $awaitingrun);
        $eventsrecorded = event_processor::get_events_for_dataflow($dataflow->id);
        $this->assertCount(0, $eventsrecorded);
    }

    /**
     * Tests event observer function
     * with the immediate execution policy.
     *
     * @covers \tool_dataflows\local\event_processor::process_event
     * @covers \tool_dataflows\local\event_processor::add_triggered_event
     * @covers \tool_dataflows\local\event_processor::consume_event
     */
    public function test_process_event_immediate() {
        set_config('permitted_dirs', '/tmp', 'tool_dataflows');

        $dataflow = $this->create_dataflow(event_processor::EXECUTE_IMMEDIATELY);

        // Trigger an event - this will run the dataflow immediately.
        ob_start();
        $event = $this->trigger_event();
        ob_end_clean();

        // Check that it executed correctly.
        $stepoutput = json_decode(file_get_contents($this->outputpath));
        $this->assertCount(1, $stepoutput);
        $this->assertEquals((object) $event->get_data(), $stepoutput[0]->eventdata);

        // Since the execution is immediate, there should not be any flows waiting or events recorded.
        $awaitingrun = event_processor::get_flows_awaiting_run();
        $this->assertCount(0, $awaitingrun);
        $eventsrecorded = event_processor::get_events_for_dataflow($dataflow->id);
        $this->assertCount(0, $eventsrecorded);
    }

    /**
     * Tests running the event when it is a dry run.
     * In a dry run, events are not consumed.
     *
     * @covers \tool_dataflows\local\event_processor::process_event
     * @covers \tool_dataflows\local\event_processor::add_triggered_event
     * @covers \tool_dataflows\local\event_processor::consume_event
     */
    public function test_process_event_immediate_dryrun() {
        set_config('permitted_dirs', '/tmp', 'tool_dataflows');

        $dataflow = $this->create_dataflow(event_processor::EXECUTE_ADHOCQUEUED);
        $this->trigger_event();

        $awaitingrun = event_processor::get_flows_awaiting_run();
        $this->assertCount(1, $awaitingrun);
        $eventsrecorded = event_processor::get_events_for_dataflow($dataflow->id);
        $this->assertCount(1, $eventsrecorded);

        // Trigger the dataflow as a dry run.
        ob_start();
        $engine = new engine($dataflow, true);
        $engine->execute();
        ob_get_clean();

        // Events should not have been consumed, since it was a dry run.
        $awaitingrun = event_processor::get_flows_awaiting_run();
        $this->assertCount(1, $awaitingrun);
        $eventsrecorded = event_processor::get_events_for_dataflow($dataflow->id);
        $this->assertCount(1, $eventsrecorded);
    }

    /**
     * Tests event observer function where the event reader is disabled.
     * When it is disabled, it should NOT capture events.
     *
     * @covers \tool_dataflows\local\event_processor::process_event
     * @covers \tool_dataflows\local\event_processor::add_triggered_event
     * @covers \tool_dataflows\local\event_processor::consume_event
     */
    public function test_process_event_disabled() {
        set_config('permitted_dirs', '/tmp', 'tool_dataflows');

        // Create a dataflow and disable it.
        $dataflow = $this->create_dataflow(event_processor::EXECUTE_ADHOCQUEUED);
        $dataflow->enabled = false;
        $dataflow->save();

        // Trigger an event - this should do nothing.
        $this->trigger_event();

        $awaitingrun = event_processor::get_flows_awaiting_run();
        $this->assertCount(0, $awaitingrun);
        $eventsrecorded = event_processor::get_events_for_dataflow($dataflow->id);
        $this->assertCount(0, $eventsrecorded);
    }

    /**
     * Tests that events are removed when a reader step that captured them is deleted.
     *
     * @covers \tool_dataflows\local\event_processor::process_event
     * @covers \tool_dataflows\local\event_processor::add_triggered_event
     * @covers \tool_dataflows\local\event_processor::consume_event
     */
    public function test_event_reader_deleted() {
        set_config('permitted_dirs', '/tmp', 'tool_dataflows');

        // Create a dataflow and record an event for it.
        $dataflow = $this->create_dataflow(event_processor::EXECUTE_ADHOCQUEUED);
        $this->trigger_event();

        $awaitingrun = event_processor::get_flows_awaiting_run();
        $this->assertCount(1, $awaitingrun);
        $eventsrecorded = event_processor::get_events_for_dataflow($dataflow->id);
        $this->assertCount(1, $eventsrecorded);

        // Delete the reader step.
        $readerstep = $dataflow->get_steps()->reader;
        $dataflow->remove_step($readerstep);

        // Event should be deleted and so the dataflow will no longer be awaiting run.
        $awaitingrun = event_processor::get_flows_awaiting_run();
        $this->assertCount(0, $awaitingrun);
        $eventsrecorded = event_processor::get_events_for_dataflow($dataflow->id);
        $this->assertCount(0, $eventsrecorded);
    }

    /**
     * Tests that events are removed when a reader step that has captured them
     * has had its configuration changed and the trigger event changed.
     *
     * @covers \tool_dataflows\local\event_processor::process_event
     * @covers \tool_dataflows\local\event_processor::add_triggered_event
     * @covers \tool_dataflows\local\event_processor::consume_event
     */
    public function test_event_trigger_event_changed() {
        set_config('permitted_dirs', '/tmp', 'tool_dataflows');

        // Create a dataflow and record an event for it.
        $dataflow = $this->create_dataflow(event_processor::EXECUTE_ADHOCQUEUED);
        $this->trigger_event();

        $awaitingrun = event_processor::get_flows_awaiting_run();
        $this->assertCount(1, $awaitingrun);
        $eventsrecorded = event_processor::get_events_for_dataflow($dataflow->id);
        $this->assertCount(1, $eventsrecorded);

        // If the event name is not changed the events are not deleted.
        $readerstep = $dataflow->get_steps()->reader;
        $readerstep->config = Yaml::dump([
            'eventname' => '\core\event\course_viewed',
            'executionpolicy' => event_processor::EXECUTE_ADHOCQUEUED
        ]);
        $readerstep->upsert();

        $awaitingrun = event_processor::get_flows_awaiting_run();
        $this->assertCount(1, $awaitingrun);
        $eventsrecorded = event_processor::get_events_for_dataflow($dataflow->id);
        $this->assertCount(1, $eventsrecorded);

        // Change the event in the reader step, this should clear the events queued.
        $readerstep = $dataflow->get_steps()->reader;
        $readerstep->config = Yaml::dump([
            'eventname' => '\core\event\profile_viewed',
            'executionpolicy' => event_processor::EXECUTE_ADHOCQUEUED
        ]);
        $readerstep->upsert();

        // Event should be deleted and so the dataflow will no longer be awaiting run.
        $awaitingrun = event_processor::get_flows_awaiting_run();
        $this->assertCount(0, $awaitingrun);
        $eventsrecorded = event_processor::get_events_for_dataflow($dataflow->id);
        $this->assertCount(0, $eventsrecorded);
    }

    /**
     * Tests a dataflow with processing set to 'adhoc' but concurrency is disabled.
     * This should switch the processing to 'adhocqueued' since 'adhoc' is inherently parallel.
     *
     * @covers \tool_dataflows\local\event_processor::process_event
     * @covers \tool_dataflows\local\event_processor::add_triggered_event
     * @covers \tool_dataflows\local\event_processor::consume_event
     */
    public function test_non_concurrent_adhoc_dataflow() {
        set_config('permitted_dirs', '/tmp', 'tool_dataflows');

        // Create a dataflow with adhoc processing, but concurrency disabled.
        $dataflow = $this->create_dataflow(event_processor::EXECUTE_ADHOC, false);
        $this->trigger_event();

        // The flow should NOT have created an adhoc task, since concurrency is disabled.
        $tasks = \core\task\manager::get_adhoc_tasks('\tool_dataflows\task\process_dataflow_ad_hoc');
        $this->assertCount(0, $tasks);

        // It should still have been queued, so the flow should still be awaiting run.
        $awaitingrun = event_processor::get_flows_awaiting_run();
        $this->assertCount(1, $awaitingrun);
        $this->assertContains((string) $dataflow->id, array_column($awaitingrun, 'dataflowid'));

        $eventsrecorded = event_processor::get_events_for_dataflow($dataflow->id);
        $this->assertCount(1, $eventsrecorded);
    }

    /**
     * Triggers a course viewed event for testing the trigger.
     *
     * @return \core\event\course_viewed
     */
    private function trigger_event(): \core\event\course_viewed {
        $event = \core\event\course_viewed::create([
            'context' => \context_course::instance($this->course->id)
        ]);
        $event->trigger();
        return $event;
    }

    /**
     * Dataflow creation helper function.
     * Creates a dataflow with an event reader and stream writer.
     *
     * @param string $policy the reader event's execution policy.
     * @param bool $concurrent if the dataflow should have concurrency enabled.
     * @return dataflow dataflow
     */
    private function create_dataflow(string $policy, bool $concurrent = true) {
        // Create the dataflow.
        $dataflow = new dataflow();
        $dataflow->name = 'testflow';
        $dataflow->enabled = true;
        $dataflow->concurrencyenabled = $concurrent;
        $dataflow->save();

        $steps = [];

        $reader = new step();
        $reader->name = 'reader';
        $reader->type = 'tool_dataflows\local\step\trigger_event';
        $reader->config = Yaml::dump([
            'eventname' => '\core\event\course_viewed',
            'executionpolicy' => $policy
        ]);

        $dataflow->add_step($reader);
        $steps[$reader->id] = $reader;

        $this->outputpath = tempnam('', 'tool_dataflows');
        $writer = new step();
        $writer->name = 'stream-writer';
        $writer->type = 'tool_dataflows\local\step\writer_stream';
        $writer->config = Yaml::dump([
            'format' => 'json',
            'streamname' => $this->outputpath,
        ]);
        $writer->depends_on([$reader]);
        $dataflow->add_step($writer);

        $this->reader = $reader;
        $this->writer = $writer;

        return $dataflow;
    }
}
