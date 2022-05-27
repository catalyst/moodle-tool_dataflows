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
     * @param string $dotscript the script for DOT to generate the image.
     * @param $type supported image types: jpg, gif, png, svg, ps.
     * @return binary content of the generated image on success, empty string on
     *           failure.
     *
     * @author     cjiang
     * @author     Kevin Pham <kevinpham@catalyst-au.net>
     */
    public static function generate($dotscript, $type) {
        global $CFG;

        $descriptorspec = [
           // The stdin is a pipe that the child will read from.
           0 => ['pipe', 'r'],
           // The stdout is a pipe that the child will write to.
           1 => ['pipe', 'w'],
           // The stderr is a pipe that the child will write to.
           2 => ['pipe', 'w']
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
            fwrite($pipes[0], $dotscript);
            fclose($pipes[0]);

            $output = stream_get_contents($pipes[1]);

            $err = stream_get_contents($pipes[2]);
            if (!empty($err)) {
                print "failed to execute cmd: \"$cmd\". stderr: `$err'\n";
                exit;
            }

            fclose($pipes[2]);
            fclose($pipes[1]);
            proc_close($process);
            return $output;
        }

        print "failed to execute cmd \"$cmd\"";
        exit();
    }

    public static function display_dataflows_table(dataflows_table $table, \moodle_url $url, string $pageheading) {
        global $PAGE;

        $download = optional_param('download', '', PARAM_ALPHA);

        $context = \context_system::instance();

        $PAGE->set_context($context);
        $PAGE->set_url($url);

        $output = $PAGE->get_renderer('tool_dataflows');
        $pluginname = get_string('pluginname', 'tool_dataflows');

        $table->is_downloading($download, 'dataflows', 'flows');
        $table->define_baseurl($url);

        if (!$table->is_downloading()) {
            $PAGE->set_title($pluginname . ': ' . $pageheading);
            $PAGE->set_pagelayout('admin');
            $PAGE->set_heading($pluginname);
            echo $output->header();
            echo $output->heading($pageheading);
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

        if (!$table->is_downloading()) {
            echo $output->footer();
        }
    }

    public static function display_steps_table(int $dataflowid, steps_table $table, \moodle_url $url, string $pageheading) {
        global $PAGE;

        $download = optional_param('download', '', PARAM_ALPHA);

        $context = \context_system::instance();

        $PAGE->set_context($context);
        $PAGE->set_url($url);

        $output = $PAGE->get_renderer('tool_dataflows');
        $pluginname = get_string('pluginname', 'tool_dataflows');

        $table->is_downloading($download, 'dataflows', 'flows');
        $table->define_baseurl($url);

        if (!$table->is_downloading()) {
            $dataflow = new dataflow($dataflowid);
            $PAGE->set_title($pluginname . ': ' . $dataflow->name . ': ' . $pageheading);
            $PAGE->set_pagelayout('admin');
            $PAGE->set_heading($pluginname . ': ' . $dataflow->name);
            echo $output->header();

            // Validate current dataflow, displaying any reason why the flow is not valid.
            $validation = $dataflow->validate_dataflow();

            // Edit dataflow button.
            $icon = $output->render(new \pix_icon('i/settings', get_string('edit')));
            $importurl = new \moodle_url(
                '/admin/tool/dataflows/edit.php',
                ['id' => $dataflow->id]);
            $exportbtn = \html_writer::tag(
                'button',
                $icon . get_string('edit'),
                ['class' => 'btn btn-secondary ml-2' ]
            );
            echo \html_writer::link($importurl, $exportbtn);

            // Display the run now button (disabling it if dataflow is not valid).
            $runurl = new \moodle_url(
                '/admin/tool/dataflows/run.php',
                ['dataflowid' => $dataflow->id]);
            $runbuttonattributes = ['class' => 'btn btn-warning mx-2' ];
            if ($validation !== true) {
                $runbuttonattributes['disabled'] = true;
            }
            $icon = $output->render(new \pix_icon('t/go', get_string('run_now', 'tool_dataflows')));
            $runbutton = \html_writer::tag('button', $icon . get_string('run_now', 'tool_dataflows'), $runbuttonattributes);
            echo \html_writer::link($runurl, $runbutton);

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

            // Generate the image based on the DOT script.
            $contents = self::generate($dataflow->get_dotscript(), 'svg');

            // Display the current dataflow visually.
            echo \html_writer::div($contents, 'text-center p-4');

            if ($validation !== true) {
                foreach ($validation as $message) {
                    echo $output->notification($message);
                }
            }

            echo $output->heading($pageheading);
        }

        // New Step.
        $icon = $output->render(new \pix_icon('t/add', get_string('import', 'tool_dataflows')));
        $addbutton = \html_writer::tag(
            'button',
            $icon . get_string('new_step', 'tool_dataflows'),
            ['class' => 'btn btn-primary mb-3']
        );
        $addurl = new \moodle_url('/admin/tool/dataflows/step.php', ['dataflowid' => $dataflowid]);
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

        if (!$table->is_downloading()) {

            echo \html_writer::empty_tag('hr');

            echo $output->footer();
        }
    }
}

