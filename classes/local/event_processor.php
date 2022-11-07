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

namespace tool_dataflows\local;

/**
 * Event processor
 *
 * @package   tool_dataflows
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_processor {

    /**
     * Executes the event's linked dataflow immediately within the same thread.
     *
     * @var string
     */
    public const EXECUTE_IMMEDIATELY = 'immediately';

    /**
     * Executes the event's linked dataflow via an adhoc task.
     *
     * @var string
     */
    public const EXECUTE_ADHOC = 'adhoc';

    /**
     * Queues the event to be processed in the next cron call.
     *
     * @var string
     */
    public const EXECUTE_ADHOCQUEUED = 'adhocqueued';

    /**
     * Table for storing captured events.
     *
     * @var string
     */
    public const TABLE = 'tool_dataflows_events';

    /**
     * Records a triggered event.
     *
     * @param int $dataflowid
     * @param int $stepid
     * @param string $eventdata JSON encoded string of data from the event captured.
     */
    public static function add_triggered_event(int $dataflowid, int $stepid, string $eventdata): void {
        global $DB;

        $data = (object) [
            'dataflowid' => $dataflowid,
            'stepid' => $stepid,
            'eventdata' => $eventdata
        ];

        // Only insert new events.
        $DB->insert_record(self::TABLE, $data);
    }

    /**
     * Consumes the event (deletes it).
     *
     * @param int $id ID of row in event queue table.
     * @return bool true if delete successful
     */
    public static function consume_event(int $id): bool {
        global $DB;
        return $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * Gets dataflows that have events queued for running.
     *
     * @return array of dataflow records, each containing the id of the dataflow.
     */
    public static function get_flows_awaiting_run(): array {
        global $DB;
        $uniquedataflows = $DB->get_records_select(self::TABLE, '', null, '', "DISTINCT(dataflowid)");
        return $uniquedataflows;
    }

    /**
     * Gets all events queued for a specific dataflow.
     *
     * @param int $dataflowid
     * @return array of events for the given dataflow
     */
    public static function get_events_for_dataflow(int $dataflowid): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['dataflowid' => $dataflowid]);
    }

    /**
     * Listens to Moodle events and processes the corresponding event triggers.
     *
     * @param \core\event\base $event
     * @throws \coding_exception if the execution policy is invalid
     */
    public static function process_event(\core\event\base $event) {
        // Get any triggers listening to this event.
        $eventdata = $event->get_data();
        $triggersteps = self::get_listening_triggers($eventdata['eventname']);

        // Trigger each workflow depending on its settings.
        foreach ($triggersteps as $step) {
            $dataflow = $step->get_dataflow();
            $executionpolicy = $step->get_redacted_config()->executionpolicy;
            $concurrent = $dataflow->is_concurrency_enabled();

            // If not concurrent & adhoc is selected, we switch it to queue serial processing.
            if (!$concurrent && $executionpolicy == self::EXECUTE_ADHOC) {
                $executionpolicy = self::EXECUTE_ADHOCQUEUED;
            }

            switch($executionpolicy) {
                case self::EXECUTE_IMMEDIATELY:
                    // Record the triggered event to save the event data.
                    self::add_triggered_event($dataflow->id, $step->id, json_encode($eventdata));

                    // Execute immediately within this thread.
                    ob_start();
                    \tool_dataflows\task\process_dataflows::execute_dataflow($dataflow->id);
                    ob_end_clean();

                    break;
                case self::EXECUTE_ADHOC:
                    // Record the triggered event to save the event data.
                    self::add_triggered_event($dataflow->id, $step->id, json_encode($eventdata));

                    // Create adhoc task to execute.
                    $record = (object) [
                        'dataflowid' => $dataflow->id
                    ];
                    \tool_dataflows\task\process_dataflow_ad_hoc::execute_from_record($record);

                    break;
                case self::EXECUTE_ADHOCQUEUED:
                    // Only record that this event was triggered.
                    // The next CRON call will then execute the event.
                    self::add_triggered_event($dataflow->id, $step->id, json_encode($eventdata));
                    break;
                default:
                    throw new \coding_exception("Unknown execution policy");
            }
        }
    }

    /**
     * Finds trigger steps that are listening to this event.
     *
     * @param string $eventname
     * @return array of steps
     */
    private static function get_listening_triggers(string $eventname): array {
        global $DB;

        // Get all event triggers.
        $type = $DB->sql_compare_text('tool_dataflows\local\step\trigger_event');
        $eventsteps = \tool_dataflows\step::get_records(['type' => $type]);

        $stepslistening = array_filter($eventsteps, function ($step) use ($eventname) {
            // Ensure the configured event for the step matches the event name.
            if ($step->get_redacted_config()->eventname !== $eventname) {
                return false;
            }

            // Ensure the dataflow this step is contained in is actually enabled.
            if (!$step->get_dataflow()->enabled) {
                return false;
            }

            return true;
        });

        return $stepslistening;
    }
}
