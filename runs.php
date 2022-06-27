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
 * Runs (list) for a dataflow page
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_dataflows\visualiser;
use tool_dataflows\dataflow;
use tool_dataflows\runs_table;

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

defined('MOODLE_INTERNAL') || die();

require_login();

$id = required_param('id', PARAM_INT);

$url = new moodle_url('/admin/tool/dataflows/runs.php', ['id' => $id]);
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url($url);

// Check for caps.
require_capability('tool/dataflows:managedataflows', $context);

// Configure any table specifics.
$table = new runs_table('dataflow_runs_table');
$sqlfields = 'run.id,
              usr.*,
              run.*';
$sqlfrom = '{tool_dataflows_runs} run
  LEFT JOIN {user} usr
         ON usr.id = run.userid';
$sqlwhere = 'dataflowid = :dataflowid';
$sqlparams = ['dataflowid' => $id];
$table->set_sql($sqlfields, $sqlfrom, $sqlwhere, $sqlparams);
$table->make_columns();

// Configure the breadcrumb navigation.
$dataflow = new dataflow($id);
visualiser::breadcrumb_navigation([
    // Dataflows > Manage Flows > :dataflow->name (details page).
    [get_string('pluginmanage', 'tool_dataflows'), new moodle_url('/admin/tool/dataflows/index.php')],
    [$dataflow->name, new moodle_url('/admin/tool/dataflows/view.php', ['id' => $id])],
    [get_string('all_runs', 'tool_dataflows'), $url],
]);

// Page basic setup.
$output = $PAGE->get_renderer('tool_dataflows');
$pluginname = get_string('pluginname', 'tool_dataflows');

$table->define_baseurl($url);

$pageheading = get_string('all_runs', 'tool_dataflows');
$dataflow = new dataflow($dataflow->id);
$PAGE->set_title($dataflow->name . ': ' . $pageheading);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($dataflow->name . ': ' . $pageheading);
echo $output->header();


// No hide/show links under each column.
$table->collapsible(false);
// Columns are presorted.
$table->sortable(false);
// Table does not show download options by default, an import/export option will be available instead.
$table->is_downloadable(false);

// Query the data and prepare the output.
$table->setup();
$table->query_db(0); // No limit, fetch all rows.
$table->pageable(false);
$table->close_recordset();

// Render the table.
$table->build_table();
$table->finish_output();

echo $output->footer();
