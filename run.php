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
use tool_dataflows\local\execution\engine;

define('NO_OUTPUT_BUFFERING', true);

require_once(dirname(__FILE__) . '/../../../config.php');

/**
 * Function used to handle mtrace by outputting the text to normal browser window.
 *
 * @param string $message Message to output
 * @param string $eol End of line character
 */
function tool_dataflows_mtrace_wrapper($message, $eol) {
    $class = '';
    $message = str_replace("\n", "\n    ", $message);

    // Mark up errors..
    if (preg_match('/error/im', $message)) {
        $class = 'bg-danger text-white';
    } else if (preg_match('/warn/im', $message)) {
        $class = 'bg-warning';
    }
    echo html_writer::tag('div', sprintf('%s %s', s($message), $eol), ['class' => $class]);
}

// Allow execution of single dataflow. This requires login and has different rules.
$dataflowid = required_param('dataflowid', PARAM_RAW_TRIMMED);
$confirm = optional_param('confirm', 0, PARAM_INT);
$returnurl = optional_param('returnurl', '/admin/tool/dataflows/index.php', PARAM_LOCALURL);
$dryrun = optional_param('dryrun', false, PARAM_BOOL);

// Basic security checks.
require_login(null, false);
require_capability('moodle/site:config', context_system::instance());
$context = context_system::instance();

// Find and set the dataflow, if not found, it will throw an exception.
$dataflow = new dataflow($dataflowid);

// Start output.
$url = new moodle_url('/admin/tool/dataflows/run.php', ['dataflowid' => $dataflowid]);
$PAGE->set_url($url);
$PAGE->set_context($context);

// Dataflows > Manage Flows > :dataflow->name > Run now.
visualiser::breadcrumb_navigation([
    [get_string('pluginmanage', 'tool_dataflows'), new moodle_url('/admin/tool/dataflows/index.php')],
    [$dataflow->name, new moodle_url('/admin/tool/dataflows/view.php', ['id' => $dataflowid])],
    [get_string('run_now', 'tool_dataflows'), $url],
]);

echo $OUTPUT->header();

$runnowurl = new moodle_url(
    '/admin/tool/dataflows/run.php',
    ['dataflowid' => $dataflowid, 'confirm' => 1, 'sesskey' => sesskey(), 'dryrun' => $dryrun]);

// The initial request just shows the confirmation page; doing nothing until confirmation.
if (!$confirm) {
    echo $OUTPUT->confirm(
        // Description.
        get_string('run_confirm', 'tool_dataflows', $dataflow->name),
        // Confirm.
        new single_button($runnowurl, get_string('run_now', 'tool_dataflows')),
        // Cancel.
        new single_button(new moodle_url($returnurl), get_string('cancel'), false));

    echo $OUTPUT->footer();
    exit;
}

// Action requires session key.
require_sesskey();

\core\session\manager::write_close();

raise_memory_limit(MEMORY_HUGE);
core_php_time_limit::raise(300);

// Re-run the specified flow (this will output an error if it doesn't exist).
echo $OUTPUT->single_button($runnowurl, get_string('run_again', 'tool_dataflows'), 'post', ['class' => 'mb-3']);

echo html_writer::tag('small', '', ['id' => 'output-container']);

// Re-run the specified flow (this will output an error if it doesn't exist).
echo $OUTPUT->single_button($runnowurl, get_string('run_again', 'tool_dataflows'), 'post', ['class' => 'mb-3']);

echo html_writer::link(
    new moodle_url('/admin/tool/dataflows/index.php'),
    get_string('pluginmanage', 'tool_dataflows'));

echo html_writer::link(
    new moodle_url('/admin/tool/dataflows/view.php', ['id' => $dataflow->id]),
    get_string('back_to', 'tool_dataflows'),
    ['class' => 'ml-2']);

echo $OUTPUT->footer();

// Prepare to handle output via mtrace.
echo html_writer::start_tag('pre', ['class' => 'tool_dataflow-output']);

// See this video https://www.youtube.com/watch?v=LLRig4s1_yA&t=1022s
// to explain this cool hack to stream unbuffered html output directly
// into an element with no ongoing javascript.
echo <<<EOF
<script>
document.getElementById('output-container').append(document.getElementsByClassName('tool_dataflow-output')[0]);
</script>
EOF;
$CFG->mtrace_wrapper = 'tool_dataflows_mtrace_wrapper';
try {
    $engine = new engine($dataflow, $dryrun, false);
    // TODO: Validate it can run.
    // Run the specified flow (this will output an error if it doesn't exist).
    $engine->execute();
} catch (\Throwable $e) {
    if (isset($engine)) {
        $engine->log($e);
    } else {
        mtrace('Engine \'' . $dataflow->name . '\': ' . $e->getMessage());
    }
}

echo html_writer::end_tag('pre');
