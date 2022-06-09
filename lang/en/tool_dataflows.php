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
$string['dataflow_enabled'] = 'Dataflow enabled';

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

// Step names.
$string['step_name_connector_curl'] = 'Curl connector';
$string['step_name_connector_debugging'] = 'Debugging connector';
$string['step_name_connector_s3'] = 'S3 file copy connector';
$string['step_name_flow_logic_join'] = 'Flow join';
$string['step_name_flow_transformer_filter'] = 'Filter transformer';
$string['step_name_reader_sql'] = 'SQL reader';
$string['step_name_trigger_cron'] = 'Scheduled task trigger';
$string['step_name_writer_debugging'] = 'Debugging writer';
$string['step_name_writer_stream'] = 'Stream writer';

// Step (type) groups.
$string['stepgrouptriggers'] = 'Triggers';
$string['stepgroupconnectors'] = 'Connectors';
$string['stepgroupconnectorlogics'] = 'Connector Logics';
$string['stepgroupwriters'] = 'Writers';
$string['stepgroupflowtransformers'] = 'Flow Transformers';
$string['stepgroupflowlogics'] = 'Flow steps';
$string['stepgroupflows'] = 'Flows';
$string['stepgroupreaders'] = 'Reader steps';

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
$string['input_connector_link_limit_range'] = 'between {$a->min} and {$a->max} input connector links';
$string['no_input_allowed'] = 'No inputs allowed';

$string['output_flow_link_limit'] = '{$a} output flow link';
$string['output_flow_link_limit_plural'] = '{$a} output flow links';
$string['output_flow_link_limit_range'] = 'between {$a->min} and {$a->max} output flow links';
$string['output_connector_link_limit'] = '{$a} output connector link';
$string['output_connector_link_limit_plural'] = '{$a} output connector links';
$string['output_connector_link_limit_range'] = 'between {$a->min} and {$a->max} output connector links';
$string['no_output_allowed'] = 'No outputs allowed';

$string['requires_with_or'] = 'Requires {$a->str1} or {$a->str2}.';
$string['requires'] = 'Requires {$a->str1} and {$a->str2}.';

// Step Chooser.
$string['stepchooser'] = 'Step chooser';

// Warnings / Errors.
$string['variablefieldnotexpected'] = 'The field \'{$a->field}\' cannot be set as it is not an expected field for step type \'{$a->steptype}\'.';
$string['invalidyaml'] = 'The content provided is invalid YAML.';
$string['hassideeffect'] = 'Has a side effect';
$string['nodataflowfile'] = 'No dataflow file found.';
$string['dataflowisnotavaliddag'] = 'Dataflow is not a valid Directed Acyclic Graph (DAG).';
$string['dataflowrequiredforstepcreation'] = 'Dataflow selected does not exist. Please create one before adding a step.';
$string['steptypedoesnotexist'] = 'The step type "{$a}" does not exist.';
$string['stepdependencydoesnotexist'] = 'The step dependency "{$a}" does not exist.';
$string['stepinvalidinputflowcount'] = 'Invalid number of input flows (Found: {$a}).';
$string['stepinvalidinputconnectorcount'] = 'Invalid number of input connectors (Found: {$a}).';
$string['stepinvalidoutputflowcount'] = 'Invalid number of output flows (Found: {$a}).';
$string['stepinvalidoutputconnectorcount'] = 'Invalid number of output connectors (Found: {$a}).';
$string['inputs_cannot_mix_flow_and_connectors'] = 'Dependencies cannot be a mixture flow and connector steps.';
$string['outputs_cannot_mix_flow_and_connectors'] = 'Dependents cannot be a mixture flow and connector steps.';
$string['must_have_inputs'] = 'Cannot have zero input links.';
$string['must_have_outputs'] = 'Cannot have zero output links.';
$string['no_input_flows'] = 'no input flows';
$string['no_input_connectors'] = 'no input connectors';
$string['no_output_flows'] = 'no output flows';
$string['no_output_connectors'] = 'no output connectors';
$string['bad_parameter'] = 'Parameter \'{$a->parameter}\' not supported in \'{$a->classname}\'.';
$string['config_field_missing'] = 'Config \'{$a}\' missing.';
$string['invalid_config'] = 'Invalid configuration';
$string['non_reader_steps_must_have_flow_upstreams'] = 'Non reader steps must have at least one flow step dependency.';
$string['format_not_supported'] = 'The format \'{$a}\' is not supported.';
$string['local_aws_missing'] = 'Failed to load AWS dependencies. Please ensure local_aws is installed.';
$string['s3_copy_failed'] = 'S3 copy failed. Please ensure you have getObject and putObject permissions on the target bucket.';
$string['s3_configuration_error'] = 'S3 client creation failed with provided configuration. Check values are valid.';
$string['missing_source_file'] = 'Unable to open local file for copying.';

// Stream errors.
$string['writer_stream:failed_to_open_stream'] = 'Failed to open stream "{$a}".';
$string['writer_stream:failed_to_write_stream'] = 'Failed to write to stream "{$a}".';

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
$string['engine_status:new'] = 'New';
$string['engine_status:initialised'] = 'Initialised';
$string['engine_status:blocked'] = 'Blocked';
$string['engine_status:waiting'] = 'Waiting';
$string['engine_status:processing'] = 'Processing';
$string['engine_status:flowing'] = 'Flowing';
$string['engine_status:finished'] = 'Finished';
$string['engine_status:cancelled'] = 'Cancelled';
$string['engine_status:aborted'] = 'Aborted';
$string['engine_status:finialised'] = 'Finalised';

// Writer stream.
$string['writer_stream:streamname'] = 'Stream Name';
$string['writer_stream:streamname_help'] = 'For example, setting <code>file:///path/to/file.txt</code> as the stream name will write the contents to this target.';
$string['writer_stream:format'] = 'Format';
$string['writer_stream:format_help'] = 'The output format of the contents written.';

// Reader SQL.
$string['reader_sql:sql'] = 'SQL';
$string['reader_sql:counterfield'] = 'Counter field';
$string['reader_sql:counterfield_help'] = 'Field in which the counter value is derived from. For example, the userid field in user related query';
$string['reader_sql:countervalue'] = 'Counter Value';
$string['reader_sql:countervalue_help'] = 'Current value from the counter field';
