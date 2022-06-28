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

use core_message\time_last_message_between_users;

/**
 * Manages run schedules for dataflow tasks.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scheduler {
    public const TABLE = 'tool_dataflows_schedule';

    /**
     * Get the scheduled times for a dataflow.
     * @param int $dataflowid
     * @return array|false
     * @throws \dml_exception
     */
    public static function get_scheduled_times(int $stepid) {
        global $DB;

        return $DB->get_record( self::TABLE, ['stepid' => $stepid], 'lastruntime, nextruntime');
    }

    /**
     * Update an entry in the database for a dataflow.
     *
     * @param int $dataflowid
     * @param int $newtime The new time for the next scheduled run.
     * @param int|null $oldtime The last time the dataflow ran. If null, the value will be unchanged.
     * @throws \dml_exception
     */
    public static function set_scheduled_times(int $dataflowid, int $stepid, int $newtime, ?int $oldtime = null) {
        global $DB;

        $obj = (object) ['nextruntime' => $newtime, 'dataflowid' => $dataflowid, 'stepid' => $stepid];
        if (!is_null($oldtime)) {
            $obj->lastruntime = $oldtime;
        }
        $id = $DB->get_field(self::TABLE, 'id', ['dataflowid' => $dataflowid, 'stepid' => $stepid]);
        if ($id === false) {
            $DB->insert_record(self::TABLE, $obj);
        } else {
            $obj->id = $id;
            $DB->update_record(self::TABLE, $obj);
        }
    }

    /**
     * Gets a list of dataflows and timestamps that are due to run based on the given reference time.
     *
     * @param int|null $reftime The time to determine the list on. Will default to current time if null.
     * @return array List of timestamps, indexed by dataflow ID.
     * @throws \dml_exception
     */
    public static function get_due_dataflows(?int $reftime = null): array {
        global $DB;
        if (is_null($reftime)) {
            $reftime = time();
        }
        return $DB->get_records_select_menu(self::TABLE, 'nextruntime <= :time', ['time' => $reftime], '', 'dataflowid, nextruntime');
    }
}
