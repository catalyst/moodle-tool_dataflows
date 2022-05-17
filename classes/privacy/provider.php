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

namespace tool_dataflows\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Currently reports no privatge data being kept. This may need to change.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider,
                          \core_privacy\local\request\plugin\provider,
                          \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection) : collection {

        $collection->add_database_table(
            'tool_dataflows',
            [
                'userid' => 'privacy:metadata:dataflows:userid',
                'timecreated' => 'privacy:metadata:dataflows:timecreated',
                'usermodified' => 'privacy:metadata:dataflows:usermodified',
                'timemodified' => 'privacy:metadata:dataflows:timemodified',
            ],
            'privacy:metadata:dataflows'
        );
        $collection->add_database_table(
            'tool_dataflows_steps',
            [
                'userid' => 'privacy:metadata:steps:userid',
                'timecreated' => 'privacy:metadata:steps:timecreated',
                'usermodified' => 'privacy:metadata:steps:usermodified',
                'timemodified' => 'privacy:metadata:steps:timemodified',
            ],
            'privacy:metadata:steps'
        );
        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int $userid The user to search.
     * @return  contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $contextlist = new contextlist();
        $contextlist->add_system_context();
        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param  approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                $list = [];
                // Dataflows with a matching userid.
                $rows = $DB->get_records('tool_dataflows', ['userid' => $userid]);
                foreach ($rows as $row) {
                    $list[] = [
                        'name' => $row->name,
                        'userid' => $userid,
                        'timecreated' => $row->timecreated,
                    ];
                }
                // Dataflows with a matching usermodified.
                $rows = $DB->get_records('tool_dataflows', ['usermodified' => $userid]);
                foreach ($rows as $row) {
                    $list[] = [
                        'name' => $row->name,
                        'usermodified' => $userid,
                        'timemodified' => $row->timemodified,
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:metadata:dataflows', 'tool_dataflows')],
                    (object) $list
                );

                // Dataflow steps with a matching userid.
                $rows = $DB->get_records('tool_dataflows_steps', ['userid' => $userid]);
                foreach ($rows as $row) {
                    $list[] = [
                        'name' => $row->name,
                        'userid' => $userid,
                        'timecreated' => $row->timecreated,
                        'type' => $row->type,
                        'config' => $row->config,
                    ];
                }
                // Dataflow steps with a matching usermodified.
                $rows = $DB->get_records('tool_dataflows_steps', ['usermodified' => $userid]);
                foreach ($rows as $row) {
                    $list[] = [
                        'name' => $row->name,
                        'usermodified' => $userid,
                        'timemodified' => $row->timemodified,
                        'type' => $row->type,
                        'config' => $row->config,
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:metadata:dataflows', 'tool_dataflows_steps')],
                    (object) $list
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param  context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $DB->delete_records('tool_dataflows_step_depends', []); // Must be deleted to ensure steps are deletable and so forth.
            $DB->delete_records('tool_dataflows_steps', []);
            $DB->delete_records('tool_dataflows', []);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param  approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                self::delete_data_by_userid($userid);
            }
        }
    }

    /**
     * Deletes all data relating for the provided userid within this plugin.
     *
     * @param      int $userid
     */
    public static function delete_data_by_userid(int $userid) {
        global $DB;
        // Get all the steps relating to this user (both userid and usermodified, and those in the dataflows table).
        $stepids = [];
        $dataflowids = [];
        // Dataflow ids from userid.
        $records = $DB->get_records('tool_dataflows', ['userid' => $userid]);
        $dataflowids = array_merge($dataflowids, array_column($records, 'id'));
        // Dataflow ids from usermodified.
        $records = $DB->get_records('tool_dataflows', ['usermodified' => $userid]);
        $dataflowids = array_merge($dataflowids, array_column($records, 'id'));

        // Step ids from dataflowid.
        list($insql, $inparams) = $DB->get_in_or_equal($dataflowids);
        $sql = "SELECT * FROM {tool_dataflows_steps} WHERE dataflowid $insql";
        $records = $DB->get_records_sql($sql, $inparams);
        $stepids = array_merge($stepids, array_column($records, 'id'));
        // Step ids from userid.
        $records = $DB->get_records('tool_dataflows_steps', ['userid' => $userid]);
        $stepids = array_merge($stepids, array_column($records, 'id'));
        // Step ids from usermodified.
        $records = $DB->get_records('tool_dataflows_steps', ['usermodified' => $userid]);
        $stepids = array_merge($stepids, array_column($records, 'id'));

        // Delete any related step_depends records.
        list($insql, $inparams) = $DB->get_in_or_equal($stepids);
        $sql = "stepid $insql";
        $DB->delete_records_select('tool_dataflows_step_depends', $sql, $inparams);
        $sql = "dependson $insql";
        $DB->delete_records_select('tool_dataflows_step_depends', $sql, $inparams);
        // Delete any related step records.
        $sql = "id $insql";
        $DB->delete_records_select('tool_dataflows_steps', $sql, $inparams);
        // Delete any related dataflow records.
        list($insql, $inparams) = $DB->get_in_or_equal($dataflowids);
        $sql = "id $insql";
        $DB->delete_records_select('tool_dataflows', $sql, $inparams);
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param  userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $sql = "SELECT * FROM {tool_dataflows}";
            $userlist->add_from_sql('userid', $sql, []);
            $sql = "SELECT * FROM {tool_dataflows}";
            $userlist->add_from_sql('usermodified', $sql, []);
            $sql = "SELECT * FROM {tool_dataflows_steps}";
            $userlist->add_from_sql('userid', $sql, []);
            $sql = "SELECT * FROM {tool_dataflows_steps}";
            $userlist->add_from_sql('usermodified', $sql, []);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param  approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $users = $userlist->get_users();
            foreach ($users as $user) {
                self::delete_data_by_userid($user->id);
            }
        }
    }
}
