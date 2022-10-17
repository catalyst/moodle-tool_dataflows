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
use tool_dataflows\local\step\connector_hash_file;
use tool_dataflows\local\execution\engine;

/**
 * Unit tests for flow_hash_file step.
 *
 * @covers \tool_dataflows\local\step\flow_hash_file
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataflows_flow_hash_file_test extends \advanced_testcase {

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Dataflow creation helper function
     *
     * @return  array of the resulting dataflow and steps in the format [dataflow, steps]
     */
    public function create_dataflow() {
        $dataflow = new dataflow();
        $dataflow->name = 'switch step test';
        $dataflow->enabled = true;
        $dataflow->save();

        $steps = [];

        // Hash file step.
        $hashfile = new step();
        $hashfile->name = 'hashfile';
        $hashfile->type = connector_hash_file::class;
        $allalgorithms = hash_algos();
        $anyalgo = reset($allalgorithms);
        $hashfile->config = Yaml::dump(['path' => '', 'algorithm' => $anyalgo]);
        $dataflow->add_step($hashfile);
        $steps[$hashfile->id] = $hashfile;

        return [$dataflow, $steps];
    }

    /**
     * Prepares the files and their matching hashes. To ensure it works consistently, this is a hardcoded list.
     *
     * @return  array
     */
    public function hash_file_provider() {
        $combinations = [];
        $content = 'Hello World';

        // Loops through all the available algorithms, records the hash and writes the content to a file.
        foreach (hash_algos() as $algorithm) {
            $hash = hash($algorithm, $content);

            // Create tempfile.
            $tempfile = tempnam('', 'tool_dataflows');
            file_put_contents($tempfile, $content);

            $combinations[] = [
                $tempfile,
                $hash,
                $algorithm,
            ];
        }

        return $combinations;
    }

    /**
     * Test the hashes produced by the hash_file step matches what is expected
     *
     * @param string $filepath
     * @param string $expectedhash
     * @param string $algorithm
     * @dataProvider hash_file_provider
     */
    public function test_hashing_a_file_results_in_expected_hashes(string $filepath, string $expectedhash, string $algorithm) {
        [$dataflow, $steps] = $this->create_dataflow();
        $hashfile = reset($steps);

        // Needed here to ensure the path is 'allowed'.
        set_config('permitted_dirs', $filepath, 'tool_dataflows');

        $hashfile->config = Yaml::dump(['path' => $filepath, 'algorithm' => $algorithm]);

        $isdryrun = false;
        $this->assertTrue($dataflow->validate_dataflow());

        ob_start();
        $engine = new engine($dataflow, $isdryrun);
        $engine->execute();
        ob_get_clean();

        // Check and ensure hashes match.
        $this->assertEquals($expectedhash, $dataflow->get_variables_root()->get('steps.hashfile.hash'));
    }
}
