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
use tool_dataflows\dataflow;
use tool_dataflows\local\execution\engine;
use tool_dataflows\step;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/local/execution/array_in_type.php');

/**
 * Unit tests for writer_stream.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_stream_writer_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test the stream writer with json formatting.
     *
     * @dataProvider test_writer_data_provider
     * @param $inputdata
     */
    public function test_writer_json($inputdata, $isdryrun) {
        // Crate the dataflow.
        $dataflow = new dataflow();
        $dataflow->name = 'array-to-stream';
        $dataflow->save();

        $reader = new step();
        $reader->name = 'array-reader';
        $reader->type = 'tool_dataflows\local\execution\array_in_type';
        $dataflow->add_step($reader);

        $tmpfilename = tempnam('', 'tool_dataflows_');

        $writer = new step();
        $writer->name = 'stream-writer';
        $writer->type = 'tool_dataflows\local\step\writer_stream';
        $writer->config = Yaml::dump(['format' => 'json', 'streamname' => $tmpfilename]);

        $writer->depends_on([$reader]);
        $dataflow->add_step($writer);

        \tool_dataflows\local\execution\array_in_type::$source = $inputdata;

        // Execute.
        $engine = new engine($dataflow, $isdryrun);
        $engine->execute();

        if ($isdryrun) {
            // Dry runs should produce no output.
            $resultdata = file_get_contents($tmpfilename);
            $this->assertTrue(empty($resultdata));
        } else {
            $resultdata = json_decode(file_get_contents($tmpfilename), true);
            $this->assertEquals($inputdata, $resultdata);
        }

        $this->assertEquals(engine::STATUS_FINALISED, $engine->status);
    }

    /**
     * Data provider for tests.
     *
     * @return array
     */
    public function test_writer_data_provider(): array {
        return [
            [[['a' => 1, 'b' => 2, 'c' => 3], ['a' => 4, 'b' => 5, 'c' => 6]], false],
            [[['a' => 1], ['b' => 5], ['c' => 6]], true],
            [[['a' => 1], ['b' => 5], ['c' => 7]], false],
        ];
    }
}
