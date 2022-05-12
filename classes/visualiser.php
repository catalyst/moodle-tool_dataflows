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
        $addbutton = \html_writer::tag('button', get_string('new_dataflow', 'tool_dataflows'), ['class' => 'btn btn-primary']);
        $addurl = new \moodle_url('/admin/tool/dataflows/edit.php');
        echo \html_writer::link($addurl, $addbutton);

        // Import Dataflow.
        $importbutton = \html_writer::tag(
            'button',
            get_string('import_dataflow', 'tool_dataflows'),
            ['class' => 'hidden btn btn-primary ml-2']
        );
        $importurl = new \moodle_url('/admin/tool/dataflows/import.php');
        echo \html_writer::link($importurl, $importbutton);

        $table->out($table->pagesize, false);
        $table->is_downloadable(false);

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
            $PAGE->set_heading($pluginname);
            echo $output->header();
            echo $output->heading($dataflow->name);

            // Generate the image based on the DOT script.
            $contents = self::generate($dataflow->get_dotscript(), 'svg');

            // Display the current dataflow visually.
            echo \html_writer::div($contents, 'text-center p-4');

            echo $output->heading($pageheading);
        }

        // New Step.
        $addbutton = \html_writer::tag('button', get_string('new_step', 'tool_dataflows'), ['class' => 'btn btn-primary']);
        $addurl = new \moodle_url('/admin/tool/dataflows/step.php', ['dataflowid' => $dataflowid]);
        echo \html_writer::link($addurl, $addbutton);

        $table->out($table->pagesize, false);
        $table->is_downloadable(false);

        if (!$table->is_downloading()) {
            echo $output->footer();
        }
    }
}

