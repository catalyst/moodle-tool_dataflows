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
 * Manage a dataflow from form actions.
 *
 * @package    tool_dataflows
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_dataflows\dataflow;
use tool_dataflows\manager;

require_once(dirname(__FILE__) . '/../../../config.php');

// Prepare and accept page params.
$id = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_TEXT);
$returnview = optional_param('retview', 0, PARAM_BOOL);

// Action requires session key.
require_sesskey();

// Basic security and capability checks.
require_login(null, false);
$context = context_system::instance();
require_capability('moodle/site:config', $context);
require_capability('tool/dataflows:managedataflows', $context);

// Find and set the dataflow, if not found, it will throw an exception.
$dataflow = new dataflow($id);

$returnurl = $returnview
    ? new moodle_url('/admin/tool/dataflows/view.php', ['id' => $dataflow->id])
    : new moodle_url('/admin/tool/dataflows/index.php');

// Ensure dataflows is not in readonly mode. If it is, display an error.
if (manager::is_dataflows_readonly()) {
    return redirect(
        $returnurl,
        get_string('readonly_active', 'tool_dataflows'),
        0,
        \core\output\notification::NOTIFY_ERROR
    );
}

$notifystring = null;
switch ($action) {
    case 'remove':
        // Remove the dataflow.
        $dataflow->delete();
        $notifystring = get_string('remove_dataflow_successful', 'tool_dataflows', $dataflow->name);
        break;

    case 'enable':
        $dataflow->set('enabled', 1);
        $dataflow->update();
        break;

    case 'disable':
        $dataflow->set('enabled', 0);
        $dataflow->update();
        break;

    default:
        break;
}


// Redirect to the dataflows details page.
if ($notifystring !== null) {
    redirect($returnurl, get_string($action.'_dataflow_successful', 'tool_dataflows', $dataflow->name),
    0, \core\output\notification::NOTIFY_SUCCESS);
}
redirect($returnurl);
