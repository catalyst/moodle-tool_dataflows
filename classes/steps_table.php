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
 * Display a table of dataflow steps.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class steps_table extends \table_sql {

    const COLUMNS = [
        'name',
        'type',
        'config',
        'dependson',
    ];

    const NOSORT_COLUMNS = [
        'manage',
        'dependson',
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
     * Display the step name
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_name(\stdClass $record): string {
        $url = new \moodle_url('/admin/tool/dataflows/step.php', ['id' => $record->id, 'dataflowid' => $record->dataflowid]);
        return \html_writer::link($url, $record->name);
    }

    /**
     * Display the step type
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_type(\stdClass $record): string {
        // Prepare the base class name and fully qualified class name (FQCN).
        $classname = $record->type;
        $position = strrpos($classname, '\\');
        $basename = substr($classname, $position + 1);
        if ($position !== 0) {
            $basename = $classname;
        }

        // For readability, opting to show the name of the type of step first, and FQCN afterwards.
        // TODO: When downloading, display as below, otherwise split into next line for web view.
        // Example: debugging (tool_dataflows\step\debugging)
        $str = $basename;
        $str .= \html_writer::tag('div', "($classname)", ['class' => 'text-muted small']);
        return $str;
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
     * Display list of other step this one depends on
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_dependson(\stdClass $record): string {
        // List all the dependencies.
        $step = new step($record->id);
        $deps = $step->dependencies();

        // Make the list.
        $out = \html_writer::start_tag('ul');
        foreach ($deps as $dep) {
            $out .= \html_writer::tag('li', $dep->name);
        }
        $out .= \html_writer::end_tag('ul');
        return $out;
    }

    /**
     * Display a list of action links available for a dataflow (e.g. delete, copy, move, etc.)
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_manage(\stdClass $record): string {
        // TODO: Implement the actions.
        return '';
    }
}
