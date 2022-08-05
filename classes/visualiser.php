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
use tool_dataflows\local\step\base_step;

/**
 * Dataflows visualiser
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class visualiser {

    /**
     * Constructs the breadcrumb admin navigation to a nested plugin page
     *
     * @param array $crumbs navigation crumbs, in the format of [title, moodle_url] per entry
     */
    public static function breadcrumb_navigation(array $crumbs) {
        global $PAGE;

        // Admin tools.
        $PAGE->navbar->add(
            get_string('tools', 'admin'),
            new \moodle_url('/admin/category.php?category=tools'));

        // Dataflows (category).
        $PAGE->navbar->add(
            get_string('pluginname', 'tool_dataflows'),
            new \moodle_url('/admin/category.php?category=tool_dataflows'));

        // Provided page(s), going from plugin dir to desired page.
        foreach ($crumbs as [$title, $url]) {
            $PAGE->navbar->add($title, $url);
        }

        // Sets the URL to the last $url in the list (typically the current page's url).
        $PAGE->set_url($url);

        // This may be overriden later on, but keep nice heading and title defaults for now.
        $PAGE->set_heading(get_string('pluginname', 'tool_dataflows') . ': ' . $title);
        $PAGE->set_title(get_string('pluginname', 'tool_dataflows') . ': ' . $title);
    }

    /**
     * Generate image according to DOT script. This function will spawn a process
     * with "dot" command and pipe the "dot_script" to it and pipe out the
     * generated image content.
     *
     * @param  string $dotscript the script for DOT to generate the image.
     * @param  string $type supported image types: jpg, gif, png, svg, ps.
     * @return binary|string content of the generated image on success, empty string on failure.
     *
     * @author     cjiang
     * @author     Kevin Pham <kevinpham@catalyst-au.net>
     */
    public static function generate(string $dotscript, ?string $type = 'svg') {
        global $CFG, $OUTPUT;

        $cache = \cache::make('tool_dataflows', 'dot');
        $hash = hash('sha256', $dotscript . $type);

        if ($data = $cache->get($hash)) {
            return $data;
        }

        if (!helper::is_graphviz_dot_installed()) {
            return $OUTPUT->render(
                new \pix_icon(helper::GRAPHVIZ_ALT_ICON,
                    get_string('preview_unavailable', 'tool_dataflows'))
            ) . get_string('preview_unavailable', 'tool_dataflows');
        }

        $descriptorspec = [
            // The stdin is a pipe that the child will read from.
            0 => ['pipe', 'r'],
            // The stdout is a pipe that the child will write to.
            1 => ['pipe', 'w'],
            // The stderr is a pipe that the child will write to.
            2 => ['pipe', 'w'],
        ];

        $cmd = (!empty($CFG->pathtodot) ? $CFG->pathtodot : 'dot') . ' -T' . $type;
        $process = proc_open(
            $cmd,
            $descriptorspec,
            $pipes,
            sys_get_temp_dir(),
            ['PATH' => getenv('PATH')]
        );

        if (is_resource($process)) {
            [$stdin, $stdout, $stderr] = $pipes;
            fwrite($stdin, $dotscript);
            fclose($stdin);

            $output = stream_get_contents($stdout);

            $err = stream_get_contents($stderr);
            if (!empty($err)) {
                throw new \Exception("failed to execute cmd: \"$cmd\". stderr: `$err`", 1);
            }

            fclose($stderr);
            fclose($stdout);
            proc_close($process);
            $cache->set($hash, $output);
            return $output;
        }

        throw new \Exception("failed to execute cmd \"$cmd\"");
    }

    /**
     * Prepares and builds out the dataflow table & page
     *
     * @param  dataflows_table $table
     * @param  \moodle_url $url
     * @param  string $pageheading
     */
    public static function display_dataflows_table(dataflows_table $table, \moodle_url $url, string $pageheading) {
        global $PAGE;

        $context = \context_system::instance();

        $PAGE->set_context($context);
        $PAGE->set_url($url);

        $output = $PAGE->get_renderer('tool_dataflows');
        $pluginname = get_string('pluginname', 'tool_dataflows');

        $table->define_baseurl($url);

        $PAGE->set_title($pluginname . ': ' . $pageheading);
        $PAGE->set_pagelayout('admin');
        $PAGE->set_heading($pluginname);
        echo $output->header();
        echo $output->heading($pageheading);

        if (!helper::is_graphviz_dot_installed()) {
            \core\notification::warning(
                get_string(
                    'no_dot_installed',
                    'tool_dataflows',
                     \html_writer::link(
                         helper::README_DEPENDENCY_LINK,
                         get_string('here', 'tool_dataflows')
                     )
                )
            );
        }

        // New Dataflow.
        $icon = $output->render(new \pix_icon('t/add', get_string('import', 'tool_dataflows')));
        $addbutton = \html_writer::tag(
            'button',
            $icon . get_string('new_dataflow', 'tool_dataflows'),
            ['class' => 'btn btn-primary']
        );

        $addurl = new \moodle_url('/admin/tool/dataflows/edit.php');
        $newdataflow = \html_writer::link($addurl, $addbutton);

        // Import Dataflow.
        $icon = $output->render(new \pix_icon('i/upload', get_string('import', 'tool_dataflows')));
        $importbutton = \html_writer::tag(
            'button',
            $icon . get_string('import_dataflow', 'tool_dataflows'),
            ['class' => 'btn btn-secondary ml-2']
        );
        $importurl = new \moodle_url('/admin/tool/dataflows/import.php');
        $importdataflow = \html_writer::link($importurl, $importbutton);

        echo \html_writer::tag('div', $newdataflow . $importdataflow, ['class' => 'mb-3']);

        // No hide/show links under each column.
        $table->collapsible(false);
        // Columns are presorted.
        $table->sortable(false);
        // Table does not show download options by default, an import/export option will be available instead.
        $table->is_downloadable(false);
        $table->out($table->pagesize, false);

        echo $output->footer();
    }

    /**
     * Prepares and displays the dataflows details page
     *
     * @param  int $dataflowid
     * @param  steps_table $table
     * @param  \moodle_url $url
     * @param  string $pageheading
     */
    public static function display_dataflows_view_page(int $dataflowid, steps_table $table, \moodle_url $url, string $pageheading) {
        global $PAGE;

        $output = $PAGE->get_renderer('tool_dataflows');
        $pluginname = get_string('pluginname', 'tool_dataflows');

        $table->define_baseurl($url);

        $dataflow = new dataflow($dataflowid);
        $PAGE->set_title($pluginname . ': ' . $dataflow->name . ': ' . $pageheading);
        $PAGE->set_pagelayout('admin');
        $PAGE->set_heading($pluginname . ': ' . $dataflow->name);
        echo $output->header();

        // Validate current dataflow, displaying any reason why the flow is not valid.
        $validation = $dataflow->validate_dataflow();

        echo \html_writer::start_div('tool_dataflow-top-bar');

        echo \html_writer::start_div('tool_dataflow-actions-bar');
        // Display the run now button (disabling it if dataflow is not valid).
        if ($validation === true) {
            $buttoncolour = 'btn-success';
            $buttonicon = 't/go';
        } else {
            $buttoncolour = 'btn-danger';
            $buttonicon = 't/block';
        }
        $runurl = new \moodle_url(
            '/admin/tool/dataflows/run.php',
            [
                'dataflowid' => $dataflow->id,
                'returnurl' => $PAGE->url->out(false),
            ]);
        $runbuttonattributes = ['class' => 'btn ' . $buttoncolour];
        if ($validation !== true) {
            $runbuttonattributes['disabled'] = true;
        }
        $icon = $output->render(new \pix_icon($buttonicon, get_string('run_now', 'tool_dataflows')));
        $runbutton = \html_writer::tag('button', $icon . get_string('run_now', 'tool_dataflows'), $runbuttonattributes);
        echo \html_writer::link($runurl, $runbutton);

        $runurl->param('dryrun', true);

        $runbuttonattributes['class'] = 'btn btn-secondary mx-2';
        $dryrunbutton = \html_writer::tag('button', $icon . get_string('dry_run', 'tool_dataflows'), $runbuttonattributes);
        echo \html_writer::link($runurl, $dryrunbutton);

        // Edit dataflow button.
        $icon = $output->render(new \pix_icon('i/settings', get_string('edit')));
        $importurl = new \moodle_url(
            '/admin/tool/dataflows/edit.php',
            ['id' => $dataflow->id]);
        $exportbtn = \html_writer::tag(
            'button',
            $icon . get_string('edit'),
            ['class' => 'btn btn-secondary mx-2']
        );
        echo \html_writer::link($importurl, $exportbtn);

        // Display the export button.
        $icon = $output->render(new \pix_icon('t/download', get_string('export', 'tool_dataflows')));
        $exportactionurl = new \moodle_url(
            '/admin/tool/dataflows/export.php',
            ['dataflowid' => $dataflowid, 'sesskey' => sesskey()]);
        $btnuid = 'exportbuttoncontents';
        $btn = $output->single_button($exportactionurl, $btnuid);
        $exportbtn = str_replace($btnuid, $icon . get_string('export', 'tool_dataflows'), $btn);
        echo $exportbtn;

        // Display the import button, which links to the import page.
        $icon = $output->render(new \pix_icon('i/upload', get_string('import', 'tool_dataflows')));
        $importurl = new \moodle_url(
            '/admin/tool/dataflows/import.php',
            ['dataflowid' => $dataflow->id]);
        $exportbtn = \html_writer::tag(
            'button',
            $icon . get_string('import', 'tool_dataflows'),
            ['class' => 'btn btn-secondary ml-2' ]
        );
        echo \html_writer::link($importurl, $exportbtn);

        // Display the standard enable and disable icon.
        if ($dataflow->enabled) {
            $icon = $output->render(new \pix_icon('t/show', get_string('disable'), 'moodle'));
            $action = 'disable';
        } else {
            $icon = $output->render(new \pix_icon('t/hide', get_string('enable'), 'moodle'));
            $action = 'enable';
        }
        $enableurl = new \moodle_url('/admin/tool/dataflows/dataflow-action.php',
            ['id' => $dataflow->id, 'action' => $action, 'sesskey' => sesskey(), 'retview' => 1]);
        $enablebtn = \html_writer::tag(
                'button',
                $icon . get_string($action),
                ['class' => 'btn btn-secondary ml-2']
            );
        echo \html_writer::link($enableurl, $enablebtn);

        // Remove dataflow button.
        $icon = $output->render(new \pix_icon('t/delete', get_string('delete')));
        $deleteurl = new \moodle_url('/admin/tool/dataflows/remove-dataflow.php', ['id' => $dataflow->id]);
        $deletebutton = new \single_button($deleteurl, $btnuid);
        $deletebutton->add_confirm_action(get_string('remove_dataflow_confirm', 'tool_dataflows', $dataflow->name));
        $deletebutton->class .= ' ml-2';
        $content = $output->render($deletebutton);
        echo str_replace($btnuid, $icon . get_string('delete'), $content);
        echo \html_writer::end_div(); // Closing tag for the .tool_dataflow-actions-bar div.

        echo \html_writer::start_div('tool_dataflow-runs-bar');
        // Display the most recent runs across the top, if it goes over a
        // certain amount then it will link to a paginated table listing out the
        // previous runs. This can have a background color to indicate the
        // status of the run and only display the run name, etc.
        // TODO: for now link to the "All runs" (for this dataflow) page containing the table.

        $maxrunstoshow = 10;
        $runs = $dataflow->get_runs($maxrunstoshow);

        // Show this when the number of runs equals the max runs, and the first run in the list is NOT run #1.
        if (count($runs) === $maxrunstoshow && ((int) reset($runs)->name !== 1)) {
            $allrunsurl = new \moodle_url('/admin/tool/dataflows/runs.php', ['id' => $dataflow->id]);
            echo \html_writer::link($allrunsurl, get_string('all_runs', 'tool_dataflows'), ['class' => 'btn btn-run-default']);
        }

        // Show up to the last 10 runs.
        foreach ($runs as $run) {
            $runurl = new \moodle_url('/admin/tool/dataflows/view-run.php', ['id' => $run->id]);
            $runstate = engine::STATUS_LABELS[$run->status];
            echo \html_writer::link($runurl, $run->name, ['class' => "btn btn-run-default run-state-{$runstate}"]);
        }

        // Recent runs label (no recent, or recent runs to describe the list).
        if (!empty($runs)) {
            $recentrunsstr = get_string('recent_runs', 'tool_dataflows');
        } else {
            $recentrunsstr = get_string('no_recent_runs', 'tool_dataflows');
        }
        $recentrunslabel = \html_writer::tag('span', $recentrunsstr, ['class' => 'btn btn-text']);
        echo $recentrunslabel;

        echo \html_writer::end_div(); // Closing tag for the .tool_dataflow-runs-bar div.

        echo \html_writer::end_div(); // Closing tag for the .tool_dataflow-top-bar div.

        // Generate the image based on the DOT script.
        $contents = self::generate($dataflow->get_dotscript(), 'svg');

        // Display the current dataflow visually.
        echo \html_writer::div($contents, 'text-center p-4');

        if ($validation !== true) {
            $errors = '';
            foreach ($validation as $message) {
                $errors .= \html_writer::tag('li', $message);
            }
            $errors = \html_writer::tag('ul', $errors);
            echo $output->notification($errors);
        }

        echo $output->heading($pageheading);

        // New Step.
        $icon = $output->render(new \pix_icon('t/add', get_string('import', 'tool_dataflows')));
        $addbutton = \html_writer::tag(
            'button',
            $icon . get_string('new_step', 'tool_dataflows'),
            ['class' => 'btn btn-primary mb-3']
        );
        $addurl = new \moodle_url('/admin/tool/dataflows/step-chooser.php', ['id' => $dataflowid]);
        echo \html_writer::link($addurl, $addbutton);

        // No hide/show links under each column.
        $table->collapsible(false);
        // Columns are presorted.
        $table->sortable(false);
        // Table does not show download options by default, an import/export option will be available instead.
        $table->is_downloadable(false);

        // Output the table manually based on the step order.
        $table->setup();
        $table->query_db(0); // No limit, fetch all rows.
        $table->pageable(false);
        $table->close_recordset();

        // Custom sort on the step order.
        $newdata = [];
        foreach ($dataflow->step_order as $stepid) {
            $newdata[] = $table->rawdata[$stepid];
        }
        $table->rawdata = $newdata;

        $table->build_table();
        $table->finish_output();

        echo $output->footer();
    }

    /**
     * Get a description of the link count requirements, for a step.
     *
     * @param base_step $steptype
     * @param string $inputoutput 'input' or 'output'
     * @param string $flowconnector 'flow' or 'connector'
     * @return string Returns a description for the link requirements, or '' when no links are allowed.
     * @throws \coding_exception
     */
    protected static function get_link_limit(base_step $steptype, string $inputoutput, string $flowconnector): string {
        $fn = "get_number_of_{$inputoutput}_{$flowconnector}s";
        list($min, $max) = $steptype->$fn();

        // Consider output label counts.
        if ($inputoutput === 'output') {
            $min = max($min, count($steptype->get_output_labels()));
        }

        if ($min === $max) {
            if ($min > 1) {
                return get_string("{$inputoutput}_{$flowconnector}_link_limit_plural", 'tool_dataflows', $min);
            } else if ($min > 0) {
                return get_string("{$inputoutput}_{$flowconnector}_link_limit", 'tool_dataflows', $min);
            } else {
                return '';
            }
        } else {
            return get_string("{$inputoutput}_{$flowconnector}_link_limit_range", 'tool_dataflows', ['min' => $min, 'max' => $max]);
        }
    }

    /**
     * Generate a description for step link requirements.
     *
     * @param base_step $steptype
     * @param string $inputoutput
     * @return string
     * @throws \coding_exception
     */
    public static function get_link_expectations(base_step $steptype, string $inputoutput): string {
        $flowmsg = self::get_link_limit($steptype, $inputoutput, 'flow');
        $connectormsg = self::get_link_limit($steptype, $inputoutput, 'connector');

        if ($flowmsg && $connectormsg) {
            return get_string('requires_with_or', 'tool_dataflows', ['str1' => $flowmsg, 'str2' => $connectormsg]);
        } else {
            if ($flowmsg == '' && $connectormsg == '') {
                return get_string("no_{$inputoutput}_allowed", 'tool_dataflows');
            } else {
                // Combine a non-zero requirement with a zero requirement, but put the non-zero requirement first.
                if ($flowmsg == '') {
                    $str1 = $connectormsg;
                    $str2 = get_string("no_{$inputoutput}_flows", 'tool_dataflows');
                } else {
                    $str1 = $flowmsg;
                    $str2 = get_string("no_{$inputoutput}_connectors", 'tool_dataflows');
                }
                return get_string('requires', 'tool_dataflows', ['str1' => $str1, 'str2' => $str2]);
            }
        }
    }
}
