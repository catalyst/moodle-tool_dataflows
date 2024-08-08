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
use tool_dataflows\local\step\reader_csv;

defined('MOODLE_INTERNAL') || die();

// Include lib.php functions that aren't included automatically for Moodle 37- and below.
require_once(dirname(__FILE__) . '/../lib.php');

/**
 * Unit test for the CSV reader step.
 *
 * @covers \tool_dataflows\local\step\reader_csv
 * @package   tool_dataflows
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_reader_csv_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('permitted_dirs', '/tmp', 'tool_dataflows');
    }

    /**
     * Test csv with headers included in the file contents
     */
    public function test_csv_with_headers_included_in_the_file_contents() {
        [$dataflow, $steps, $inputpath, $outputpath] = $this->create_dataflow();

        $reader = $steps[$dataflow->steps->reader->id];
        $reader->vars = Yaml::dump(['testapple' => '${{ record.apple }}']);
        $reader->config = Yaml::dump([
            'path' => $inputpath,
            'headers' => '',
            'delimiter' => ',',
            'maxlinelength' => '1000',
        ]);

        // Create test input file.
        $data = [
            ['apple', 'berry', 'cucumber'],
            [4, 5, 6],
            [7, 8, 9],
        ];
        $content = '';
        foreach ($data as $row) {
            $content .= implode(',', $row);
            $content .= PHP_EOL;
        }
        file_put_contents($inputpath, $content);

        // Execute.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $output = file_get_contents($outputpath);
        $this->assertEquals(7, $engine->get_variables_root()->get('steps.reader.vars.testapple'));

        $this->assertEquals($content, $output);
    }

    /**
     * Test csv with custom headers configured
     */
    public function test_csv_with_custom_headers_configured() {
        [$dataflow, $steps, $inputpath, $outputpath] = $this->create_dataflow();
        $headers = ['first', 'second', 'third'];

        $reader = $steps[$dataflow->steps->reader->id];
        $reader->vars = Yaml::dump(['testsecond' => '${{ record.second }}']);
        $reader->config = Yaml::dump([
            'path' => $inputpath,
            'headers' => implode(',', $headers),
            'delimiter' => ',',
            'maxlinelength' => '1000',
        ]);

        // Create test input file.
        $data = [
            ['apple', 'berry', 'cucumber'],
            [4, 5, 6],
            [7, 8, 9],
        ];
        $content = '';
        foreach ($data as $row) {
            $content .= implode(',', $row);
            $content .= PHP_EOL;
        }
        file_put_contents($inputpath, $content);

        // Execute.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $output = file_get_contents($outputpath);
        $this->assertEquals(8, $engine->get_variables_root()->get('steps.reader.vars.testsecond'));

        // Expected output; Add a header line.
        $expected = implode(',', $headers) . PHP_EOL . $content;
        $this->assertEquals($expected, $output);
    }

    /**
     * Tests for an invalid input stream.
     */
    public function test_bad_input_stream() {
        [$dataflow, $steps, $inputpath, $outputpath] = $this->create_dataflow();
        $path = 'path/to/nowhere';

        $reader = $steps[$dataflow->steps->reader->id];
        $reader->config = Yaml::dump([
            'path' => $path,
            'headers' => '',
            'delimiter' => ',',
            'maxlinelength' => '1000',
        ]);

        $this->expectException('\moodle_exception');

        // Execute.
        ob_start();
        $engine = new engine($dataflow);
        $this->expectExceptionMessage(get_string('writer_stream:failed_to_open_stream',
            'tool_dataflows', $engine->resolve_path($path)));
        try {
            $engine->execute();
        } finally {
            ob_get_clean();
        }
    }

    /**
     * Test csv with custom headers configured
     */
    public function test_csv_with_overwrite_headers_configured() {
        [$dataflow, $steps, $inputpath, $outputpath] = $this->create_dataflow();
        $headers = ['first', 'second', 'third'];

        $reader = $steps[$dataflow->steps->reader->id];
        $reader->config = Yaml::dump([
            'path' => $inputpath,
            'headers' => implode(',', $headers),
            'overwriteheaders' => '1',
            'delimiter' => ',',
            'maxlinelength' => '1000',
        ]);

        // Create test input file.
        $data = [
            ['apple', 'berry', 'cucumber'],
            [4, 5, 6],
            [7, 8, 9],
        ];
        $content = '';
        foreach ($data as $row) {
            $content .= implode(',', $row);
            $content .= PHP_EOL;
        }
        file_put_contents($inputpath, $content);

        // Expected content, which has headers but first line of content is removed.
        array_shift($data);
        $expected = implode(',', $headers) . PHP_EOL;
        foreach ($data as $row) {
            $expected .= implode(',', $row);
            $expected .= PHP_EOL;
        }

        // Execute.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $output = file_get_contents($outputpath);

        // Expected output; Add a header line.
        $this->assertEquals($expected, $output);
    }

    /**
     * Dataflow creation helper function
     *
     * @return  array dataflow and steps
     */
    public function create_dataflow(): array {
        // Create the dataflow.
        $dataflow = new dataflow();
        $dataflow->name = 'testflow';
        $dataflow->enabled = true;
        $dataflow->save();

        $steps = [];

        $inputpath = tempnam('', 'tool_dataflows');
        $reader = new step();
        $reader->name = 'reader';
        $reader->type = reader_csv::class;
        $reader->config = Yaml::dump([
            'path' => $inputpath,
            'headers' => '',
            'delimiter' => ',',
            'maxlinelength' => '1000',
        ]);
        $dataflow->add_step($reader);
        $steps[$reader->id] = $reader;

        $outputpath = tempnam('', 'tool_dataflows');
        $writer = new step();
        $writer->name = 'stream-writer';
        $writer->type = 'tool_dataflows\local\step\writer_stream';
        $writer->config = Yaml::dump([
            'format' => 'csv',
            'streamname' => $outputpath,
        ]);
        $writer->depends_on([$reader]);
        $dataflow->add_step($writer);

        $this->reader = $reader;
        $this->writer = $writer;

        return [$dataflow, $steps, $inputpath, $outputpath];
    }
}
