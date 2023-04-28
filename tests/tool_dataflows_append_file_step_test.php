<?php
// This file is part of Moodle - https://moodle.org/
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
use tool_dataflows\local\execution\engine;

defined('MOODLE_INTERNAL') || die();

require_once('backup/cc/cc_lib/gral_lib/pathutils.php');

/**
 * Unit test for append file step.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_append_file_step_test extends \advanced_testcase {

    /** Test file data. */
    const TEST_FILE_DATA = [
        'a.txt' => 'blah! vlah',
        'b.txt' => "abc\nxyz",
        'c.txt' => '  ',
    ];

    /** Existing file data. */
    const EXISTING_FILE_DATA = [
        'a.txt' => null,
        'b.txt' => 'abc',
        'c.txt' => null,
    ];

    /** Expected for test_strip_first_line. */
    const EXPECTED_FOR_STRIP = "blah! vlahxyz";

    /** Out file name. */
    const OUT_FILE_NAME = 'out.txt';

    /** File list name. */
    const FILE_LIST_NAME = 'files.csv';

    /** @var string  Base directory. */
    private $basedir;

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $filedata = self::TEST_FILE_DATA;
        $filenamedata = 'fn' . PHP_EOL . implode(PHP_EOL, array_keys($filedata));

        // Set up list of files to be pumped through the flow loop.
        $basedir = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        file_put_contents($basedir . self::FILE_LIST_NAME, $filenamedata);

        // Set up source data to be read from.
        foreach (self::TEST_FILE_DATA as $filename => $filecontent) {
            file_put_contents($basedir . $filename, $filecontent);
        }

        $this->basedir = $basedir;
    }

    /**
     * Tests appending to a single file.
     *
     * @covers \tool_dataflows\local\step\flow_append_file
     */
    public function test_append_one_file() {
        // Remove the destination file.
        if (file_exists($this->basedir . self::OUT_FILE_NAME)) {
            unlink($this->basedir . self::OUT_FILE_NAME);
        }

        // Perform the test.
        set_config('permitted_dirs', $this->basedir, 'tool_dataflows');
        $dataflow = $this->make_dataflow($this->basedir . self::OUT_FILE_NAME);

        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $expecteddata = implode('', self::TEST_FILE_DATA);
        $actualdata = file_get_contents($this->basedir . self::OUT_FILE_NAME);
        $this->assertEquals($expecteddata, $actualdata);
    }

    /**
     * Tests appending to many files, declared 1:1.
     *
     * @covers \tool_dataflows\local\step\flow_append_file
     */
    public function test_append_many_files() {
        // Set up files that will be appended to.
        foreach (self::EXISTING_FILE_DATA as $filename => $filecontent) {
            $path = $this->basedir . '_' . $filename;
            if (file_exists($path)) {
                unlink($path);
            }
            if (!is_null($filecontent)) {
                file_put_contents($path, $filecontent);
            }
        }

        // Perform the test.
        set_config('permitted_dirs', $this->basedir, 'tool_dataflows');
        $dataflow = $this->make_dataflow($this->basedir . '_${{record.fn}}');

        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        foreach (self::TEST_FILE_DATA as $name => $content) {
            $expecteddata = self::EXISTING_FILE_DATA[$name] . $content;
            $actualdata = file_get_contents($this->basedir . "_$name");
            $this->assertEquals($expecteddata, $actualdata);
        }
    }

    /**
     * Tests appending to a directory.
     *
     * @covers \tool_dataflows\local\step\flow_append_file
     */
    public function test_append_dir() {
        // Set up a directory (with files) that will be appended to.
        $dir = $this->basedir . 'somedir';
        if (file_exists($dir)) {
            if (is_dir($dir)) {
                rmdirr($dir);
            } else {
                unlink($dir);
            }
        }
        mkdir($dir);
        $dir .= DIRECTORY_SEPARATOR;

        // Set up files within the directory that will be appended to.
        foreach (self::EXISTING_FILE_DATA as $filename => $filecontent) {
            if (file_exists($dir . $filename)) {
                unlink($dir . $filename);
            }
            if (!is_null($filecontent)) {
                file_put_contents($dir . $filename, $filecontent);
            }
        }

        // Perform the test.
        set_config('permitted_dirs', $this->basedir, 'tool_dataflows');
        $dataflow = $this->make_dataflow($this->basedir . 'somedir');

        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        foreach (self::TEST_FILE_DATA as $name => $content) {
            $expecteddata = self::EXISTING_FILE_DATA[$name] . $content;
            $actualdata = file_get_contents($this->basedir . 'somedir' . DIRECTORY_SEPARATOR . $name);
            $this->assertEquals($expecteddata, $actualdata);
        }
    }

    /**
     * Tests stripping the first line.
     *
     * @covers \tool_dataflows\local\step\flow_append_file
     */
    public function test_strip_first_line() {
        // Remove the destination file.
        if (file_exists($this->basedir . self::OUT_FILE_NAME)) {
            unlink($this->basedir . self::OUT_FILE_NAME);
        }

        // Perform the test.
        set_config('permitted_dirs', $this->basedir, 'tool_dataflows');
        $dataflow = $this->make_dataflow($this->basedir . self::OUT_FILE_NAME, true);

        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $actualdata = file_get_contents($this->basedir . self::OUT_FILE_NAME);
        $this->assertEquals(self::EXPECTED_FOR_STRIP, $actualdata);
    }

    /**
     * Creates a dataflow to test.
     *
     * @param string $to Destination file parameter.
     * @param bool $stripfirstline
     * @return dataflow
     */
    private function make_dataflow(string $to, bool $stripfirstline = false) {
        $namespace = '\\tool_dataflows\\local\\step\\';

        $dataflow = new dataflow();
        $dataflow->enabled = true;
        $dataflow->name = 'dataflow';
        $dataflow->save();

        $reader = new step();
        $reader->name = 'reader';
        $reader->type = $namespace . 'reader_csv';
        $reader->config = Yaml::dump([
            'path' => $this->basedir . self::FILE_LIST_NAME,
            'delimiter' => ',',
            'headers' => '',
        ]);
        $dataflow->add_step($reader);

        $step = new step();
        $step->name = 'appender';
        $step->type = $namespace . 'flow_append_file';
        $step->depends_on([$reader]);
        $step->config = Yaml::dump([
            'from' => $this->basedir . '${{record.fn}}',
            'to' => $to,
            'chopfirstline' => $stripfirstline,
        ]);
        $dataflow->add_step($step);

        return $dataflow;
    }
}
