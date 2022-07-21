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
$string['permitted_dirs'] = 'Permitted directories';
$string['permitted_dirs_desc'] = 'List directories here to allow them to be read from/written to by dataflow steps.';

// Manage flows / Overview.
$string['overview'] = 'Overview';
$string['field_preview'] = 'Preview';
$string['field_userid'] = 'User';
$string['field_actions'] = 'Actions';
$string['field_stepcount'] = '# Steps';
$string['field_details'] = 'Details';
$string['import_dataflow'] = 'Import Dataflow';
$string['run_confirm'] = 'Are you sure you want to run the dataflow \'{$a}\' now? The will run on the web server and may take some time to complete.';
$string['run_again'] = 'Run again';
$string['run_now'] = 'Run now';
$string['dry_run'] = 'Dry run';
$string['export'] = 'Export';
$string['import'] = 'Import';
$string['dataflow_enabled'] = 'Dataflow enabled';
$string['all_runs'] = 'All runs';
$string['recent_runs'] = 'Recent runs';
$string['no_recent_runs'] = 'No recent runs';

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

// Dataflows runs (table).
$string['run'] = 'Run';
$string['runs'] = 'Runs';
$string['field_status'] = 'Status';
$string['field_timestarted'] = 'Started';
$string['field_timefinished'] = 'Finished';
$string['field_duration'] = 'Duration';
$string['startstate'] = 'Start state';
$string['currentstate'] = 'Current state';
$string['endstate'] = 'End state';

// Step names.
$string['step_name_connector_curl'] = 'Curl connector';
$string['step_name_connector_debugging'] = 'Debugging connector';
$string['step_name_connector_s3'] = 'S3 file copy connector';
$string['step_name_flow_logic_join'] = 'Flow join';
$string['step_name_flow_transformer_filter'] = 'Filter transformer';
$string['step_name_reader_json'] = 'JSON reader';
$string['step_name_reader_sql'] = 'SQL reader';
$string['step_name_trigger_cron'] = 'Scheduled task trigger';
$string['step_name_writer_debugging'] = 'Debugging writer';
$string['step_name_writer_stream'] = 'Stream writer';
$string['step_name_connector_debug_file_display'] = 'File contents display';

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
$string['available_fields'] = 'Available Fields';
$string['available_fields_help'] = 'The fields listed below can be referenced in any step configuration, e.g. {$a}';
$string['field_description'] = 'Description';
$string['field_alias'] = 'Alias';
$string['field_alias_help'] = 'A snake cased reference to the step, unique to this dataflow. This can be used when referencing dependencies and inputs from other steps. Leaving this field blank will attempt to use a snake case version of the name value. For example, if the name is "My Step", it will be populate this field as "my_step"';
$string['field_config'] = 'Configuration';
$string['field_outputs'] = 'Output Mapping';
$string['field_outputs_help'] = 'An optional list of custom named fields you want mapped from the step\'s output in YAML format. For example if you set, {$a->config}, it can later be referenced as {$a->reference} from a future step. This can be useful as it allows you to create a shorter alias to a deeply nested value within the same context as the data.';
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
$string['nametaken'] = 'The name \'{$a}\' is already in use for this dataflow.';
$string['aliastaken'] = 'The alias \'{$a}\' is already in use for this dataflow.';
$string['recursiveexpressiondetected'] = 'The field \'{$a->field}\' contains or is part of, a self referencing expression \'{$a->steptype}\'.';
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
$string['toomanytriggers'] = 'A dataflow can have at most one trigger step.';
$string['inputs_cannot_mix_flow_and_connectors'] = 'Dependencies cannot be a mixture flow and connector steps.';
$string['outputs_cannot_mix_flow_and_connectors'] = 'Dependents cannot be a mixture flow and connector steps.';
$string['must_have_inputs'] = 'Cannot have zero input links.';
$string['must_have_outputs'] = 'Cannot have zero output links.';
$string['no_input_flows'] = 'no input flows';
$string['no_input_connectors'] = 'no input connectors';
$string['no_output_flows'] = 'no output flows';
$string['no_output_connectors'] = 'no output connectors';
$string['bad_parameter'] = 'Parameter \'{$a->parameter}\' not supported in \'{$a->classname}\'.';
$string['config_field_missing'] = 'Config \'{$a}\' is missing.';
$string['config_field_invalid'] = 'Config \'{$a}\' is invalid.';
$string['invalid_config'] = 'Invalid configuration';
$string['non_reader_steps_must_have_flow_upstreams'] = 'Non reader steps must have at least one flow step dependency.';
$string['format_not_supported'] = 'The format \'{$a}\' is not supported.';
$string['local_aws_missing'] = 'Failed to load AWS dependencies. Please ensure local_aws is installed.';
$string['s3_copy_failed'] = 'S3 copy failed. Please ensure you have getObject and putObject permissions on the target bucket.';
$string['s3_configuration_error'] = 'S3 client creation failed with provided configuration. Check values are valid.';
$string['missing_source_file'] = 'Unable to open local file for copying.';
$string['running_disabled_dataflow'] = 'Trying to run a disabled dataflow.';
$string['running_invalid_dataflow'] = 'Trying to run an invalid dataflow.';
$string['change_state_after_concluded'] = 'Attempting to change the status of a dataflow engine after it has concluded.';
$string['bad_status'] = 'Bad status, had "{$a->status}", expected "{$a->expected}"';
$string['must_have_a_step_def_defined'] = 'If an engine is passed as a parameter, a step definition must alse be passed.';
$string['path_invalid'] = 'Path "{$a}" is not permitted.';

// JSON errors.
$string['reader_json:failed_to_decode_json'] = 'Invalid JSON, failed to decode JSON file "{$a}".';
$string['reader_json:failed_to_fetch_array'] = 'Failed to extract nested JSON array "{$a}".';
$string['reader_json:failed_to_open_file'] = 'Failed to open JSON file "{$a}".';
$string['reader_json:no_sort_key'] = 'Unable to sort. Sort by value "{$a}" not a key in array.';

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
$string['privacy:metadata:runs'] = 'Data from the dataflow run';
$string['privacy:metadata:runs:userid'] = 'The id of the user who triggered the run';
$string['privacy:metadata:runs:timestarted'] = 'The timestamp of when the run was started';

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
$string['engine_status:finalised'] = 'Finalised';

// Writer stream.
$string['writer_stream:streamname'] = 'Stream Name';
$string['writer_stream:streamname_help'] = 'For example, setting <code>file:///path/to/file.txt</code> as the stream name will write the contents to this target.';
$string['writer_stream:format'] = 'Format';
$string['writer_stream:format_help'] = 'The output format of the contents written.';
$string['writer_stream:prettyprint'] = 'Pretty print';
$string['writer_stream:prettyprint_help'] = 'Format the output to be human readable.';

// Reader SQL.
$string['reader_sql:sql'] = 'SQL';
$string['reader_sql:sql_help'] = 'You may use expressions with the SQL. An example of this is setting a counter which tracks the id (via the counter field) of a given record. This in turn allows you to optionally add a constraint for when the expression evaluates to a value that can be used. For example, given counterfield equals "id", the query might be expressed as follows: {$a}This will fetch the first 10 records, and will set the countervalue to the 10th id of the record returned. The next time this step runs, it will include the extra query fragment (denoted by the surrounding square brackets <code>[[</code> and <code>]]</code>) and will query the next 10 records starting after the last one that was processed in the first run, and so on.';
$string['reader_sql:counterfield'] = 'Counter field';
$string['reader_sql:counterfield_help'] = 'Field in which the counter value is derived from. For example, the user <code>id</code> field in user related query';
$string['reader_sql:countervalue'] = 'Counter Value';
$string['reader_sql:countervalue_help'] = 'Current value from the counter field';
$string['reader_sql:variable_not_valid_in_position_replacement_text'] = "Invalid expression \${{ {\$a->expression} }} as `{\$a->expressionpath}` could not be resolved at line {\$a->line} character {\$a->column} in:\n{\$a->sql}"; // phpcs:disable moodle.Strings.ForbiddenStrings.Found

// Reader JSON.
$string['reader_json:arrayexpression_help'] = 'Nested array to extract from JSON. For example, {$a->expression} will return the users array from the following JSON (If empty it is assumed the starting point of the JSON file is an array):{$a->jsonexample}';
$string['reader_json:arrayexpression'] = 'Array Expression';
$string['reader_json:arraysortexpression_help'] = 'Value to sort array by, this value needs to be present in the return array. For the example above, setting this value to {$a} will return an array sorted by the firstname value in the users array.';
$string['reader_json:arraysortexpression'] = 'Sort by';
$string['reader_json:json_path_help'] = 'For example, setting {$a} will parse the JSON file at the location.';
$string['reader_json:pathtojson'] = 'Path to JSON Array';
$string['reader_json:sortorder'] = 'Sort Order';
$string['reader_json:sortorder_help'] = 'This will apply if the \'Sort by\' has been set. Otherwise the records will remain untouched in their original order.';

// Cron trigger.
$string['trigger_cron:cron'] = 'Scheduled task';
$string['trigger_cron:timestr'] = 'Time schedule';
$string['trigger_cron:timestr_help'] = 'A time string to determine the next time the dataflow would run, using the PHP function strtotime() format. For example "+5 minutes" would run the dataflow every 5 miunutes, or "tomorrow 2:00 am" would run the dataflow 2:00 am each day. The string must always be able to resolve to a future time.';
$string['trigger_cron:flow_disabled'] = 'Dataflow disabled';
$string['trigger_cron:invalid'] = 'Schedule configuration is invalid.';
$string['trigger_cron:crontab'] = 'Schedule in cron tab format';
$string['trigger_cron:crontab_desc'] = 'The schedule is edited as five values: minute, hour, day, month and day of month, in that order. The values are in crontab format.';
$string['trigger_cron:strftime_datetime'] = '%d %b %Y, %H:%M';
$string['trigger_cron:next_run_time'] = 'Next run time: {$a}';

// S3 File Copy.
$string['connector_s3:bucket'] = 'Bucket';
$string['connector_s3:region'] = 'Region';
$string['connector_s3:key'] = 'Key';
$string['connector_s3:secret'] = 'Secret';
$string['connector_s3:source'] = 'Source / From';
$string['connector_s3:source_help'] = 'Path to the source file. This can be a local (e.g. output.csv) or S3 path (e.g. s3://path/to/file).';
$string['connector_s3:target'] = 'Target / To';
$string['connector_s3:target_help'] = 'Path to the target file. Similar to the source, it can be a local or S3 path. If a directory is used, the source filename is applied.';
$string['connector_s3:missing_s3_source_or_target'] = 'At least one source or target path must reference a location in S3.';
$string['connector_s3:source_is_a_directory'] = 'The source path is a directory but a file path is expected.';

// Connector cURL.
$string['connector_curl:curl'] = 'URL';
$string['connector_curl:destination'] = 'File / Response destination';
$string['connector_curl:destination_help'] = 'If set then the response body will be saved to this path. If blank then the response body will available in the step variable: ...response.result';
$string['connector_curl:headers'] = 'HTTP request headers';
$string['connector_curl:rawpostdata'] = 'Raw post data';
$string['connector_curl:sideeffects'] = 'Does this request have side effects?';
$string['connector_curl:sideeffects_help'] = 'Most read requests done via http GET do not have side effects ie they change state on the remote server, and generally any POST or PUT does have side effects. Curl calls with side effects are not actually executed in dry run mode.';
$string['connector_curl:timeout'] = 'Timeout';
$string['connector_curl:timeout_help'] = 'Time in seconds after which the request will abort, default is 60 seconds.';
$string['connector_curl:field_headers_help'] = 'Headers should be in the following JSON format: {$a->json} You can also use the following YAML format: {$a->yaml}';
$string['connector_curl:output_response_result'] = 'Returns a string that contains the response to the request as text, or null if the request was unsuccessful or has not yet been sent.';

// Checks.
$string['checkdataflow_runs'] = 'Dataflows';
$string['check:dataflows_completed_successfully'] = 'All recent dataflow runs completed successfully.';
$string['check:dataflows_no_recent_runs'] = 'No recent dataflow runs.';
$string['check:dataflows_not_enabled'] = 'No dataflows enabled.';
$string['check:dataflows_run_status'] = 'Run {$a->name} - {$a->state}';
