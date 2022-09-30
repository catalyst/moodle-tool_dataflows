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

use Symfony\Component\Yaml\Yaml;

/**
 * Display a table of dataflow steps.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class steps_table extends sql_table {

    /** Columns to display. */
    const COLUMNS = [
        'name',
        'description',
        'config',
        'details',
    ];

    /** Columns that shouldn't be sorted. */
    const NOSORT_COLUMNS = [
        'actions',
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
        $step = new step($record->id);
        $contents = visualiser::generate($step->get_dotscript(), 'svg');
        return $contents;
    }

    /**
     * Display the step type
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_description(\stdClass $record): string {

        // Prepare the base class name and fully qualified class name (FQCN).
        $classname = $record->type;
        $position = strrpos($classname, '\\');
        $basename = substr($classname, $position + 1);
        if ($position === false) {
            $basename = $classname;
        }

        // Icons relating to the step type and its functions. Currently
        // colours/shapes which will match up closely with the current dataflow
        // diagram.
        $icons = [];
        if (class_exists($classname)) {
            $steptype = new $classname();
            $basename = $steptype->get_name();
        } else {
            $icons[] = \html_writer::tag('span', '❓', [
                'title' => get_string('steptypedoesnotexist', 'tool_dataflows', $record->type),
            ]);
        }

        // For readability, opting to show the name of the type of step first, and FQCN afterwards.
        // TODO: When downloading, display as below, otherwise split into next line for web view.
        // Example: writer_debugging (tool_dataflows\local\step\writer_debugging).
        $str = '';
        if (count($icons)) {
            $str .= \html_writer::tag('span', implode(' ', $icons), ['class' => 'text-muted small mr-1']);
        }
        $str .= \html_writer::tag('code', '${{steps.' . $record->alias . '}}') . '<br>';
        $str .= "$basename<br>";
        $str .= '<small><pre>' . s($record->type) . '</pre></small><br>';
        $str .= $record->description;

        return $str;
    }

    /**
     * Display the configuration
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_config(\stdClass $record): string {
        $step = new step($record->id);
        $redactedconfig = $step->get_redacted_config(false);

        // We want to display the vars definitions as well.
        $vars = $step->get_raw_vars();
        if (!helper::obj_empty($vars)) {
            $redactedconfig->vars = $vars;
        }
        if (helper::obj_empty($redactedconfig)) {
            return '';
        }
        $output = Yaml::dump(
            $redactedconfig,
            helper::YAML_DUMP_INLINE_LEVEL,
            helper::YAML_DUMP_INDENT_LEVEL,
            Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | YAML::DUMP_OBJECT_AS_MAP
        );
        return \html_writer::tag('pre', $output, ['style' => 'max-width: 30em;']);
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
            $label = "{$dep->name}";
            if (isset($dep->position)) {
                // If the step has a defined key / label for this entry, then use that label instead.
                // For example: 'case #14' could instead be 'case: even number detected'.
                $outputlabel = $dep->position;
                if (isset($dep->type) && class_exists($dep->type)) {
                    $depstep = new step($dep->id);
                    $steptype = $depstep->steptype;
                    $outputlabel = $steptype->get_output_label($dep->position);
                }

                $label .= " → {$outputlabel}";
            }
            $out .= \html_writer::tag('li', $label);
        }
        $out .= \html_writer::end_tag('ul');
        return $out;
    }

    /**
     * Display any extra information about the step that doesn't fit into any other column.
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_details(\stdClass $record): string {
        $step = new step($record->id);
        $steptype = $step->steptype;
        if (!isset($steptype)) {
            return '';
        }
        return $steptype->get_details();
    }

    /**
     * Display a list of action links available for the step (e.g. delete, copy, move, etc.)
     *
     * @param \stdClass $record
     * @return string
     */
    public function col_actions(\stdClass $record): string {
        global $OUTPUT;

        // Display the remove_step button.
        $icon = $OUTPUT->render(new \pix_icon('i/delete', get_string('remove_step', 'tool_dataflows')));
        $removeurl = new \moodle_url(
            '/admin/tool/dataflows/remove-step.php',
            ['stepid' => $record->id, 'sesskey' => sesskey()]);

        return $OUTPUT->action_link(
            $removeurl,
            $icon,
            new \confirm_action(get_string('remove_confirm', 'tool_dataflows', $record->name))
        );
    }
}
