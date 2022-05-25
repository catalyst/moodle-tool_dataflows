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
 * Import page
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_dataflows\dataflow;
use tool_dataflows\import_form;
use tool_dataflows\visualiser;

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

defined('MOODLE_INTERNAL') || die();

require_login();

admin_externalpage_setup('tool_dataflows_overview', '', null, '', ['pagelayout' => 'report']);

$context = context_system::instance();

// Check for caps.
require_capability('tool/dataflows:managedataflows', $context);

$overviewurl = new moodle_url('/admin/tool/dataflows/index.php');
$url = new moodle_url('/admin/tool/dataflows/import.php');

$PAGE->set_url($url);

$customdata = [];
$form = new import_form($PAGE->url->out(false), $customdata);
if ($form->is_cancelled()) {
    redirect($overviewurl);
}

if (($data = $form->get_data())) {
    try {
        // Do stuff.
        $filecontent = $form->get_file_content('userfile');
        $yaml = \Symfony\Component\Yaml\Yaml::parse($filecontent);
        $dataflow = new dataflow();
        $dataflow->import($yaml);
        \core\notification::success(get_string('changessaved'));
    } catch (Exception $e) {
        \core\notification::error($e->getMessage());
    }

    // We are done, so let's redirect somewhere.
    redirect($overviewurl);
}

// Display the mandatory header and footer.
$heading = get_string('import', 'tool_dataflows');
// Configure the breadcrumb navigation.
visualiser::breadcrumb_navigation([
    [get_string('pluginmanage', 'tool_dataflows'), $overviewurl],
    [$heading, $url],
]);

$title = implode(': ', array_filter([
    get_string('pluginname', 'tool_dataflows'),
    $heading
]));
$PAGE->set_title($title);
$PAGE->set_heading(get_string('pluginname', 'tool_dataflows'));
echo $OUTPUT->header();

// Output headings.
echo $OUTPUT->heading($heading);

// And display the form, and its validation errors if there are any.
$form->display();

// Display footer.
echo $OUTPUT->footer();
