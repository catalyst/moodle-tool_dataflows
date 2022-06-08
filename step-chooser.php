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

use tool_dataflows\visualiser;
use tool_dataflows\dataflow;
use tool_dataflows\manager;
use tool_dataflows\step;

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

defined('MOODLE_INTERNAL') || die();

require_login();

$id = required_param('id', PARAM_INT);

// Check for caps.
$context = context_system::instance();
require_capability('tool/dataflows:managedataflows', $context);

$url = new moodle_url('/admin/tool/dataflows/step-chooser.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_url($url);

// Configure the breadcrumb navigation.
$dataflow = new dataflow($id);
$pageheading = get_string('stepchooser', 'tool_dataflows');
visualiser::breadcrumb_navigation([
    // Dataflows > Manage Flows > :dataflow->name (details page).
    [get_string('pluginmanage', 'tool_dataflows'), new moodle_url('/admin/tool/dataflows/index.php')],
    [$dataflow->name, new moodle_url('/admin/tool/dataflows/view.php', ['id' => $id])],
    [$pageheading, new moodle_url('/admin/tool/dataflows/step-chooser.php', ['id' => $id])],
]);

// Header.
$OUTPUT = $PAGE->get_renderer('tool_dataflows');
$pluginname = get_string('pluginname', 'tool_dataflows');
$PAGE->set_title($pluginname . ': ' . $dataflow->name . ': ' . $pageheading);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($pluginname . ': ' . $dataflow->name);
echo $OUTPUT->header();
echo $OUTPUT->heading($pageheading);

// New Step.
$icon = $OUTPUT->render(new \pix_icon('t/add', get_string('import', 'tool_dataflows')));
$steptypes = manager::get_steps_types();
$items = array_reduce($steptypes, function ($acc, $steptype) use ($dataflow) {
    $step = new step();
    $step->name = $steptype->get_name();
    $step->type = get_class($steptype);

    $acc[$steptype->get_group()] = $acc[$steptype->get_group()] ?? [];
    $acc[$steptype->get_group()][] = [
        'href' => new \moodle_url(
            '/admin/tool/dataflows/step.php',
            ['dataflowid' => $dataflow->id, 'type' => get_class($steptype)]
        ),
        'label' => visualiser::generate($step->get_dotscript(false), 'svg'),
    ];
    return $acc;
}, []);

// Triggers have some trigger property configured TBD.
// Flows always handle some iterator.
$data = [
    'label' => $icon . get_string('new_step', 'tool_dataflows'),
    'items' => $items,
];
foreach ($data['items'] as $key => $value) {
    $data["has$key"] = !empty($value);
}
echo $OUTPUT->render_from_template('tool_dataflows/step-chooser', $data);

// Footer.
echo $OUTPUT->footer();
