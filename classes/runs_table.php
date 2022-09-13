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

use tool_dataflows\local\execution\engine;

/**
 * Display a table runs for a particular dataflow.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class runs_table extends sql_table {

    /** Columns to display. */
    const COLUMNS = [
        'name',
        'userid',
        'status',
        'timestarted',
        'timefinished',
        'duration',
    ];

    /** Columns that shouldn't be sorted. */
    const NOSORT_COLUMNS = ['actions'];

    /**
     * Returns the columns defined for the table.
     *
     * @return string[]
     */
    protected function get_columns(): array {
        $columns = self::COLUMNS;
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
     * Display the run name
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_name(\stdClass $record): string {
        $url = new \moodle_url('/admin/tool/dataflows/view-run.php', ['id' => $record->id]);
        $runstate = engine::STATUS_LABELS[$record->status];
        return \html_writer::link($url, $record->name, ['class' => "btn btn-run-default run-state-{$runstate}"]);
    }

    /**
     * Display the user who started it
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
     * Display the status as a string
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_status(\stdClass $record): string {
        if (is_null($record->status)) {
            return '';
        }
        return get_string('engine_status:' . engine::STATUS_LABELS[$record->status], 'tool_dataflows');
    }

    /**
     * Return the time started as a formatted datetime string
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_timestarted(\stdClass $record): string {
        return userdate($record->timestarted, get_string('strftimedatetimeaccurate', 'tool_dataflows'));
    }

    /**
     * Return the time finished as a formatted datetime string
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_timefinished(\stdClass $record): string {
        return userdate($record->timefinished, get_string('strftimedatetimeaccurate', 'tool_dataflows'));
    }

    /**
     * Return the duration in seconds of the run.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_duration(\stdClass $record): string {
        $totalsecs = number_format($record->timefinished - $record->timestarted, 3);
        return format_time((float) $totalsecs);
    }
}
