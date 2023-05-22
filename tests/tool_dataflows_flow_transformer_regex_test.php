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
 * @author    Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_flow_transformer_regex_test extends \advanced_testcase {


    /** Test data. */
    const TEST_DATA = [
        [ 'test' => 'hello world' ],
        [ 'test' => 'hello earth' ],
        [ 'test' => '' ],
    ];

    /** Expression config */
    const CONFIG = [
        'field' => '${{steps.reader.record.test}}',
        'pattern' => '/world/',
    ];

    /** To be expected. */
    const EXPECTED = [
        [ 'test' => 'hello world', 'regex' => 'world' ],
        [ 'test' => 'hello earth', 'regex' => null ],
        [ 'test' => '', 'regex' => null ],
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
     * Tests the regex step.
     *
     * @dataProvider regex_provider
     * @covers \tool_dataflows\local\step\flow_transformer_regex
     * @param array $data
     * @param array $config
     * @param array $expected
     */
    public function test_regex(array $data, array $config, array $expected) {

        // Perform the test.
        set_config('permitted_dirs', $this->basedir, 'tool_dataflows');
        $dataflow = $this->make_dataflow($data, $config);

        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $result = json_decode(file_get_contents($this->basedir . self::OUTPUT_FILE_NAME), true);
        $this->assertEquals($expected, $result);
    }

    /**
     * Provider for test_regex()
     *
     * @return array[]
     */
    public function regex_provider(): array {
        return [
            [ self::TEST_DATA, self::CONFIG, self::EXPECTED ],
            [
                [['test' => '1,2,3,4,5,6,7,8,9']],
                [
                    'field' => '${{steps.reader.record.test}}',
                    'pattern' => '/4\,5\,6/',
                ],
                [['test' => '1,2,3,4,5,6,7,8,9', 'regex' => '4,5,6']],
            ],
        ];
    }

    /**
     * Creates a dataflow to test.
     *
     * @param array $data
     * @param array $config
     * @return dataflow
     */
    private function make_dataflow(array $data, array $config): dataflow {
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
            'content' => json_encode($data),
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

        $regex = new step();
        $regex->name = 'regex';
        $regex->type = $namespace . 'flow_transformer_regex';
        $regex->depends_on([$reader]);
        $regex->config = Yaml::dump($config);
        $dataflow->add_step($regex);

        $writer = new step();
        $writer->name = 'writer';
        $writer->type = $namespace . 'writer_stream';
        $writer->depends_on([$regex]);
        $writer->config = Yaml::dump([
            'streamname' => $this->basedir . self::OUTPUT_FILE_NAME,
            'format' => 'json',
        ]);
        $dataflow->add_step($writer);

        return $dataflow;
    }
}
