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

use tool_dataflows\local\execution\flow_callback_step;
use tool_dataflows\local\execution\test_step;

defined('MOODLE_INTERNAL') || die();

// These are needed. Files will not be automatically included.
require_once(__DIR__ . '/../local/execution/array_in_type.php');
require_once(__DIR__ . '/../local/execution/array_out_type.php');
require_once(__DIR__ . '/../local/execution/flow_callback_step.php');

/**
 * Stock dataflows to be used in testing.
 *
 * @package   <insert>
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_dataflows {
    /**
     * Create a two step reader-writer using the static array in and array out test steps.
     *
     * @returns array [<dataflow>, <steps]
     */
    public static function array_in_array_out(): array {
        $dataflow = new dataflow();
        $dataflow->name = 'array-in-array-out';
        $dataflow->enabled = true;
        $dataflow->save();

        $reader = new step();
        $reader->name = 'reader';
        $reader->type = 'tool_dataflows\local\execution\array_in_type';
        $dataflow->add_step($reader);

        $writer = new step();
        $writer->name = 'writer';
        $writer->type = 'tool_dataflows\local\execution\array_out_type';

        $writer->depends_on([$reader]);
        $dataflow->add_step($writer);
        
        return [$dataflow, ['reader' => $reader, 'writer' => $writer]];
    }

    /**
     * Create a three step reader-callback-writer dataflow.
     *
     * @param string $readertype
     * @param string $writertype
     * @return array [<dataflow>, <steps]
     */
    public static function reader_callback_writer(string $readertype, string $writertype, callable $fn): array {
        $dataflow = new dataflow();
        $dataflow->name = 'reader-callback-writer';
        $dataflow->enabled = true;
        $dataflow->save();

        $reader = new step();
        $reader->name = 'reader';
        $reader->type = $readertype;
        $dataflow->add_step($reader);

        $callback = new test_step();
        $callback->name = 'callback';
        $callback->type = flow_callback_step::class;
        $callback->dodgyvars['callback'] = $fn;
        $callback->depends_on([$reader]);
        $dataflow->add_step($callback);

        $writer = new step();
        $writer->name = 'writer';
        $writer->type = $writertype;
        $writer->depends_on([$callback]);
        $dataflow->add_step($writer);

        return [$dataflow, ['reader' => $reader, 'callback' => $callback, 'writer' => $writer]];
    }
}

