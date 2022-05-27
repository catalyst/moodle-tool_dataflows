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
 * Removes a dataflow step
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_dataflows\step;

require_once(dirname(__FILE__) . '/../../../config.php');

// Prepare and accept page params.
$stepid = required_param('stepid', PARAM_INT);

// Action requires session key.
require_sesskey();

// Basic security and capability checks.
require_login(null, false);
$context = context_system::instance();
require_capability('moodle/site:config', $context);
require_capability('tool/dataflows:managedataflows', $context);

// Find and set the dataflow, if not found, it will throw an exception.
$step = new step($stepid);

// Start output.
$dataflowdetailsurl = new moodle_url('/admin/tool/dataflows/view.php', ['id' => $step->dataflowid]);

// Remove the step.
$step->delete();

// Redirect to the dataflows details page.
redirect($dataflowdetailsurl, get_string('remove_step_successful', 'tool_dataflows', $step->name));
