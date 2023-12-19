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
 * Main file
 *
 * @package    tool_dataflows
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2022, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/vendor/autoload.php');

use tool_dataflows\local\formats\encoders;
use tool_dataflows\local\step;

/**
 *  Triggered as soon as practical on every moodle bootstrap after config has
 *  been loaded. The $USER object is available at this point too.
 *
 *  NOTE: DO NOT REMOVE. This currently ensures all vendor libraries are loaded.
 */
function tool_dataflows_after_config() {
}

/**
 * Returns a list of step types available for this plugin.
 *
 * NOTE: For other plugins, the function name should be simply declared as <component_name>_dataflow_step_types.
 *
 * @return     array of step types
 */
function tool_dataflows_step_types() {
    return [
        new step\connector_abort,
        new step\connector_append_file,
        new step\connector_compression,
        new step\connector_copy_file,
        new step\connector_curl,
        new step\connector_debug_file_display,
        new step\connector_debugging,
        new step\connector_directory_file_count,
        new step\connector_directory_file_list,
        new step\connector_email,
        new step\connector_file_exists,
        new step\connector_file_put_content,
        new step\connector_gpg,
        new step\connector_hash_file,
        new step\connector_log,
        new step\connector_noop,
        new step\connector_remove_file,
        new step\connector_s3,
        new step\connector_set_variable,
        new step\connector_sftp,
        new step\connector_sql,
        new step\connector_update_user,
        new step\connector_sftp_directory_file_list,
        new step\connector_sns_notify,
        new step\connector_wait,
        new step\flow_abort,
        new step\flow_append_file,
        new step\flow_compression,
        new step\flow_copy_file,
        new step\flow_curl,
        new step\flow_email,
        new step\flow_file_put_content,
        new step\flow_gpg,
        new step\flow_hash_file,
        new step\flow_log,
        new step\flow_logic_join,
        new step\flow_logic_switch,
        new step\flow_noop,
        new step\flow_remove_file,
        new step\flow_s3,
        new step\flow_set_variable,
        new step\flow_sftp,
        new step\flow_sql,
        new step\flow_transformer_alter,
        new step\flow_transformer_filter,
        new step\flow_transformer_regex,
        new step\flow_web_service,
        new step\reader_csv,
        new step\reader_directory_file_list,
        new step\reader_json,
        new step\reader_sftp_directory_file_list,
        new step\reader_sql,
        new step\trigger_cron,
        new step\trigger_event,
        new step\trigger_webservice,
        new step\writer_debugging,
        new step\writer_stream,
        new step\connector_set_multiple_variables,
        new step\flow_set_multiple_variables,
    ];
}

/**
 * Returns a list of encoders available for this plugin.
 *
 * NOTE: For other plugins, the function name should be simply declared as <component_name>_dataflows_encoders.
 *
 * @return     array of dataflow encoders
 */
function tool_dataflows_encoders() {
    return [
        new encoders\json,
        new encoders\csv,
    ];
}

/**
 * Add dataflows generic status check.
 *
 * @return array of check objects
 */
function tool_dataflows_status_checks(): array {
    return [new \tool_dataflows\check\dataflow_runs()];
}
