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
 * Run a particular dataflow
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_dataflows\dataflow;
use tool_dataflows\visualiser;
use tool_dataflows\execution\engine;

define('NO_OUTPUT_BUFFERING', true);

require_once(dirname(__FILE__) . '/../../../config.php');

/**
 * Function used to handle mtrace by outputting the text to normal browser window.
 *
 * @param string $message Message to output
 * @param string $eol End of line character
 */
function tool_dataflows_mtrace_wrapper($message, $eol) {
    echo s($message . $eol);
}

// Allow execution of single dataflow. This requires login and has different rules.
$dataflowid = required_param('dataflowid', PARAM_RAW_TRIMMED);
$confirm = optional_param('confirm', 0, PARAM_INT);

// Basic security checks.
require_admin();
$context = context_system::instance();

// Find and set the dataflow, if not found, it will throw an exception.
$dataflow = new dataflow($dataflowid);

// Start output.
$url = new moodle_url('/admin/tool/dataflows/run.php', ['dataflowid' => $dataflowid]);
$PAGE->set_url($url);
$PAGE->set_context($context);

visualiser::breadcrumb_navigation([
    [get_string('pluginmanage', 'tool_dataflows'), new moodle_url('/admin/tool/dataflows/index.php')],
    [$dataflow->name, $url],
]);

echo $OUTPUT->header();

$runnowurl = new moodle_url(
    '/admin/tool/dataflows/run.php',
    ['dataflowid' => $dataflowid, 'confirm' => 1, 'sesskey' => sesskey()]);

// The initial request just shows the confirmation page; doing nothing until confirmation.
if (!$confirm) {
    echo $OUTPUT->confirm(
        // Description.
        get_string('run_confirm', 'tool_dataflows', $dataflow->name),
        // Confirm.
        new single_button($runnowurl, get_string('run_now', 'tool_dataflows')),
        // Cancel.
        new single_button(new moodle_url('/admin/tool/dataflows/index.php',
        ['dataflowid' => $dataflowid]),
        get_string('cancel'), false));

    echo $OUTPUT->footer();
    exit;
}

// Action requires session key.
require_sesskey();

\core\session\manager::write_close();

// Prepare to handle output via mtrace.
echo html_writer::start_tag('pre');
$CFG->mtrace_wrapper = 'tool_dataflows_mtrace_wrapper';

$engine = new engine($dataflow);
// TODO: Validate it can run.
// Run the specified flow (this will output an error if it doesn't exist).
$engine->execute();

echo html_writer::end_tag('pre');

// Re-run the specified flow (this will output an error if it doesn't exist).
echo $OUTPUT->single_button($runnowurl, get_string('run_again', 'tool_dataflows'), 'post', ['class' => 'mb-3']);

echo html_writer::link(
    new moodle_url('/admin/tool/dataflows/index.php'),
    get_string('pluginmanage', 'tool_dataflows'));

echo $OUTPUT->footer();
