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

    /** Columns to display. */
    const COLUMNS = [
        'preview',
        'name',
        'userid',
        'details',
        'lastrunstart',
        'lastrunduration',
    ];

    /** Columns that shouldn't be sorted. */
    const NOSORT_COLUMNS = [
        'preview',
        'actions',
    ];

    /** Maximum width of the preview image (in pixels). */
    const PREVIEW_MAX_WIDTH = 250;

    /** Maximum height of the preview image (in pixels). */
    const PREVIEW_MAX_HEIGHT = 30;

    /**
     * Returns the columns defined for the table.
     *
     * @return string[]
     */
    protected function get_columns(): array {
        $columns = self::COLUMNS;
        if (!$this->is_downloading()) {
            $columns[] = 'actions';
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
        $this->sortable(false, 'name', SORT_DESC);
        $this->define_columns($columns);
        $this->define_headers($headers);
    }

    /**
     * Set sql sort value
     *
     * @return string
     */
    public function get_sql_sort() {
        return 'enabled DESC, name';
    }

    /**
     * Display a small preview of the workflow
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_preview(\stdClass $record): string {
        global $OUTPUT;
        $imgurl = new \moodle_url('/admin/tool/dataflows/visual.php', ['id' => $record->id, 'type' => 'png']);
        if (helper::is_graphviz_dot_installed()) {
            $img = \html_writer::img(
                $imgurl,
                "",
                [
                    'class' => 'lightbox',
                    'style' => 'max-width: ' . self::PREVIEW_MAX_WIDTH . 'px; max-height: ' . self::PREVIEW_MAX_HEIGHT . 'px',
                ]
            );
        } else {
            $img = $OUTPUT->render(
                new \pix_icon(helper::GRAPHVIZ_ALT_ICON,
                get_string('preview_unavailable', 'tool_dataflows'))
            ) . get_string('preview_unavailable', 'tool_dataflows');
        }
        $dataflowstepsurl = new \moodle_url('/admin/tool/dataflows/view.php', ['id' => $record->id]);
        return \html_writer::link($dataflowstepsurl, $img);
    }

    /**
     * Display the dataflow name
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_name(\stdClass $record): string {
        $url = new \moodle_url('/admin/tool/dataflows/view.php', ['id' => $record->id]);
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
     * Display any extra information about the steps that doesn't fit into any other column.
     * Gathers 'extra' info from each step.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_details(\stdClass $record): string {
        $content = [];
        $dataflow = new dataflow($record->id);
        foreach ($dataflow->steps as $step) {
            $steptype = $step->steptype;
            if (!isset($steptype)) {
                continue;
            }

            $extrainfo = $steptype->get_details();
            if (!empty($extrainfo)) {
                $content[] = $extrainfo;
            }
        }
        return implode('<br/>', $content);
    }

    /**
     * Display the last time the dataflow was run.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_lastrunstart(\stdClass $record): string {
        global $DB;

        $runrecords = $DB->get_records(
            run::TABLE,
            ['dataflowid' => $record->id],
            'timestarted DESC', 'timestarted', 0, 1);

        if (count($runrecords)) {
            $time = (int) current($runrecords)->timestarted;
            $timeago = time() - $time;
            return get_string('last_run_timeago', 'tool_dataflows', format_time($timeago));
        }
        return '';
    }

    /**
     * Display the duration of the last run.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_lastrunduration(\stdClass $record): string {
        global $DB;

        $runrecords = $DB->get_records(
            run::TABLE,
            ['dataflowid' => $record->id],
            'timestarted DESC', 'timestarted, timefinished', 0, 1);

        if (count($runrecords)) {
            $record = current($runrecords);

            $delta = $record->timefinished - $record->timestarted;
            if ($delta > 10) {
                return number_format($delta, 0) . ' ' . get_string('secs');
            }
            return number_format($delta, 1) . ' ' . get_string('secs');
        }
        return '';
    }

    /**
     * Display a list of action links available for a dataflow (e.g. delete, copy, export, etc.)
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_actions(\stdClass $record): string {
        global $OUTPUT;

        $content = '<nobr>';
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
        $icon = $OUTPUT->render(new \pix_icon('t/download', get_string('export', 'tool_dataflows'), 'moodle'));
        $exportactionurl = new \moodle_url(
            '/admin/tool/dataflows/export.php',
            ['dataflowid' => $record->id, 'sesskey' => sesskey()]);
        $content .= \html_writer::link($exportactionurl, $icon, ['class' => 'action-icon']);

        // Display the standard enable and disable icon.
        if ($record->enabled) {
            $icon = $OUTPUT->render(new \pix_icon('t/show', get_string('disable'), 'moodle'));
            $action = 'disable';
        } else {
            $icon = $OUTPUT->render(new \pix_icon('t/hide', get_string('enable'), 'moodle'));
            $action = 'enable';
        }
        $url = new \moodle_url('/admin/tool/dataflows/dataflow-action.php',
            ['id' => $record->id, 'action' => $action, 'sesskey' => sesskey()]);
        $content .= \html_writer::link($url, $icon, ['class' => 'action-icon']);

        // Delete dataflow icon.
        $deleteurl = new \moodle_url('/admin/tool/dataflows/dataflow-action.php',
            ['id' => $record->id, 'action' => 'remove', 'sesskey' => sesskey()]);
        $confirmaction = new \confirm_action(get_string('remove_dataflow_confirm', 'tool_dataflows', $record->name));
        $deleteicon = new \pix_icon('t/delete', get_string('remove_dataflow', 'tool_dataflows'));
        $link = new \action_link($deleteurl, '', $confirmaction, null,  $deleteicon);
        $content .= $OUTPUT->render($link);

        $content .= '</nobr>';
        return $content;
    }

    /**
     * Get row level classes for output
     *
     * @param \stdClass $row
     * @return string
     */
    public function get_row_class($row): string {
        if (!$row->enabled) {
            return 'dimmed_text';
        }
        return '';
    }
}
