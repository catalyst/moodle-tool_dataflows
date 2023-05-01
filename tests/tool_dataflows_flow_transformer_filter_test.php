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

/**
 * Unit test for append file step.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_flow_transformer_filter_test extends \advanced_testcase {
    /** Test data. */
    const TEST_DATA = [
        [ 'a' => '1', 'b' => 'a_1_b' ],
        [ 'a' => '5', 'b' => 'a_9_b' ],
        [ 'a' => 'x', 'b' => 'a_3_b' ],
        [ 'a' => '0', 'b' => 'a_2_b' ],
        [ 'a' => '0', 'b' => 'a_1_b' ],
        [ 'a' => '0', 'b' => 'a_6_b' ],
    ];

    /** Input file name. */
    const INPUT_FILE_NAME = 'input.json';

    /** Output file name. */
    const OUTPUT_FILE_NAME = 'output.json';

    /** @var string  Base directory. */
    private $basedir;

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // Set up the file to be pumped through the flow loop.
        $basedir = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        if (file_exists($basedir . self::OUTPUT_FILE_NAME)) {
            unlink($basedir . self::OUTPUT_FILE_NAME);
        }

        $this->basedir = $basedir;
    }

    /**
     * Cleanup after each test.
     */
    protected function tearDown(): void {
        $this->basedir = null;
    }


    /**
     * Tests appending to many files, declared 1:1.
     *
     * @dataProvider filter_provider
     * @covers \tool_dataflows\local\step\flow_transformer_filter
     * @param string $expr
     * @param array $expected
     */
    public function test_filter(string $expr, array $expected) {

        // Perform the test.
        set_config('permitted_dirs', $this->basedir, 'tool_dataflows');
        $dataflow = $this->make_dataflow($expr);

        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $result = json_decode(file_get_contents($this->basedir . self::OUTPUT_FILE_NAME), true);

        $this->assertEquals($expected, $result);
    }

    /**
     * Provider function for test_filter().
     *
     * @return array[]
     */
    public function filter_provider() {
        return [
            [ 'record.a == 1', [
                [ 'a' => '1', 'b' => 'a_1_b' ],
            ]],
            [ "record.a == '0'", [
                [ 'a' => '0', 'b' => 'a_2_b' ],
                [ 'a' => '0', 'b' => 'a_1_b' ],
                [ 'a' => '0', 'b' => 'a_6_b' ],
            ]],
            [ "record.b >= 'a_3_b'", [
                [ 'a' => '5', 'b' => 'a_9_b' ],
                [ 'a' => 'x', 'b' => 'a_3_b' ],
                [ 'a' => '0', 'b' => 'a_6_b' ],
            ]],
        ];
    }

    /**
     * Creates a dataflow to test.
     *
     * @param string $expr Expression to add to filter step.
     * @return dataflow
     */
    private function make_dataflow(string $expr): dataflow {
        $namespace = '\\tool_dataflows\\local\\step\\';

        $dataflow = new dataflow();
        $dataflow->enabled = true;
        $dataflow->name = 'dataflow';
        $dataflow->save();

        $setup = new step();
        $setup->name = 'setup';
        $setup->type = $namespace . 'connector_file_put_content';
        $setup->config = Yaml::dump([
            'path' => $this->basedir . self::INPUT_FILE_NAME,
            'content' => json_encode(self::TEST_DATA),
        ]);
        $dataflow->add_step($setup);

        $reader = new step();
        $reader->name = 'reader';
        $reader->type = $namespace . 'reader_json';
        $reader->depends_on([$setup]);
        $reader->config = Yaml::dump([
            'pathtojson' => $this->basedir . self::INPUT_FILE_NAME,
            'arrayexpression' => '',
            'arraysortexpression' => '',
            'sortorder' => '',
        ]);
        $dataflow->add_step($reader);

        $filter = new step();
        $filter->name = 'filter';
        $filter->type = $namespace . 'flow_transformer_filter';
        $filter->depends_on([$reader]);
        $filter->config = Yaml::dump([
            'filter' => $expr
        ]);
        $dataflow->add_step($filter);

        $writer = new step();
        $writer->name = 'writer';
        $writer->type = $namespace . 'writer_stream';
        $writer->depends_on([$filter]);
        $writer->config = Yaml::dump([
            'streamname' => $this->basedir . self::OUTPUT_FILE_NAME,
            'format' => 'json',
        ]);
        $dataflow->add_step($writer);

        return $dataflow;
    }
}
