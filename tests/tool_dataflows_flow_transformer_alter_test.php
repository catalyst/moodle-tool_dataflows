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
class tool_dataflows_flow_transformer_alter_test extends \advanced_testcase {


    /** Test data. */
    const TEST_DATA1 = [
        [ 'a' => '1', 'b' => '1' ],
        [ 'a' => '5', 'b' => '9' ],
        [ 'a' => '8', 'b' => '3' ],
        [ 'a' => '0', 'b' => '2' ],
        [ 'a' => '0', 'b' => '1' ],
        [ 'a' => '0', 'b' => '6' ],
    ];

    /** Expression config */
    const EXPRESSIONS1 = [
        'a' => 'o',
        'c' => '${{record.a}} ${{record.b}}',
        'd' => '${{record.a + record.b}}',
    ];

    /** To be expected. */
    const EXPECTED1 = [
        [ 'a' => 'o', 'b' => '1', 'c' => '1 1', 'd' => 2 ],
        [ 'a' => 'o', 'b' => '9', 'c' => '5 9', 'd' => 14 ],
        [ 'a' => 'o', 'b' => '3', 'c' => '8 3', 'd' => 11 ],
        [ 'a' => 'o', 'b' => '2', 'c' => '0 2', 'd' => 2 ],
        [ 'a' => 'o', 'b' => '1', 'c' => '0 1', 'd' => 1 ],
        [ 'a' => 'o', 'b' => '6', 'c' => '0 6', 'd' => 6 ],
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
     * Tests the alteration step.
     *
     * @dataProvider alter_provider
     * @covers \tool_dataflows\local\step\flow_transformer_alter
     * @param array $data
     * @param array $exprs
     * @param array $expected
     */
    public function test_alter(array $data, array $exprs, array $expected) {

        // Perform the test.
        set_config('permitted_dirs', $this->basedir, 'tool_dataflows');
        $dataflow = $this->make_dataflow($data, $exprs);

        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $result = json_decode(file_get_contents($this->basedir . self::OUTPUT_FILE_NAME), true);
        $this->assertEquals($expected, $result);
    }

    /**
     * Provider for test_alter()
     *
     * @return array[]
     */
    public function alter_provider(): array {
        return [
            [ self::TEST_DATA1, self::EXPRESSIONS1, self::EXPECTED1 ],
        ];
    }

    /**
     * Creates a dataflow to test.
     *
     * @param array $data
     * @param array $exprs
     * @return dataflow
     */
    private function make_dataflow(array $data, array $exprs): dataflow {
        $namespace = '\\tool_dataflows\\local\\step\\';

        $dataflow = new dataflow();
        $dataflow->enabled = true;
        $dataflow->name = 'dataflow';
        $dataflow->save();

        $setupconnector = new step();
        $setupconnector->name = 'setup';
        $setupconnector->type = $namespace . 'connector_file_put_content';
        $setupconnector->config = Yaml::dump([
            'path' => $this->basedir . self::INPUT_FILE_NAME,
            'content' => json_encode($data),
        ]);
        $dataflow->add_step($setupconnector);

        $reader = new step();
        $reader->name = 'reader';
        $reader->type = $namespace . 'reader_json';
        $reader->depends_on([$setupconnector]);
        $reader->config = Yaml::dump([
            'pathtojson' => $this->basedir . self::INPUT_FILE_NAME,
            'arrayexpression' => '',
            'arraysortexpression' => '',
            'sortorder' => '',
        ]);
        $dataflow->add_step($reader);

        $alter = new step();
        $alter->name = 'filter';
        $alter->type = $namespace . 'flow_transformer_alter';
        $alter->depends_on([$reader]);
        $alter->config = Yaml::dump([
            'expressions' => $exprs
        ]);
        $dataflow->add_step($alter);

        $writer = new step();
        $writer->name = 'writer';
        $writer->type = $namespace . 'writer_stream';
        $writer->depends_on([$alter]);
        $writer->config = Yaml::dump([
            'streamname' => $this->basedir . self::OUTPUT_FILE_NAME,
            'format' => 'json',
        ]);
        $dataflow->add_step($writer);

        return $dataflow;
    }
}
