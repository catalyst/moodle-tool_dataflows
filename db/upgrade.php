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

/**
 * Plugin upgrade code
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Function to upgrade tool_dataflows.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_tool_dataflows_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2022070501) {

        // Changing type of field timestarted, timepaused, timefinished on table
        // tool_dataflows_runs to number and precision to (14, 4).
        $table = new xmldb_table('tool_dataflows_runs');

        foreach (['timestarted', 'timepaused', 'timefinished'] as $fieldname) {
            $field = new xmldb_field($fieldname, XMLDB_TYPE_NUMBER, '14, 4', null, null, null, null, null);
            $dbman->change_field_type($table, $field);
            $dbman->change_field_precision($table, $field);
        }

        // Dataflows savepoint reached.
        upgrade_plugin_savepoint(true, 2022070501, 'tool', 'dataflows');
    }

    if ($oldversion < 2022071900) {

        // Define table tool_dataflows_versions to be created.
        $table = new xmldb_table('tool_dataflows_versions');

        // Adding fields to table tool_dataflows_versions.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('dataflowid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('confighash', XMLDB_TYPE_CHAR, '40', null, null, null, null);
        $table->add_field('configyaml', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table tool_dataflows_versions.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('dataflowid', XMLDB_KEY_FOREIGN, ['dataflowid'], 'tool_dataflows', ['id']);

        // Conditionally launch create table for tool_dataflows_versions.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define field confighash to be added to tool_dataflows.
        $table = new xmldb_table('tool_dataflows');
        $field = new xmldb_field('confighash', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'usermodified');

        // Conditionally launch add field confighash.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Dataflows savepoint reached.
        upgrade_plugin_savepoint(true, 2022071900, 'tool', 'dataflows');
    }

    if ($oldversion < 2022072101) {

        // Define field position to be added to tool_dataflows_step_depends.
        $table = new xmldb_table('tool_dataflows_step_depends');
        $field = new xmldb_field('position', XMLDB_TYPE_INTEGER, '4', null, null, null, null, 'dependson');

        // Conditionally launch add field position.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Dataflows savepoint reached.
        upgrade_plugin_savepoint(true, 2022072101, 'tool', 'dataflows');
    }

    return true;
}
