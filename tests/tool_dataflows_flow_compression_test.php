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
use tool_dataflows\local\step\flow_compression;
use tool_dataflows\local\step\reader_directory_file_list;

/**
 * Unit test for the compression flow step.
 *
 * @package   tool_dataflows
 * @author    Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \tool_dataflows\local\step\connector_compression
 */
class tool_dataflows_flow_compression_test extends \advanced_testcase {
    /** @var string directory with files in it to read from for flow step */
    private $readdir;

    /** @var string directory where the compressed/decompressed files are outputted to */
    private $outdir;

    /**
     * Sets up tests
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $basedir = make_unique_writable_directory(make_temp_directory('tool_dataflows'));
        $this->readdir = $basedir . '/filesin';
        $this->outdir = $basedir . '/filesout';

        mkdir($this->readdir);
        mkdir($this->outdir);

        // Generate some files to read from in the read directory.
        file_put_contents($this->readdir . '/test1.txt', 'test1234');
        file_put_contents($this->readdir . '/test2.txt', 'test1234');

        set_config('permitted_dirs', $basedir, 'tool_dataflows');
    }

    protected function tearDown(): void {
        $this->readdir = null;
        $this->outdir = null;
    }

    /**
     * Creates a test dataflow
     *
     * @param string $method
     */
    private function create_test_dataflow(string $method) {
        $dataflow = new dataflow();
        $dataflow->name = 'compression-connector-test';
        $dataflow->save();

        $reader = new step();
        $reader->config = Yaml::dump([
            'directory' => $this->readdir,
            'pattern' => "*",
            'returnvalue' => 'basename',
            'sort' => 'alpha',
            'offset' => '0',
            'limit' => '0',
        ]);
        $reader->name = 'reader';
        $reader->type = reader_directory_file_list::class;
        $dataflow->add_step($reader);

        $compress = new step();
        $compress->config = Yaml::dump([
            'from' => $this->readdir . '/${{record.filename}}',
            'to' => $this->outdir . '/${{record.filename}}-out.gz',
            'method' => $method,
            'command' => 'compress',
        ]);
        $compress->depends_on([$reader]);
        $compress->name = 'compress';
        $compress->type = flow_compression::class;
        $dataflow->add_step($compress);

        $decompress = new step();
        $decompress->config = Yaml::dump([
            'from' => $this->outdir . '/${{record.filename}}-out.gz',
            'to' => $this->outdir . '/${{record.filename}}-out-decompressed.txt',
            'method' => $method,
            'command' => 'decompress',
        ]);
        $decompress->depends_on([$compress]);
        $decompress->name = 'decompress';
        $decompress->type = flow_compression::class;
        $dataflow->add_step($decompress);

        return $dataflow;
    }

    /**
     * Test compression
     */
    public function test_gzip_compression_decompression() {
        // Ensure gzip is installed, otherwise we should skip the test.
        if (!is_executable(get_config('tool_dataflows', 'gzip_exec_path'))) {
            $this->markTestSkipped('gzip is not installed');
            return;
        }

        // We have two test files, but also . and .. exist so we need to account for them.
        $this->assertCount(2 + 2, scandir($this->readdir));

        $dataflow = $this->create_test_dataflow('gzip');

        ob_start();
        $engine = new engine($dataflow, false, false);
        $engine->execute();
        ob_get_clean();

        // Check the files were outputted correctly.
        // Note we check the mime type rather than the file extension
        // since the extension may be misleading.
        $outdircontenttypes = array_map(function ($filename) {
            $fullpath = $this->outdir . '/' . $filename;
            return mime_content_type($fullpath);
        }, scandir($this->outdir));

        $this->assertCount(2, array_filter($outdircontenttypes, function ($type) {
            return $type == 'application/gzip' || $type == 'application/x-gzip';
        }));

        $this->assertCount(2, array_filter($outdircontenttypes, function ($type) {
            return $type == 'text/plain';
        }));
    }
}

