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
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
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

        set_config('permitted_dirs', '/tmp', 'tool_dataflows');

        $this->dataflow = $this->create_dataflow();
        $this->set_initial_test_data();
    }

    /**
     * Test validate_config().
     *
     * @covers \tool_dataflows\local\step\reader_json::validate_config
     */
    public function test_validate_config() {
        // Test valid configuration.
        $tmpfile = tempnam('', 'tmpjsonfile');
        $this->assertFileExists($tmpfile);

        $config = (object) ['pathtojson' => $tmpfile];
        $reader = new reader_json();
        $this->assertTrue($reader->validate_config($config));

        // Test missing JSON value.
        $config = (object) ['other' => $tmpfile];
        $result = $reader->validate_config($config);
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('config_pathtojson', $result);
        $this->assertEquals(get_string('config_field_missing', 'tool_dataflows', 'pathtojson'), $result['config_pathtojson']);
    }

    /**
     * Dataflow creation helper function
     *
     * Sets up the json reader, a writer and step configuration can be applied later per test
     *
     * @return  dataflow dataflow
     */
    public function create_dataflow() {
        // Create the dataflow.
        $dataflow = new dataflow();
        $dataflow->name = 'testflow';
        $dataflow->enabled = true;
        $dataflow->save();

        $steps = [];

        $this->inputpath = tempnam('', 'tool_dataflows');
        $reader = new step();
        $reader->name = 'reader';
        $reader->type = 'tool_dataflows\local\step\reader_json';
        $reader->config = Yaml::dump(['pathtojson' => $this->inputpath]);
        $dataflow->add_step($reader);
        $steps[$reader->id] = $reader;

        $this->outputpath = tempnam('', 'tool_dataflows');
        $writer = new step();
        $writer->name = 'stream-writer';
        $writer->type = 'tool_dataflows\local\step\writer_stream';
        $writer->config = Yaml::dump([
            'format' => 'json',
            'streamname' => $this->outputpath,
        ]);
        $writer->depends_on([$reader]);
        $dataflow->add_step($writer);

        $this->reader = $reader;
        $this->writer = $writer;

        return $dataflow;
    }

    /**
     * Creates the base contents of the reader file
     */
    public function set_initial_test_data() {
        $users = [
            (object) ['id' => '2',  'userdetails' => (object) ['firstname' => 'John', 'lastname' => 'Doe', 'name' => 'Name2']],
            (object) ['id' => '1',  'userdetails' => (object) ['firstname' => 'Bob', 'lastname' => 'Smith', 'name' => 'Name1']],
            (object) ['id' => '3',  'userdetails' => (object) ['firstname' => 'Foo', 'lastname' => 'Bar', 'name' => 'Name3']],
        ];

        $json = json_encode((object) [
            'data' => (object) [
                'list' => ['users' => $users],
            ],
            'modified' => [1654058940],
            'errors' => [],
        ]);

        // Write initial contents to file for reader to pick up.
        $stream = fopen($this->inputpath, 'w');
        fwrite($stream, $json);
        fclose($stream);
    }

    /**
     * Tests an unsorted array to ensure the output is correct
     *
     * @covers \tool_dataflows\local\step\reader_json
     */
    public function test_reader_json_unsorted_array() {
        [$dataflow, $reader, $writer] = [$this->dataflow, $this->reader, $this->writer];

        // Test unsorted array.
        $users = [
            (object) ['id' => '2',  'userdetails' => (object) ['firstname' => 'John', 'lastname' => 'Doe', 'name' => 'Name2']],
            (object) ['id' => '1',  'userdetails' => (object) ['firstname' => 'Bob', 'lastname' => 'Smith', 'name' => 'Name1']],
            (object) ['id' => '3',  'userdetails' => (object) ['firstname' => 'Foo', 'lastname' => 'Bar', 'name' => 'Name3']],
        ];

        $json = json_encode((object) [
            'data' => (object) [
                'list' => ['users' => $users],
            ],
            'modified' => [1654058940],
            'errors' => [],
        ]);

        // Write initial contents to file for reader to pick up.
        $stream = fopen($this->inputpath, 'w');
        fwrite($stream, $json);
        fclose($stream);

        // Set the config data via a YAML config string.
        $reader->config = Yaml::dump([
            'pathtojson' => $this->inputpath,
            'arrayexpression' => 'data.list.users',
            'arraysortexpression' => '',
        ]);
        $dataflow->add_step($reader);

        $writer->config = Yaml::dump(['format' => 'json', 'streamname' => $this->outputpath]);

        // Execute.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $resultdata = json_decode(file_get_contents($this->outputpath));
        $this->assertEquals($users, $resultdata);
    }

    /**
     * Tests an unsorted array to ensure the output is correct
     *
     * By default, the sort order should be ascending.
     *
     * @covers \tool_dataflows\local\step\reader_json::get_sort_by_config_value
     * @covers \tool_dataflows\local\step\reader_json::get_sort_order_direction
     */
    public function test_reader_json_sort_function() {
        [$dataflow, $reader, $writer] = [$this->dataflow, $this->reader, $this->writer];

        // Test sort function.

        // Set the new config data via a YAML config string.
        $reader->config = Yaml::dump([
            'pathtojson' => $this->inputpath,
            'arrayexpression' => 'data.list.users',
            'arraysortexpression' => 'userdetails.firstname',
        ]);

        $sorteduserarray = [
            (object) ['id' => '1',  'userdetails' => (object) ['firstname' => 'Bob', 'lastname' => 'Smith', 'name' => 'Name1']],
            (object) ['id' => '3',  'userdetails' => (object) ['firstname' => 'Foo', 'lastname' => 'Bar', 'name' => 'Name3']],
            (object) ['id' => '2',  'userdetails' => (object) ['firstname' => 'John', 'lastname' => 'Doe', 'name' => 'Name2']],
        ];

        $writer->config = Yaml::dump([
            'format' => 'json',
            'streamname' => $this->outputpath,
        ]);

        // Execute.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $sortedresultdata = json_decode(file_get_contents($this->outputpath));
        $this->assertEquals($sorteduserarray, $sortedresultdata);
    }

    /**
     * Tests to see if the json reader can iterate over object keys, if the target is stored in some form of a hash map
     *
     * @covers \tool_dataflows\local\step\reader_json
     */
    public function test_reader_json_looping_over_object_keys() {
        [$dataflow, $reader, $writer] = [$this->dataflow, $this->reader, $this->writer];

        // Test looping over object keys.
        $singleuser = '{"firstname": "Bob", "lastname": "Smith", "name": "Name"}';

        $tmpinputuser = tempnam('', 'jsonoutputuser');
        $stream = fopen($tmpinputuser, 'w');
        fwrite($stream, $singleuser);
        fclose($stream);

        // Set the new config data via a YAML config string.
        $reader->config = Yaml::dump([
            'pathtojson' => $tmpinputuser,
            'arrayexpression' => '',
            'arraysortexpression' => '',
        ]);

        $writer->config = Yaml::dump([
            'format' => 'json',
            'streamname' => $this->outputpath,
        ]);

        // Execute.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $this->assertEquals(json_decode(file_get_contents($this->outputpath)), ['Bob', 'Smith', 'Name']);

        $this->assertEquals(engine::STATUS_FINALISED, $engine->status);
    }

    /**
     * Tests to ensure the sort order works as expected
     *
     * @covers \tool_dataflows\local\step\reader_json::get_sort_by_config_value
     * @covers \tool_dataflows\local\step\reader_json::get_sort_order_direction
     */
    public function test_json_sort_order() {
        [$dataflow, $reader, $writer] = [$this->dataflow, $this->reader, $this->writer];

        // Test sort function.

        // Set the new config data via a YAML config string.
        $reader->config = Yaml::dump([
            'pathtojson' => $this->inputpath,
            'arrayexpression' => 'data.list.users',
            'arraysortexpression' => 'userdetails.firstname',
            'sortorder' => 'desc',
        ]);

        $reversesorteduserarray = [
            (object) ['id' => '2',  'userdetails' => (object) ['firstname' => 'John', 'lastname' => 'Doe', 'name' => 'Name2']],
            (object) ['id' => '3',  'userdetails' => (object) ['firstname' => 'Foo', 'lastname' => 'Bar', 'name' => 'Name3']],
            (object) ['id' => '1',  'userdetails' => (object) ['firstname' => 'Bob', 'lastname' => 'Smith', 'name' => 'Name1']],
        ];

        $writer->config = Yaml::dump([
            'format' => 'json',
            'streamname' => $this->outputpath,
        ]);

        // Execute.
        ob_start();
        $engine = new engine($dataflow);
        $engine->execute();
        ob_get_clean();

        $sortedresultdata = json_decode(file_get_contents($this->outputpath));
        $this->assertEquals($reversesorteduserarray, $sortedresultdata);
    }
}
