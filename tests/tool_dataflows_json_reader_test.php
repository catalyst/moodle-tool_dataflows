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
use tool_dataflows\local\step\reader_json;

defined('MOODLE_INTERNAL') || die();

// Include lib.php functions that aren't included automatically for Moodle 37- and below.
require_once(dirname(__FILE__) . '/../lib.php');

/**
 * Unit test for the JSON reader step.
 *
 * @package   tool_dataflows
 * @author    Peter Sistrom <petersistrom@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_json_reader_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test validate_config().
     *
     * @covers \tool_dataflows\local\step\reader_json::validate_config
     * @throws \coding_exception
     */
    public function test_validate_config() {
        // Test valid configuration.
        $config = (object)['json' => '{key:{value:Array[]}}'];
        $reader = new reader_json();
        $this->assertTrue($reader->validate_config($config));

        // Test missing JSON value.
        $config = (object)['other' => '{key:{value:Array[]}}'];
        $result = $reader->validate_config($config);
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('config_json', $result);
        $this->assertEquals(get_string('config_field_missing', 'tool_dataflows', 'json'), $result['config_json']);
    }

    /**
     * Test the JSON reader throughout a engine lifecycle/run
     *
     * @covers \tool_dataflows\local\step\reader_json
     */
    public function test_reader_json() {

        // Create the dataflow.
        $dataflow = new dataflow();
        $dataflow->name = 'testflow';
        $dataflow->enabled = true;
        $dataflow->save();

        $reader = new step();
        $reader->name = 'reader';
        $reader->type = 'tool_dataflows\local\step\reader_json';

        $json = json_encode((object) [
            'data' => (object) [
                'list' => [
                    'users' => [
                        [ "id" => "2",  "userdetails" => ["firstname" => "John", "lastname" => "Doe", "name" => "Name2"]],
                        [ "id" => "1",  "userdetails" => ["firstname" => "Bob", "lastname" => "Smith", "name" => "Name1"]],
                        [ "id" => "3",  "userdetails" => ["firstname" => "Foo", "lastname" => "Bar", "name" => "Name3"]]
                    ],
                ]
            ],
            'modified' => [1654058940],
            'errors' => [],
        ]);

        // Test unsorted array.
        $expecteduserarray = json_decode('[
            { "id": "2",  "userdetails": {"firstname": "John", "lastname": "Doe", "name": "Name2"}},
            { "id": "1",  "userdetails": {"firstname": "Bob", "lastname": "Smith", "name": "Name1"}},
            { "id": "3",  "userdetails": {"firstname": "Foo", "lastname": "Bar", "name": "Name3"}}
        ]');

        $tmpinput = tempnam('', 'jsoninput');
        $stream = fopen($tmpinput, 'w');
        fwrite($stream, $json);
        fclose($stream);

        // Set the config data via a YAML config string.
        $reader->config = Yaml::dump([
            'json' => $tmpinput,
            'arrayexpression' => 'data.list.users',
            'arraysortexpression' => '',
        ]);
        $dataflow->add_step($reader);

        $tmpoutput = tempnam('', 'jsonoutput');

        $writer = new step();
        $writer->name = 'stream-writer';
        $writer->type = 'tool_dataflows\local\step\writer_stream';
        $writer->config = Yaml::dump(['format' => 'json', 'streamname' => $tmpoutput]);

        $writer->depends_on([$reader]);
        $dataflow->add_step($writer);

        // Execute.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $resultdata = json_decode(file_get_contents($tmpoutput));
        $this->assertEquals($expecteduserarray, $resultdata);

        // Test sort function

        // Set the new config data via a YAML config string.
        $reader->config = Yaml::dump([
            'json' => $tmpinput,
            'arrayexpression' => 'data.list.users',
            'arraysortexpression' => 'userdetails.firstname',
        ]);

        $expecteduserarray = json_decode('[
            { "id": "1",  "userdetails": {"firstname": "Bob", "lastname": "Smith", "name": "Name1"}},
            { "id": "3",  "userdetails": {"firstname": "Foo", "lastname": "Bar", "name": "Name3"}},
            { "id": "2",  "userdetails": {"firstname": "John", "lastname": "Doe", "name": "Name2"}}
        ]');

        $tmpoutput2 = tempnam('', 'jsonoutput2');
        $writer->config = Yaml::dump(['format' => 'json', 'streamname' => $tmpoutput2]);

        // Execute.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $resultdata = json_decode(file_get_contents($tmpoutput2));
        $this->assertEquals($expecteduserarray, $resultdata);

        $this->assertEquals(engine::STATUS_FINALISED, $engine->status);
    }
}
