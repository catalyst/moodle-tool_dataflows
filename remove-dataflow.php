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
 * Removes a dataflow.
 *
 * @package    tool_dataflows
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_dataflows\dataflow;

require_once(dirname(__FILE__) . '/../../../config.php');

// Prepare and accept page params.
$id = required_param('id', PARAM_INT);

// Action requires session key.
require_sesskey();

// Basic security and capability checks.
require_login(null, false);
$context = context_system::instance();
require_capability('moodle/site:config', $context);
require_capability('tool/dataflows:managedataflows', $context);

// Find and set the dataflow, if not found, it will throw an exception.
$dataflow = new dataflow($id);

$returnurl = new moodle_url('/admin/tool/dataflows/index.php');

// Remove the dataflow.
$dataflow->delete();

// Redirect to the dataflows details page.
redirect($returnurl, get_string('remove_dataflow_successful', 'tool_dataflows', $dataflow->name),
    0, \core\output\notification::NOTIFY_SUCCESS);
