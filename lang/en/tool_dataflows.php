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

// Dataflow steps (table).
$string['steps'] = 'Steps';
$string['field_dependson'] = 'Depends On';

// Step (form).
$string['field_description'] = 'Description';
$string['field_internalid'] = 'Internal ID';
$string['field_internalid_help'] = 'A kebab cased reference to the step, unique to this dataflow. This can be used when referencing dependencies and inputs from other steps. Leaving this field blank will attempt to use a sluggified version of the name value.';
$string['field_config'] = 'Configuration';
$string['field_type'] = 'Type of Step';
$string['update_step'] = 'Update Step';
$string['new_step'] = 'New Step';

// Warnings / Errors.
$string['dataflowisnotavaliddag'] = 'Dataflow is not a valid Directed Acyclic Graph (DAG).';
$string['dataflowrequiredforstepcreation'] = 'Dataflow selected does not exist. Please create one before adding a step.';
$string['stepdependencydoesnotexist'] = 'The step dependency "{$a}" does not exist';
$string['stepinvalidinputstreamcount'] = 'Invalid number of input streams for {$a->name} (Found: {$a->found}, Expected between {$a->min} and {$a->max})';
$string['stepinvalidoutputstreamcount'] = 'Invalid number of output streams for {$a->name} (Found: {$a->found}, Expected between {$a->min} and {$a->max})';

// Privacy.
$string['privacy:metadata:dataflows'] = 'Data from the configured dataflows';
$string['privacy:metadata:steps'] = 'Data from the configured dataflow steps';
$string['privacy:metadata:dataflows:userid'] = 'The id of the user who created this dataflow';
$string['privacy:metadata:dataflows:timecreated'] = 'The timestamp of when the dataflow was created';
$string['privacy:metadata:dataflows:usermodified'] = 'The id of the user who modified this dataflow';
$string['privacy:metadata:dataflows:timemodified'] = 'The timestamp of when the dataflow was modified';
$string['privacy:metadata:steps:userid'] = 'The id of the user who created this step';
$string['privacy:metadata:steps:timecreated'] = 'The timestamp of when the step was created';
$string['privacy:metadata:steps:usermodified'] = 'The id of the user who modified this step';
$string['privacy:metadata:steps:timemodified'] = 'The timestamp of when the step was modified';

// Tasks.
$string['task:processdataflows'] = 'Process dataflows scheduled task.';
