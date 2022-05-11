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
 * Language strings
 *
 * @package    tool_dataflows
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Dataflows';
$string['pluginmanage'] = 'Manage flows';
$string['dataflows'] = 'Dataflows';

// General settings.
$string['pluginsettings'] = 'General settings';
$string['enabled'] = 'Enable/disable this plugin';
$string['enabled_help'] = '';

// Manage flows / Overview.
$string['overview'] = 'Overview';
$string['field_preview'] = 'Preview';
$string['field_userid'] = 'User';
$string['field_manage'] = 'Manage';
$string['field_stepcount'] = '# Steps';
$string['import_dataflow'] = 'Import Dataflow';

// Dataflows (form).
$string['field_name'] = 'Name';
$string['update_dataflow'] = 'Update Dataflow';
$string['new_dataflow'] = 'New Dataflow';

// Dataflow steps (table)
$string['steps'] = 'Steps';

// Step (form).
$string['field_config'] = 'Configuration';
$string['field_type'] = 'Type of Step';
$string['update_step'] = 'Update Step';
$string['new_step'] = 'New Step';

// Warnings / Errors
$string['dataflowrequiredforstepcreation'] = 'Dataflow selected does not exist. Please create one before adding a step.';

// Privacy.
$string['privacy:metadata'] = 'No personal information is stored';
$string['task:processdataflows'] = 'Process dataflows scheduled task.';
