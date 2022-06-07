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
$string['run_confirm'] = 'Are you sure you want to run the dataflow \'{$a}\' now? The will run on the web server and may take some time to complete.';
$string['run_again'] = 'Run again';
$string['run_now'] = 'Run now';
$string['dry_run'] = 'Dry run';
$string['export'] = 'Export';
$string['import'] = 'Import';

// Dataflows (form).
$string['field_name'] = 'Name';
$string['update_dataflow'] = 'Update Dataflow';
$string['new_dataflow'] = 'New Dataflow';
$string['remove_dataflow'] = 'Remove dataflow';
$string['remove_dataflow_confirm'] = 'Are you sure you want to remove the dataflow \'{$a}\'? This action is irreversible.';
$string['remove_dataflow_successful'] = 'Removed dataflow \'{$a}\' successfully.';

// Dataflow import form.
$string['dataflow_file'] = 'Dataflow file';

// Dataflow steps (table).
$string['back_to'] = 'Back to Dataflow';
$string['field_dependson'] = 'Depends on';
$string['remove_confirm'] = 'Are you sure you want to remove the step \'{$a}\'? This action is irreversible.';
$string['remove_step_successful'] = 'Removed step \'{$a}\' successfully.';
$string['remove_step'] = 'Remove step';
$string['steps'] = 'Steps';

// Step (type) groups.
$string['stepgrouptriggers'] = 'Triggers';
$string['stepgroupconnectors'] = 'Connectors';
$string['stepgroupconnectorlogics'] = 'Connector Logics';
$string['stepgroupwriters'] = 'Writers';
$string['stepgroupflowtransformers'] = 'Flow Transformers';
$string['stepgroupflowlogics'] = 'Flow Logics';
$string['stepgroupflows'] = 'Flows';
$string['stepgroupreaders'] = 'Readers';

// Step (form).
$string['field_description'] = 'Description';
$string['field_alias'] = 'Alias';
$string['field_alias_help'] = 'A snake cased reference to the step, unique to this dataflow. This can be used when referencing dependencies and inputs from other steps. Leaving this field blank will attempt to use a snake case version of the name value. For example, if the name is "My Step", it will be populate this field as "my_step"';
$string['field_config'] = 'Configuration';
$string['field_type'] = 'Type of step';
$string['update_step'] = 'Update step';
$string['new_step'] = 'New step';

$string['inputs'] = 'Inputs';
$string['outputs'] = 'Outputs';
$string['no_inputs'] = 'No inputs';
$string['no_outputs'] = 'No outputs';

$string['input_flow_link_limit'] = '{$a} input flow link';
$string['input_flow_link_limit_plural'] = '{$a} input flow links';
$string['input_flow_link_limit_range'] = 'between {$a->min} and {$a->max} input flow links';
$string['input_connector_link_limit'] = '{$a} input connector link';
$string['input_connector_link_limit_plural'] = '{$a} input connector links';
$string['input_connector_link_limit_range'] = 'between {$a->min} and {$a->max} connector flow links';
$string['no_input_allowed'] = 'No inputs allowed';

$string['output_flow_link_limit'] = '{$a} output flow link';
$string['output_flow_link_limit_plural'] = '{$a} output flow links';
$string['output_flow_link_limit_range'] = 'between {$a->min} and {$a->max} output flow links';
$string['output_connector_link_limit'] = '{$a} output connector link';
$string['output_connector_link_limit_plural'] = '{$a} output connector links';
$string['output_connector_link_limit_range'] = 'between {$a->min} and {$a->max} connector flow links';
$string['no_output_allowed'] = 'No outputs allowed';

$string['requires_with_or'] = 'Requires {$a->str1} or {$a->str2}.';
$string['requires'] = 'Requires {$a}.';

// Step Chooser.
$string['stepchooser'] = 'Step chooser';

// Warnings / Errors.
$string['invalidyaml'] = 'The content provided is invalid YAML.';
$string['hassideeffect'] = 'Has a side effect';
$string['nodataflowfile'] = 'No dataflow file found.';
$string['dataflowisnotavaliddag'] = 'Dataflow is not a valid Directed Acyclic Graph (DAG).';
$string['dataflowrequiredforstepcreation'] = 'Dataflow selected does not exist. Please create one before adding a step.';
$string['steptypedoesnotexist'] = 'The step type "{$a}" does not exist.';
$string['stepdependencydoesnotexist'] = 'The step dependency "{$a}" does not exist.';
$string['stepinvalidinputflowcount'] = 'Invalid number of input flows (Found: {$a->found}, Expected between {$a->min} and {$a->max}).';
$string['stepinvalidoutputflowcount'] = 'Invalid number of output flows (Found: {$a->found}, Expected between {$a->min} and {$a->max}).';
$string['bad_parameter'] = 'Parameter \'{$a->parameter}\' not supported in \'{$a->classname}\'.';
$string['config_field_missing'] = 'Config \'{$a}\' missing.';
$string['invalid_config'] = 'Invalid configuration';
$string['non_reader_steps_must_have_flow_upstreams'] = 'Non reader steps must have at least one flow step dependency.';
$string['format_not_supported'] = 'The format \'{$a}\' is not supported.';

// Capabilities.
$string['dataflows:managedataflows'] = 'Create and configure dataflows';
$string['dataflows:exportdataflowhistory'] = 'Download / export history of dataflows';
$string['dataflows:exportrundetails'] = 'Download / export dataflow run details';

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

// Engine status.
$string['engine_status_new'] = 'New';
$string['engine_status_initialised'] = 'Initialised';
$string['engine_status_blocked'] = 'Blocked';
$string['engine_status_waiting'] = 'Waiting';
$string['engine_status_processing'] = 'Processing';
$string['engine_status_flowing'] = 'Flowing';
$string['engine_status_finished'] = 'Finished';
$string['engine_status_cancelled'] = 'Cancelled';
$string['engine_status_aborted'] = 'Aborted';
$string['engine_status_finialised'] = 'Finalised';
