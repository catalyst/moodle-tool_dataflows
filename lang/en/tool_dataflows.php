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
$string['enabled'] = 'Enable/disable this plugin';
$string['enabled_help'] = '';
$string['global_vars'] = 'Global variables';
$string['global_vars_desc'] = "Global variables that can be accessed via expressions within all dataflows as \${{global.vars.&lt;name&gt;}}'.
    Values are defined in YAML format, with nested values being converted into a dot separated sequence.\n
    Example.
    abc:
      def: 12  # Accessed as \${{global.vars.abc.def}}";
$string['gpg_exec_path'] = 'Path to GPG executable';
$string['gpg_exec_path_desc'] = 'Path to GPG executable';
$string['gpg_key_dir'] = 'Path to keyring directory';
$string['gpg_key_dir_desc'] = 'Path to keyring directory';
$string['log_handlers'] = 'Log handlers';
$string['log_handlers_desc'] = 'Additional log handlers to output dataflow logs to more destinations. The handler for mtrace is always active and cannot be disabled. Applying the settings at the dataflow level will override settings applied at the site admin level.';
$string['log_handler_file_per_dataflow'] = 'File per dataflow - [dataroot]/tool_dataflows/{id}-Y-m-d.log';
$string['log_handler_file_per_run'] = 'File per run - [dataroot]/tool_dataflows/{dataflowid}/{id}.log';
$string['log_handler_browser_console'] = 'Browser Console';
$string['permitted_dirs'] = 'Permitted directories';
$string['permitted_dirs_desc'] = "List directories here to allow them to be read from/written to by dataflow steps.
    One directory per line. Each directory must be an absolute path. You can use the place holder '{\$a}' for the
    site's data root directory. Comments using '/* .. */' and '#' can be included. Blank lines will be ignored.\n
    Examples.
    /tmp
    /home/me/mydata
    {\$a}/somedir";
$string['pluginsettings'] = 'General settings';
$string['readonly'] = 'Read Only';
$string['readonly_active'] = '"Read Only" is currently active. You cannot modify any dataflow settings until it is disabled.';
$string['readonly_help'] = 'When readonly is active, you cannot modify any dataflows setting from the UI. This can be unset via CLI.';

// Manage flows / Overview.
$string['overview'] = 'Overview';
$string['field_preview'] = 'Preview';
$string['field_userid'] = 'Created by';
$string['field_actions'] = 'Actions';
$string['field_stepcount'] = '# Steps';
$string['field_details'] = 'Details';
$string['field_lastrunstart'] = 'Last run started';
$string['field_lastrunduration'] = 'Last run duration';
$string['import_dataflow'] = 'Import Dataflow';
$string['run_confirm'] = 'Are you sure you want to run the dataflow \'{$a}\' now? The will run on the web server and may take some time to complete.';
$string['run_again'] = 'Run again';
$string['run_now'] = 'Run now';
$string['dry_run'] = 'Dry run';
$string['export'] = 'Export';
$string['exporttxt'] = 'Export as txt';
$string['exportpreview'] = 'Export preview';
$string['import'] = 'Import';
$string['dataflow_enabled'] = 'Dataflow enabled';
$string['all_runs'] = 'All runs';
$string['recent_runs'] = 'Recent runs';
$string['no_recent_runs'] = 'No recent runs';
$string['last_run_timeago'] = '{$a} ago';

// Dataflows (form).
$string['field_name'] = 'Name';
$string['update_dataflow'] = 'Update Dataflow';
$string['new_dataflow'] = 'New Dataflow';
$string['remove_dataflow'] = 'Remove dataflow';
$string['remove_dataflow_confirm'] = 'Are you sure you want to remove the dataflow \'{$a}\'? This action is irreversible.';
$string['remove_dataflow_successful'] = 'Removed dataflow \'{$a}\' successfully.';
$string['concurrency_enabled'] = 'Enable concurrent running';
$string['field_vars'] = 'Variables';
$string['field_vars_help'] = 'Dataflow variables that can be accessed via expressions within all dataflow steps as {$a->reference}.
 Values are defined in YAML format (&lt;var&gt;: &lt;value&gt;), with nested values being converted into a dot separated sequence.
  Example:{$a->example} Note: If you use \'[dataroot]\', make sure to quote the value or it will be interpreted as an array.';
$string['error:vars_not_object'] = 'Vars must form a YAML object (Define each var as &lt;var&gt;: &lt;value&gt;)';
$string['error:invalid_yaml'] = 'Invalid YAML (Try quoting your value(s)): {$a}';

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
$string['strftimedatetimeaccurate'] = '%d %B %Y, %I:%M:%S %p';

// Step names.
$string['step_name_connector_directory_file_count'] = 'Directory file count';
$string['step_name_connector_directory_file_list'] = 'Directory file list';
$string['step_name_connector_sftp_directory_file_list'] = 'SFTP directory file list';
$string['step_name_connector_abort'] = 'Abort connector';
$string['step_name_connector_append_file'] = 'Append file';
$string['step_name_connector_copy_file'] = 'Copy File';
$string['step_name_connector_curl'] = 'Curl connector';
$string['step_name_connector_debug_file_display'] = 'File contents display';
$string['step_name_connector_debugging'] = 'Debugging connector';
$string['step_name_connector_email'] = 'Email notification';
$string['step_name_connector_file_exists'] = 'File exists';
$string['step_name_connector_file_put_content'] = 'File put content';
$string['step_name_connector_gpg'] = 'GPG';
$string['step_name_connector_hash_file'] = 'Hash file';
$string['step_name_connector_noop'] = 'No-op';
$string['step_name_connector_remove_file'] = 'Remove file';
$string['step_name_connector_s3'] = 'S3 file copy';
$string['step_name_connector_set_variable'] = 'Set variable';
$string['step_name_connector_sftp'] = 'SFTP file copy';
$string['step_name_connector_sns_notify'] = 'AWS-SNS Notification';
$string['step_name_connector_wait'] = 'Wait';
$string['step_name_flow_abort'] = 'Abort';
$string['step_name_flow_append_file'] = 'Append';
$string['step_name_flow_copy_file'] = 'Copy File';
$string['step_name_flow_curl'] = 'Curl';
$string['step_name_flow_email'] = 'Flow email notification';
$string['step_name_flow_file_put_content'] = 'File put content';
$string['step_name_flow_gpg'] = 'GPG';
$string['step_name_flow_hash_file'] = 'Hash file';
$string['step_name_flow_remove_file'] = 'Remove file';
$string['step_name_flow_set_variable'] = 'Set variable';
$string['step_name_flow_logic_join'] = 'Join';
$string['step_name_flow_logic_switch'] = 'Switch';
$string['step_name_flow_noop'] = 'No-op';
$string['step_name_flow_s3'] = 'S3 file copy';
$string['step_name_flow_sftp'] = 'SFTP file copy';
$string['step_name_flow_transformer_alter'] = 'Alteration transformer';
$string['step_name_flow_transformer_filter'] = 'Filter transformer';
$string['step_name_flow_transformer_regex'] = 'Regex transformer';
$string['step_name_flow_web_service'] = 'Flow web service';
$string['step_name_reader_csv'] = 'CSV reader';
$string['step_name_reader_directory_file_list'] = 'Directory file list reader';
$string['step_name_reader_json'] = 'JSON reader';
$string['step_name_reader_sql'] = 'SQL reader';
$string['step_name_trigger_cron'] = 'Cron';
$string['step_name_trigger_webservice'] = 'Webservice';
$string['step_name_writer_debugging'] = 'Debugging writer';
$string['step_name_writer_stream'] = 'Stream writer';
$string['step_name_trigger_event'] = 'Moodle event';
$string['step_name_flow_sql'] = 'SQL';

// Step (type) groups.
$string['stepgrouptriggers'] = 'Triggers';
$string['stepgroupconnectors'] = 'Connectors';
$string['stepgroupconnectorlogics'] = 'Connector Logics';
$string['stepgroupwriters'] = 'Writers';
$string['stepgroupflowtransformers'] = 'Flow Transformers';
$string['stepgroupflowlogics'] = 'Flow logic';
$string['stepgroupflows'] = 'Flows';
$string['stepgroupreaders'] = 'Readers';

// Step (form).
$string['available_fields'] = 'Available Fields';
$string['available_fields_help'] = 'The fields listed below can be referenced in any step configuration, e.g. {$a}';
$string['field_description'] = 'Description';
$string['field_alias'] = 'Alias';
$string['field_alias_help'] = 'A reference to this step, unique to this dataflow. This can be used in expressions to access the step. It must be made up of only letters, numbers, or underscores (\'_\'). If empty, then a snake case version of the name will be used. For example, if the name is "My Step", it will be populate this field as "my_step"';
$string['field_config'] = 'Configuration';
$string['field_step_vars_help'] = "Variables that can be accessed from any step in the form {\$a->reference}. These variables are updated after a step has been executed, or after every iteration in a flow step. The setting is defined in YAML format.
    Example: {\$a->example}";
$string['field_type'] = 'Step type';
$string['update_step'] = 'Update step';
$string['new_step'] = 'New step';
$string['stepextras'] = 'Extra settings';

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
$string['out_path'] = 'Output path';
$string['out_path_help'] = 'Path to the file to be written to. e.g.';
$string['path_help'] = 'Path to the file to be read or written to. e.g.';
$string['path_help_examples'] = "
    out.csv           # A path relative to the flows temp working dir;
    /var/data.json    # An absolute path;
    file:///my/in.txt # Any valid php stream url;";

$string['requires_with_or'] = 'Requires {$a->str1} or {$a->str2}.';
$string['requires'] = 'Requires {$a->str1} and {$a->str2}.';
$string['runningfor'] = 'Running for {$a}';

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
$string['successfullycopiedtoclipboard'] = 'Successfully copied to clipboard {$a}';
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
$string['config_user_invalid'] = 'User \'{$a}\' is invalid or does not exist.';
$string['invalid_config'] = 'Invalid configuration';
$string['non_reader_steps_must_have_flow_upstreams'] = 'Non reader steps must have at least one flow step dependency.';
$string['format_not_supported'] = 'The format \'{$a}\' is not supported.';
$string['local_aws_missing'] = 'Failed to load AWS dependencies. Please ensure local_aws is installed.';
$string['s3_copy_failed'] = 'S3 copy failed. {$a}';
$string['s3_configuration_error'] = 'S3 client creation failed with provided configuration. Check values are valid.';
$string['missing_source_file'] = 'Unable to open local file for copying.';
$string['running_disabled_dataflow'] = 'Trying to run a disabled dataflow.';
$string['running_invalid_dataflow'] = 'Trying to run an invalid dataflow.';
$string['change_state_after_concluded'] = 'Attempting to change the status of a dataflow engine to "{$a->to}" after it has concluded ("{$a->from}").';
$string['change_step_state_after_concluded'] = 'Attempting to change the status of a dataflow engine step to "{$a->to}" after it has concluded ("{$a->from}").';
$string['bad_status'] = 'Bad status, had "{$a->status}", expected "{$a->expected}"';
$string['must_have_a_step_def_defined'] = 'If an engine is passed as a parameter, a step definition must alse be passed.';
$string['path_invalid'] = 'Path "{$a}" is not permitted.';
$string['path_not_absolute'] = 'Path "{$a}" is not an absolute path.';
$string['unknown_placeholder'] = 'Placeholder "{$a}" is not recognised.';
$string['invalid_value_for_field'] = 'Value "{$a->value}" for field "{$a->field}" is invalid.';
$string['invalid_value'] = 'Value is invalid.';
$string['no_dot_installed'] = 'A dependency "dot" could not be found. View dependencies and installation instructions {$a}.';
$string['preview_unavailable'] = 'Preview unavailable';
$string['here'] = 'here';
$string['concurrency_disabled'] = 'Concurrency is not possible with this dataflow. Concurrent running flag has been set to false.';
$string['concurrency_enabled_disabled_desc'] = '<i>Concurrent running is not possible because one or more steps are unable to support concurrency.
 While you can still edit this setting, it\'s value will be ignored. Reasons are:</i>';
$string['file_missing'] = 'File is missing \'{$a}\'.';
$string['property_not_supported'] = 'Property \'{$a->property}\' not supported in \'{$a->classname}\'';

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
$string['writer_stream:format'] = 'Format';
$string['writer_stream:format_help'] = 'The output format of the contents written.';
$string['writer_stream:prettyprint'] = 'Pretty print';
$string['writer_stream:prettyprint_help'] = 'Format the output to be human readable.';
$string['writer_csv:fail_to_encode'] = 'Failed to encode CSV.';

// SQL trait.
$string['sql_trait:sql_param_type_not_valid'] = 'The SQL parameter must be a valid type (string or int).';
$string['sql_trait:variable_not_valid_in_position_replacement_text'] = "Invalid expression \${{ {\$a->expression} }} as `{\$a->expressionpath}` could not be resolved at line {\$a->line} character {\$a->column} in:\n{\$a->sql}"; // phpcs:disable moodle.Strings.ForbiddenStrings.Found

// Reader SQL.
$string['reader_sql:sql'] = 'SQL';
$string['reader_sql:sql_help'] = 'You may use expressions with the SQL. An example of this is setting a counter which tracks the id (via the counter field) of a given record. This in turn allows you to optionally add a constraint for when the expression evaluates to a value that can be used. For example, given counterfield equals "id", the query might be expressed as follows: {$a}This will fetch the first 10 records, and will set the countervalue to the 10th id of the record returned. The next time this step runs, it will include the extra query fragment (denoted by the surrounding square brackets <code>[[</code> and <code>]]</code>) and will query the next 10 records starting after the last one that was processed in the first run, and so on.  Note: do not quote expressions in your SQL. Expressions are parsed as prepared statement parameters, and so are already understood by the DB engine to be literals.';
$string['reader_sql:counterfield'] = 'Counter field';
$string['reader_sql:counterfield_help'] = 'Field in which the counter value is derived from. For example, the user <code>id</code> field in user related query';
$string['reader_sql:countervalue'] = 'Counter Value';
$string['reader_sql:countervalue_help'] = 'Current value from the counter field';
$string['reader_sql:counterfield_not_empty'] = 'Counterfield value is non-empty.';
$string['reader_sql:settings_unknown'] = 'The settings are unknown.';

// Reader CSV.
$string['reader_csv:path'] = 'Path to CSV file';
$string['reader_csv:delimiter'] = 'Delimiter';
$string['reader_csv:headers'] = 'Headers';
$string['reader_csv:headers_help'] = 'If populated, then this will act as the header to map field to keys. If left blank, it will be populated automatically using the first read row.';
$string['reader_csv:overwriteheaders'] = 'Overwrite existing headers';
$string['reader_csv:overwriteheaders_help'] = 'If checked, the headers supplied above will be used instead of the ones in the file, effectively ignoring the first row.';

// Reader JSON.
$string['reader_json:arrayexpression_help'] = 'Nested array to extract from JSON. For example, {$a->expression} will return the users array from the following JSON (If empty it is assumed the starting point of the JSON file is an array):{$a->jsonexample}';
$string['reader_json:arrayexpression'] = 'Array Expression';
$string['reader_json:arraysortexpression_help'] = 'Value to sort array by, this value needs to be present in the return array. For the example above, setting this value to {$a} will return an array sorted by the firstname value in the users array.';
$string['reader_json:arraysortexpression'] = 'Sort by';
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

// Email notification.
$string['connector_email:message'] = 'Message';
$string['connector_email:sending_message'] = 'Sending email to <{$a}>';
$string['connector_email:subject'] = 'Subject';
$string['connector_email:to'] = 'Recipients email address';
$string['connector_email:name'] = 'Recipients name';

// Hash file.
$string['flow_hash_file:path'] = 'Path to file';
$string['flow_hash_file:algorithm'] = 'Algorithm';
$string['flow_hash_file:algorithm_help'] = 'Name of selected hashing algorithm. Available algorithms include: {$a}';
$string['flow_hash_file:algorithm_does_not_exist'] = 'Name of selected hashing algorithm. Available algorithms include: {$a}';

// S3 File Copy.
$string['connector_s3:bucket'] = 'Bucket';
$string['connector_s3:region'] = 'Region';
$string['connector_s3:key'] = 'Key';
$string['connector_s3:secret'] = 'Secret';
$string['connector_s3:source'] = 'Source / From';
$string['connector_s3:source_help'] = 'Path to the source file. This can be a local file or S3 path e.g.';
$string['connector_s3:target_help'] = 'Path to the target file. This can be a local file or S3 path e.g.';
$string['connector_s3:path_example'] = '    s3://path/to/file # Any s3 stream url;';
$string['connector_s3:target'] = 'Target / To';
$string['connector_s3:missing_s3_source_or_target'] = 'At least one source or target path must reference a location in S3.';
$string['connector_s3:source_is_a_directory'] = 'The source path is a directory but a file path is expected.';

// AWS SNS Notification.
$string['connector_sns_notify:topic'] = 'Topic';
$string['connector_sns_notify:message'] = 'Message';
$string['connector_sns_notify:sending_message'] = 'Sending SNS notification to topic "{$a}". Message follows.';

// Connector cURL.
$string['connector_curl:curl'] = 'URL';
$string['connector_curl:destination'] = 'File / Response destination';
$string['connector_curl:destination_help'] = 'If set, then the response body will be saved to this path. If blank, then the response body will be available in the step variable: ...response.result';
$string['connector_curl:headers'] = 'HTTP request headers';
$string['connector_curl:headersnotvalid'] = 'Supplied HTTP headers are not valid';
$string['connector_curl:rawpostdata'] = 'Raw post data';
$string['connector_curl:sideeffects'] = 'Does this request have side effects?';
$string['connector_curl:sideeffects_help'] = 'Most read requests done via http GET do not have side effects ie they change state on the remote server, and generally any POST or PUT does have side effects. Curl calls with side effects are not actually executed in dry run mode.';
$string['connector_curl:timeout'] = 'Timeout';
$string['connector_curl:timeout_help'] = 'Time in seconds after which the request will abort, default is 60 seconds.';
$string['connector_curl:field_headers_help'] = 'Headers should be in valid HTTP format: {$a} One per line.';
$string['connector_curl:output_response_result'] = 'Returns a string that contains the response to the request as text, or null if the request was unsuccessful or has not yet been sent.';
$string['connector_curl:header_format'] = '<header>:<value>';
$string['connector_curl:headers_invalid'] = 'Curl connector headers are invalid.';

// GPG.
$string['gpg:userid'] = 'Key owner (user ID)';
$string['gpg:encrypt'] = 'Encrypt';
$string['gpg:decrypt'] = 'Decrypt';
$string['gpg:output_success'] = 'Whether the operation was successful.';
$string['gpg:passphrase'] = 'Passphrase';

// SFTP.
$string['sftp:header'] = 'SFTP resource';

// Connector sftp.
$string['connector_sftp:host'] = 'Host';
$string['connector_sftp:source'] = 'Source';
$string['connector_sftp:source_desc'] = 'Path to the source file. This can be a local file or remote path e.g.';
$string['connector_sftp:target'] = 'Target';
$string['connector_sftp:target_desc'] = 'Path to the target file. This can be a local file or remote path e.g.';
$string['connector_sftp:path_example'] = '    sftp://file/path  # Any remote file url;';
$string['connector_sftp:bad_host'] = 'Unable to connect to host.';
$string['connector_sftp:bad_hostpubkey'] = 'Host public key mismatch.';
$string['connector_sftp:bad_auth'] = 'Authorisation failed.';
$string['connector_sftp:bad_sftp'] = 'Unable to start SFTP session.';
$string['connector_sftp:no_ssh2'] = 'SSH2 extension not installed';
$string['connector_sftp:host_desc'] = 'The host to connect to';
$string['connector_sftp:port'] = 'Port';
$string['connector_sftp:hostpubkey'] = 'Host public key';
$string['connector_sftp:hostpubkey_desc'] = 'Public key that must match the one returned by the host. If empty, it will be set on the first connection.';
$string['connector_sftp:copy_fail'] = 'Copy failed: \'{$a}\'.';
$string['connector_sftp:missing_remote'] = 'At least one of source/target must be remote.';
$string['connector_sftp:pubkeyfile'] = 'Public key file';
$string['connector_sftp:privkeyfile'] = 'Private key file';
$string['connector_sftp:keyfile_desc'] = 'Fill this field if you are using public/private key authentication. If empty, then authentication will fall back to username/password.';
$string['connector_sftp:password_desc'] = 'Password for username/password authentication. If a key file is specified, then this is used to decrypt the key. It is mandatory for username/password authentication, optional for key authentication.';
$string['connector_sftp:file_not_found'] = 'File not found: \'{$a}\'.';

// Checks.
$string['check:dataflows_completed_successfully'] = 'All recent dataflow runs completed successfully.';
$string['check:dataflows_no_runs'] = 'No dataflow runs executed.';
$string['check:dataflows_not_enabled'] = 'No dataflows enabled.';
$string['check:dataflows_run_status'] = 'Run {$a->name} - {$a->state}';

// Email notification.
$string['flow_email:message'] = 'Message';
$string['flow_email:sending_message'] = 'Sending email to <{$a}>';
$string['flow_email:subject'] = 'Subject';
$string['flow_email:to'] = 'Recipients email address';
$string['flow_email:name'] = 'Recipients name';

// Flow logic: Case.
$string['flow_logic_switch:cases'] = 'Cases';
$string['flow_logic_switch:cases_help'] = 'Each line represents a case, and each case has a label for display, and an expression separated by a colon "<code>:</code>", used to determine whether the linked step will consume the data that flows or not. If no label is present, it will instead use the line number for that connection. You can use <code>default: 1</code> as the last entry to ensure it always matches on at least one step - should all other expressions fail. Otherwise, if all expressions fail to match, the record will not flow through and would be skipped.';
$string['flow_logic_switch:casenotfound'] = 'The output position of #{$a} did not match any existing case';
$string['flow_logic_switch:nomatchingcases'] = 'No matching cases, skipping record';

// Flow transformer alteration.
$string['flow_transformer_alter:expressions'] = 'Transform expressions';
$string['flow_transformer_alter:expressions_help'] = 'Each line represents a transformation. The expression will be evaluated and the result assigned to the field identified by the label. The expressions will always use the original input values.';

// Flow transformer filter.
$string['flow_transformer_filter:filter'] = 'Filter expression';
$string['flow_transformer_filter:filter_help'] = "This expression must evaluate to a boolean. When the expression evaluates to false, the current value for the flow will not be passed on to later steps in the flow. There is no need for '\${{' and '}}'.";

// Flow tranformer regex.
$string['flow_transformer_regex:pattern'] = 'Regex pattern';
$string['flow_transformer_regex:pattern_help'] = "The regex pattern will be applied to a field, and the first match will be returned.";
$string['flow_transformer_regex:field'] = 'Input field';
$string['flow_transformer_regex:field_help'] = "This field must be a string that will be processed using the regex pattern";

// Wait connector.
$string['connector_wait:timesec'] = 'Time in seconds';
$string['connector_wait:not_integer'] = 'Wait time value must evaluate to a positive integer (had "{$a}").';

// Abort If.
$string['connector_abort:condition'] = 'Condition';

// File exists.
$string['connector_file_exists:path'] = 'File path';

// Flow Web service.
$string['flow_web_service:webservice'] = 'Web service';
$string['flow_web_service:webservice_help'] = 'Web service name to call ie: <code>core_user_create_users</code>';
$string['flow_web_service:user'] = 'User calling Web service';
$string['flow_web_service:user_help'] = 'A username as the one used for login';
$string['flow_web_service:selectuser'] = 'Select user';
$string['flow_web_service:parameters'] = 'Parameters';
$string['flow_web_service:parameters_help'] = 'Parameters passed to the webservice in JSON format';
$string['flow_web_service:failure'] = 'Failure processing';
$string['flow_web_service:failure_help'] = 'In case web service call fails, either record, abort step only or abort entire flow';
$string['flow_web_service:skiprecord'] = 'Skip this record';
$string['flow_web_service:abortflow'] = 'Abort flow';
$string['flow_web_service:field_parameters_help'] = 'The parameters required depend on the webservice and are in YAML format eg: {$a->yaml}';
$string['flow_web_service:path'] = 'Failure path recording';

// Cache definition.
$string['cachedef_dot'] = 'This cache stores internal fragments of dot binaries';

// Dataflow iterator.
$string['dataflow_iterator:null_input'] = 'Trying to construct an iterator without an upstream iterator for step {$a}';

// Append file.
$string['trait_append_file:cannot_read_file'] = 'Cannot read file {$a}';

$string['flow_append_file:chopfirstline'] = 'Remove first line before appending to a non-empty file?';

// Copy File.
$string['flow_copy_file:from'] = 'From';
$string['flow_copy_file:to'] = 'To';
$string['flow_copy_file:copy_failed'] = 'Failed to copy {$a->from} to {$a->to}';

// Directory file count.
$string['connector_directory_file_count:path'] = 'Path to directory';

// Directory file list.
$string['directory_file_list:header'] = 'Directory File List settings';
$string['directory_file_list:absolutepath'] = 'absolutepath';
$string['directory_file_list:alpha'] = 'Alphabetical (A->Z)';
$string['directory_file_list:alpha_reverse'] = 'Reverse alphabetical (Z->A)';
$string['directory_file_list:basename'] = 'Base Name';
$string['directory_file_list:filenames'] = 'List of filenames.';
$string['directory_file_list:limit'] = 'Limit';
$string['directory_file_list:offset'] = 'Offset';
$string['directory_file_list:pattern'] = 'File pattern';
$string['directory_file_list:relativepath'] = 'Relative Path';
$string['directory_file_list:returnvalue'] = 'Return Value';
$string['directory_file_list:size'] = 'Largest to smallest';
$string['directory_file_list:size_reverse'] = 'Smallest to larget';
$string['directory_file_list:sort'] = 'Sort';
$string['directory_file_list:subdirectories'] = 'Include sub-directories?';
$string['directory_file_list:time'] = 'Latest to earliest';
$string['directory_file_list:time_reverse'] = 'Earliest to latest';

// Variables.
$string['variables:assign_object_to_value'] = 'Attempting to assign an object to a value node for \'{$a}\'.';
$string['variables:assign_value_to_object'] = 'Attempting to assign a value to an object node for \'{$a}\'.';
$string['variables:reference_lead_as_branch'] = 'Attempting to reference a leaf as a branch for \'{$a}\'.';
$string['variables:cannot_resolve_ref'] = 'Cannot resolve reference \'{$a}\'.';
$string['variables:root_not_in_ancestry'] = 'Root \'{$a->root}\' is not in the ancestry of \'{$a->node}\'.';
$string['variables:no_step_definition'] = 'Cannot get variables without a step definition.';

// File put content.
$string['file_put_content:content'] = 'Content';
$string['file_put_content:content_help'] = 'Content to be saved to file. Can include expressions.';

// Set variable step.
$string['set_variable:field'] = 'Field';
$string['set_variable:field_help'] = 'Defines the path to the field you would like to set the value. For example: <code>dataflow.vars.counter</code>.';
$string['set_variable:value'] = 'Value';
$string['set_variable:value_help'] = 'The value could be a number, text, or an expression. For example: <code>${{ record.id }}</code>.';

// Event trigger.
$string['trigger_event:policy:immediate'] = 'Run immediately';
$string['trigger_event:policy:adhoc'] = 'Run ASAP in individual tasks in parallel';
$string['trigger_event:policy:adhocqueued'] = 'Run ASAP in batch tasks in series';
$string['trigger_event:form:eventname'] = 'Trigger event';
$string['trigger_event:form:executionpolicy'] = 'Execution policy';
$string['trigger_event:variable:event'] = 'Data included in the Moodle event';
$string['trigger_event:form:eventname_help'] = 'Select the event to listen to. Note if the event is changed after a step is created, previously captured events queued for processing will be purged.';
$string['trigger_event:form:executionpolicy_help'] = 'If the dataflow does not support concurrent running, serial processing will be used instead of parallel processing';

// SQL flow step.
$string['flow_sql:sql'] = 'SQL';
$string['flow_sql:sql_help'] = 'You may use expressions with the SQL such as variables from other steps.  Note: do not quote expressions in your SQL. Expressions are parsed as prepared statement parameters, and so are already understood by the DB engine to be literals.';

// Remove file step.
$string['remove_file:file'] = 'File path to be removed';
