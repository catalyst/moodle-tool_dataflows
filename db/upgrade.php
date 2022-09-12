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

    if ($oldversion < 2022072502) {
        // Define field position to be added to tool_dataflows_step_depends.
        $table = new xmldb_table('tool_dataflows_step_depends');
        $field = new xmldb_field('position', XMLDB_TYPE_INTEGER, '4', null, null, null, null, 'dependson');

        // Conditionally launch add field position.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Dataflows savepoint reached.
        upgrade_plugin_savepoint(true, 2022072502, 'tool', 'dataflows');
    }

    if ($oldversion < 2022080101) {
        $table = new xmldb_table('tool_dataflows');

        $field = new xmldb_field('concurrencyenabled', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'enabled');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table tool_dataflows_versions to be created.
        $table = new xmldb_table('tool_dataflows_lock_metadata');

        // Conditionally launch create table for tool_dataflows_lock_metadata.
        if (!$dbman->table_exists($table)) {
            // Adding fields to table tool_dataflows_lock_metadata.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('dataflowid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timestamp', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('processid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            // Adding keys to table tool_dataflows_lock_metadata.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('dataflowid', XMLDB_KEY_FOREIGN, ['dataflowid'], 'tool_dataflows', ['id']);

            $dbman->create_table($table);

            upgrade_plugin_savepoint(true, 2022080101, 'tool', 'dataflows');
        }
    }

    if ($oldversion < 2022080801) {
        // Changing type of field config on table tool_dataflows_steps to text.
        $table = new xmldb_table('tool_dataflows_steps');
        $field = new xmldb_field('config', XMLDB_TYPE_TEXT, null, null, null, null, null, 'name');

        // Launch change of type for field config.
        $dbman->change_field_type($table, $field);

        // Dataflows savepoint reached.
        upgrade_plugin_savepoint(true, 2022080801, 'tool', 'dataflows');
    }

    if ($oldversion < 2022090600) {
        // Update flows to change instances of case step to switch.
        $records = $DB->get_records(
            'tool_dataflows_steps',
            ['type' => 'tool_dataflows\local\step\flow_logic_case'],
            '',
            'id, type'
        );
        foreach ($records as $record) {
            $record->type = 'tool_dataflows\local\step\flow_logic_switch';
            $DB->update_record('tool_dataflows_steps', $record);
        }
        upgrade_plugin_savepoint(true, 2022090600, 'tool', 'dataflows');
    }

    if ($oldversion < 2022090700 ) {
        // Changing type of field config on table tool_dataflows_steps to text.
        $table = new xmldb_table('tool_dataflows');

        $field = new xmldb_field('config', XMLDB_TYPE_TEXT, null, null, null, null, null, 'concurrencyenabled');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'vars');
        }

        $table = new xmldb_table('tool_dataflows_steps');

        // Vars is a standard field defined in all steps.
        $field = new xmldb_field('vars', XMLDB_TYPE_TEXT, null, null, null, null, null, 'config');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Transfer vars from config field to vars field.
        $records = $DB->get_records('tool_dataflows_steps', null, '', 'id, config');
        foreach ($records as $record) {
            $config = \Symfony\Component\Yaml\Yaml::parse($record->config);
            if (isset($config['outputs'])) {
                $vars = $config['outputs'];
                unset($config['outputs']);
                $record->config = \Symfony\Component\Yaml\Yaml::dump($config);
                $record->vars = \Symfony\Component\Yaml\Yaml::dump($vars);
            } else {
                $record->vars = ''; // Vars field cannot be null.
            }
            $DB->update_record('tool_dataflows_steps', $record);
        }

        // Erase confighash to force it to be recalculated.
        $records = $DB->get_records('tool_dataflows', null, '', 'id');
        foreach ($records as $record) {
            $record->confighash = '';
            $DB->update_record('tool_dataflows', $record);
        }

        // Dataflows savepoint reached.
        upgrade_plugin_savepoint(true, 2022090700, 'tool', 'dataflows');
    }

    if ($oldversion < 2022090700) {
        // Rename field group on table tool_dataflows_logs to loggroup.
        $table = new xmldb_table('tool_dataflows_logs');
        $field = new xmldb_field('group', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'level');

        // Launch rename field loggroup.
        $dbman->rename_field($table, $field, 'loggroup');

        // Dataflows savepoint reached.
        upgrade_plugin_savepoint(true, 2022090700, 'tool', 'dataflows');
    }

    return true;
}
