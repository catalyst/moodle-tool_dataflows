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
    public static function get_scheduled_times(int $dataflowid) {
        global $DB;

        return $DB->get_record( self::TABLE, ['dataflowid' => $dataflowid], 'lastruntime, nextruntime');
    }

    /**
     * Get the last time a dataflow was scheduled to run.
     *
     * @param int $dataflowid
     * @return int Timestamp. Defaults to zero (effectively never) if no entry exists.
     * @throws \dml_exception
     */
    public static function get_last_scheduled_time(int $dataflowid): int {
        global $DB;

        $record = $DB->get_record( self::TABLE, ['dataflowid' => $dataflowid]);
        if ($record === false) {
            return 0; // 1st Jan 1970 is far enough in the past to be equivalent to 'never'.
        } else {
            return $record->lastruntime;
        }
    }

    /**
     * Get the next time a dataflow is scheduled to run.
     *
     * @param int $dataflowid
     * @return int Timestamp. Defaults to current time if no entry exists.
     * @throws \dml_exception
     */
    public static function get_next_scheduled_time(int $dataflowid): int {
        global $DB;

        $record = $DB->get_record( self::TABLE, ['dataflowid' => $dataflowid]);
        if ($record === false) {
            return time();
        } else {
            return $record->nextruntime;
        }
    }

    /**
     * Determine the next time a dataflow should run. Skips forward using the timestring until a time after cutoff is reached.
     *
     * @param string $timestr strtotime() compatible string.
     * @param int|null $oldtime Reference time. If null, the current time is used.
     * @param int|null $curtime Cutoff time. If null, the current time is used.
     * @return int|false Timestamp or false on failure.
     */
    public static function determine_next_scheduled_time(string $timestr, ?int $oldtime = null, ?int $curtime = null) {
        $curtime = $curtime ?? time();
        $oldtime = $oldtime ?? $curtime;

        do {
            $newtime = strtotime($timestr, $oldtime);
            if ($newtime <= $oldtime) {
                return false;
            }
            $oldtime = $newtime;
        } while ($newtime <= $curtime);
        return $newtime;
    }

    /**
     * Update an entry in the database for a dataflow.
     *
     * @param int $dataflowid
     * @param int $newtime The new time for the next scheduled run.
     * @param int|null $oldtime The last time the dataflow ran. If null, the value will be unchanged.
     * @throws \dml_exception
     */
    public static function set_scheduled_times(int $dataflowid, int $newtime, ?int $oldtime = null) {
        global $DB;

        $obj = (object) ['nextruntime' => $newtime, 'dataflowid' => $dataflowid];
        if (!is_null($oldtime)) {
            $obj->lastruntime = $oldtime;
        }
        $id = $DB->get_field(self::TABLE, 'id', ['dataflowid' => $dataflowid]);
        if ($id === false) {
            $DB->insert_record(self::TABLE, $obj);
        } else {
            $obj->id = $id;
            $DB->update_record(self::TABLE, $obj);
        }
    }

    /**
     * Gets a list of dataflows that are due to run based on the given reference time.
     *
     * @param int|null $reftime The time to determine the list on. Will default to current time if null.
     * @return array List of dataflow IDs.
     * @throws \dml_exception
     */
    public static function get_due_dataflows(?int $reftime = null): array {
        global $DB;
        if (is_null($reftime)) {
            $reftime = time();
        }
        return $DB->get_records_select_menu(self::TABLE, 'nextruntime <= :time', ['time' => $reftime], '', 'id, dataflowid');
    }
}
