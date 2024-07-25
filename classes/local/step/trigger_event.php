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

namespace tool_dataflows\local\step;

use tool_dataflows\local\event_processor;
use tool_dataflows\local\execution\iterators\iterator;
use tool_dataflows\local\execution\iterators\dataflow_iterator;
use tool_dataflows\local\execution\flow_engine_step;
use tool_dataflows\local\execution\engine_step;
use tool_dataflows\local\execution\engine;
/**
 * Event reader step
 *
 * @package   tool_dataflows
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trigger_event extends trigger_step {

    /**
     * Executes the reader event by consuming the event from the queue and add the event data to the variables for this step.
     *
     * @param mixed|null $input
     * @return mixed
     */
    public function execute($input = null) {
        $variables = $this->get_variables();
        $variables->set('event', $input->eventdata);
        return $input;
    }

    /**
     * A list of outputs and their description if applicable.
     *
     * @return array of outputs
     */
    public function define_outputs(): array {
        return ['event' => get_string('trigger_event:variable:event', 'tool_dataflows')];
    }

    /**
     * Returns the event iterator. Reads events from the database queue table and returns them as an array iterator.
     *
     * @return iterator Array iterator containing the events queued for this dataflow.
     */
    public function get_iterator(): iterator {
        // First get a lock to ensure no other parallel running task is reading the queue.
        $dataflowid = $this->stepdef->get('dataflowid');
        $queuelock = event_processor::get_queue_lock($dataflowid);

        try {
            $eventarray = event_processor::get_events_for_dataflow($dataflowid);

            // If not a dry run, we consume the events while holding the lock.
            // This ensures no other parallel running task also read these events
            // before they are removed from the queue.
            if (!$this->enginestep->engine->isdryrun) {
                foreach ($eventarray as $event) {
                    event_processor::consume_event($event->id);
                }
            }

            $queuelock->release();
        } catch (\Throwable $e) {
            // Release the queue lock and re-throw the exception.
            $queuelock->release();
            throw $e;
        }

        // Re-parse the stringified JSON event data for each.
        $eventarray = array_map(function ($e) {
            $e->eventdata = json_decode($e->eventdata);
            return $e;
        }, $eventarray);

        return new dataflow_iterator($this->enginestep, new \ArrayIterator($eventarray));
    }

    /**
     * Get the default data.
     *
     * @param \stdClass $data
     * @return \stdClass
     */
    public function form_get_default_data(\stdClass $data): \stdClass {
        $data = parent::form_get_default_data($data);

        if (!isset($data->config_eventname)) {
            $data->config_eventname = '';
        }

        if (!isset($data->config_executionpolicy)) {
            $data->config_executionpolicy = event_processor::EXECUTE_ADHOCQUEUED;
        }

        return $data;
    }

    /**
     * Defines eventname and execution fields.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'eventname' => ['type' => PARAM_TEXT],
            'executionpolicy' => ['type' => PARAM_TEXT],
        ];
    }

    /**
     * Returns a list of events where the event is not deprecated.
     * Used for the event picker form element.
     *
     * @return array
     */
    private function get_events_list() {
        $eventlist = \tool_monitor\eventlist::get_all_eventlist();
        $pluginlist = \tool_monitor\eventlist::get_plugin_list($eventlist);
        $plugineventlist = [];
        foreach ($pluginlist as $plugintype => $plugins) {
            foreach ($plugins as $plugin => $pluginname) {
                foreach ($eventlist[$plugin] as $event => $eventname) {
                    // Filter out events which cannot be triggered for some reason.
                    if (!$event::is_deprecated()) {
                        $plugineventlist[$event] = "{$pluginname}: {$eventname}";
                    }
                }
            }
        }
        return $plugineventlist;
    }

    /**
     * Adds event picker and execution policy options to step form.
     *
     * @param \MoodleQuickForm $mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {
        // Get all the possible events for the event picker.
        $eventpickeroptions = array_merge(
            ['' => get_string('choosedots')],
            $this->get_events_list()
        );

        $eventpickersettings = [
            'noselectionstring' => get_string('choosedots'),
            'allowmultiple' => false,
        ];

        $mform->addElement(
            'autocomplete',
            'config_eventname',
            get_string('trigger_event:form:eventname', 'tool_dataflows'),
            $eventpickeroptions,
            $eventpickersettings
        );
        $mform->setType('config_eventname', PARAM_TEXT);
        $mform->addHelpButton('config_eventname', 'trigger_event:form:eventname', 'tool_dataflows');

        $executionoptions = [
            event_processor::EXECUTE_IMMEDIATELY => get_string('trigger_event:policy:immediate', 'tool_dataflows'),
            event_processor::EXECUTE_ADHOC => get_string('trigger_event:policy:adhoc', 'tool_dataflows'),
            event_processor::EXECUTE_ADHOCQUEUED => get_string('trigger_event:policy:adhocqueued', 'tool_dataflows'),
        ];

        $mform->addElement('select', 'config_executionpolicy',
            get_string('trigger_event:form:executionpolicy', 'tool_dataflows'), $executionoptions);
        $mform->setType('config_executionpolicy', PARAM_TEXT);
        $mform->addHelpButton('config_executionpolicy', 'trigger_event:form:executionpolicy', 'tool_dataflows');
    }

    /**
     * On delete, delete all events recorded for this flow.
     */
    public function on_delete() {
        // Consume all events recorded for this dataflow to avoid lingering events.
        $dataflowid = $this->stepdef->get('dataflowid');
        $this->clear_dataflow_events($dataflowid);
    }

    /**
     * On save, if the event was changed, delete all record events.
     * Otherwise the reader could still be called with the old events.
     */
    public function on_save() {
        // See if any events were recorded for a different event.
        $dataflowid = $this->stepdef->get('dataflowid');
        $eventarray = event_processor::get_events_for_dataflow($dataflowid);
        $neweventname = $this->stepdef->get_redacted_config()->eventname;

        // Find any events recorded that are not the same as the new event config.
        $differentevents = array_filter($eventarray, function ($recordedevent) use ($neweventname) {
            $data = json_decode($recordedevent->eventdata);
            $recordedeventname = $data->eventname;
            return $recordedeventname !== $neweventname;
        });

        // If at least 1 event recorded is different, we clear all of them.
        if (count($differentevents) > 0) {
            $this->clear_dataflow_events($dataflowid);
        }
    }

    /**
     * Deletes all the events recorded for a given dataflow
     *
     * @param int $dataflowid
     */
    private function clear_dataflow_events(int $dataflowid) {
        $eventarray = event_processor::get_events_for_dataflow($dataflowid);
        foreach ($eventarray as $event) {
            event_processor::consume_event($event->id);
        }
    }

    /**
     * Validates config, ensures the event is set and the execution policy is allowed.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        $errors = [];
        if (empty($config->eventname)) {
            $errors['config_eventname'] = get_string(
                'config_field_missing',
                'tool_dataflows',
                get_string('trigger_event:form:eventname', 'tool_dataflows'),
                true
            );
        }

        $possiblepolicies = [
            event_processor::EXECUTE_ADHOC,
            event_processor::EXECUTE_IMMEDIATELY,
            event_processor::EXECUTE_ADHOCQUEUED,
        ];

        if (empty($config->executionpolicy)) {
            $errors['config_executionpolicy'] = get_string(
                'config_field_missing',
                'tool_dataflows',
                get_string('trigger_event:form:executionpolicy', 'tool_dataflows'),
                true
            );
        } else if (!in_array($config->executionpolicy, $possiblepolicies)) {
            $errors['config_executionpolicy'] = get_string(
                'config_field_invalid',
                'tool_dataflows',
                get_string('trigger_event:form:executionpolicy', 'tool_dataflows'),
                true
            );
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * The event reader does have a producing iterator.
     *
     * @return bool true
     */
    public function has_producing_iterator(): bool {
        return true;
    }

    /**
     * This step uses a flow engine step instead of the default connector engine step.
     *
     * @param engine $engine
     * @return engine_step
     */
    protected function generate_engine_step(engine $engine): engine_step {
        return new flow_engine_step($engine, $this->stepdef, $this);
    }

    /**
     * This step is a flow step.
     *
     * @return bool true
     */
    public function is_flow(): bool {
        return true;
    }
}
