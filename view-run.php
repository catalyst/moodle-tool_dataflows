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
 * Trigger dataflow settings.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_dataflows\steps_table;
use tool_dataflows\visualiser;
use tool_dataflows\dataflow;
use tool_dataflows\run;

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

defined('MOODLE_INTERNAL') || die();

require_login();

// The dataflow run id.
$id = required_param('id', PARAM_INT);

$run = new run($id);

$url = new moodle_url('/admin/tool/dataflows/view-run.php', ['id' => $id]);
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url($url);

// Check for caps.
require_capability('tool/dataflows:managedataflows', $context);

// Configure any table specifics.
$table = new steps_table('dataflows_table');
$sqlfields = 'step.id,
              usr.*,
              step.*';
$sqlfrom = '{tool_dataflows_steps} step
  LEFT JOIN {user} usr
         ON usr.id = step.userid';
$sqlwhere = 'dataflowid = :dataflowid';
$sqlparams = ['dataflowid' => $id];
$table->set_sql($sqlfields, $sqlfrom, $sqlwhere, $sqlparams);
$table->make_columns();

// Configure the breadcrumb navigation.
$dataflow = new dataflow($run->dataflowid);
visualiser::breadcrumb_navigation([
    // Dataflows > Manage Flows > :dataflow->name (details page) > Runs.
    [get_string('pluginmanage', 'tool_dataflows'), new moodle_url('/admin/tool/dataflows/index.php')],
    [$dataflow->name, new moodle_url('/admin/tool/dataflows/view.php', ['id' => $run->dataflowid])],
    ["#{$run->name}", $url],
]);

$context = \context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url($url);

$output = $PAGE->get_renderer('tool_dataflows');
$pluginname = get_string('pluginname', 'tool_dataflows');

$table->define_baseurl($url);

$pageheading = "{$dataflow->name} #{$run->name}";
$PAGE->set_title($pluginname . ': ' . $dataflow->name . ': ' . $pageheading);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($pluginname . ': ' . $dataflow->name);

echo $output->header();
echo $output->heading($pageheading);

visualiser::display_dataflows_runs_chooser($dataflow, $id);

// Run Summary information (at the top).
$defaulttimezone = date_default_timezone_get();
$table = new html_table();
$table->attributes['class'] = 'tool_dataflows table-run';
$duration = number_format($run->timefinished - $run->timestarted, 4);
$secsstr = get_string('secs');
$data = [
    'field_timestarted' => date_format_string(
        $run->timestarted,
        get_string('strftimedatetimeaccurate', 'tool_dataflows'),
        $defaulttimezone
    ),
    'field_timefinished' => date_format_string(
        $run->timefinished,
        get_string('strftimedatetimeaccurate', 'tool_dataflows'),
        $defaulttimezone
    ),
    'field_duration' => $duration ? format_time((float) $duration) : ("0 {$secsstr}"),
];
$tabledata = [];
foreach ($data as $key => $value) {
    $row = new html_table_row([
        get_string($key, 'tool_dataflows'),
        $value,
    ]);
    $row->attributes['class'] .= $key;
    $tabledata[] = $row;
}
$table->data = $tabledata;
echo html_writer::table($table);

// Run state details.
$data = $run->to_record();
if (!empty($data->endstate)) {
    $data->hasendstate = true;
}
echo $output->render_from_template('tool_dataflows/run', $data);

echo $output->footer();
