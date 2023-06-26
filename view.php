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
use tool_dataflows\manager;

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

defined('MOODLE_INTERNAL') || die();

require_login();

$id = required_param('id', PARAM_INT);

$url = new moodle_url('/admin/tool/dataflows/view.php', ['id' => $id]);
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
$dataflow = new dataflow($id);
visualiser::breadcrumb_navigation([
    // Dataflows > Manage Flows > :dataflow->name (details page).
    [get_string('pluginmanage', 'tool_dataflows'), new moodle_url('/admin/tool/dataflows/index.php')],
    [$dataflow->name, new moodle_url('/admin/tool/dataflows/view.php', ['id' => $id])],
]);

if (manager::is_dataflows_readonly()) {
    \core\notification::warning(get_string('readonly_active', 'tool_dataflows'));
}

visualiser::display_dataflows_view_page($id, $table, $url, get_string('steps', 'tool_dataflows'));
