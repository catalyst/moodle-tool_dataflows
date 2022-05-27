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

namespace tool_dataflows;

/**
 * Display a table of dataflows.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dataflows_table extends \table_sql {

    const COLUMNS = [
        'preview',
        'name',
        'config',
        'userid',
        'stepcount',
    ];

    const NOSORT_COLUMNS = [
        'preview',
        'manage',
    ];

    /**
     * Returns the columns defined for the table.
     *
     * @return string[]
     */
    protected function get_columns(): array {
        $columns = self::COLUMNS;
        if (!$this->is_downloading()) {
            $columns[] = 'manage';
        }
        return $columns;
    }

    /**
     * Defines the columns for this table.
     */
    public function make_columns(): void {
        $headers = [];
        $columns = $this->get_columns();
        foreach ($columns as $column) {
            $headers[] = get_string('field_' . $column, 'tool_dataflows');
        }
        foreach (self::NOSORT_COLUMNS ?? [] as $column) {
            $this->no_sorting($column);
        }
        $this->define_columns($columns);
        $this->define_headers($headers);
    }

    /**
     * Display a small preview of the workflow
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_preview(\stdClass $record): string {
        // IDEA: If the number of nodes in the dataflow is plenty, it might be
        // better to make the nodes coloured. And based on the shape, the user
        // can infer which one it is.
        $imgurl = new \moodle_url('/admin/tool/dataflows/visual.php', ['id' => $record->id, 'type' => 'png']);
        $img = \html_writer::img($imgurl, "Dataflow #{$record->id} visualisation", ['height' => 30, 'class' => 'lightbox']);
        $dataflowstepsurl = new \moodle_url('/admin/tool/dataflows/view.php', ['dataflowid' => $record->id]);
        return \html_writer::link($dataflowstepsurl, $img);
    }

    /**
     * Display the dataflow name
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_name(\stdClass $record): string {
        $url = new \moodle_url('/admin/tool/dataflows/view.php', ['dataflowid' => $record->id]);
        return \html_writer::link($url, $record->name);
    }

    /**
     * Display the user who created it
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_userid(\stdClass $record): string {
        $url = new \moodle_url('/user/profile.php', ['id' => $record->userid]);
        $fullname = fullname($record);
        return \html_writer::link($url, $fullname);
    }

    /**
     * Display the number of steps stored for this dataflow and a link to edit them.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_stepcount(\stdClass $record): string {
        $dataflowstepsurl = new \moodle_url('/admin/tool/dataflows/view.php', ['dataflowid' => $record->id]);
        return \html_writer::link($dataflowstepsurl, $record->stepcount);
    }

    /**
     * Display a list of action links available for a dataflow (e.g. delete, copy, export, etc.)
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_manage(\stdClass $record): string {
        global $OUTPUT;

        $content = '';
        // Display the run now button, which will link to a confirmation page.
        $runurl = new \moodle_url(
            '/admin/tool/dataflows/run.php',
            ['dataflowid' => $record->id]);
        $icon = $OUTPUT->render(new \pix_icon('t/go', get_string('run_now', 'tool_dataflows')));
        $content .= \html_writer::link($runurl, $icon, ['class' => 'action-icon']);

        // Display the run now button, which will link to a confirmation page.
        $editurl = new \moodle_url(
            '/admin/tool/dataflows/edit.php',
            ['id' => $record->id]);
        $icon = $OUTPUT->render(new \pix_icon('i/settings', get_string('edit')));
        $content .= \html_writer::link($editurl, $icon, ['class' => 'action-icon']);

        // Display the export button (adjusted to be a small icon button).
        $icon = $OUTPUT->render(new \pix_icon('t/download', get_string('export', 'tool_dataflows'), 'moodle', ['class' => 'mr-0']));
        $exportactionurl = new \moodle_url(
            '/admin/tool/dataflows/export.php',
            ['dataflowid' => $record->id, 'sesskey' => sesskey()]);
        $btnuid = 'exportbuttoncontents';
        $btn = $OUTPUT->single_button($exportactionurl, $btnuid, 'post');
        $exportbtn = str_replace($btnuid, $icon, $btn);
        $exportbtn = str_replace('btn-secondary', 'btn-link bn-sm p-0', $exportbtn);
        $content .= $exportbtn;

        return $content;
    }

    /**
     * Display the configuration
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_config(\stdClass $record): string {
        return \html_writer::tag('pre', $record->config);
    }
}
