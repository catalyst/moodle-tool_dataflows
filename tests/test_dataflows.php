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

use tool_dataflows\local\execution\array_in_type;
use tool_dataflows\local\execution\array_out_type;
use tool_dataflows\local\execution\flow_callback_step;
use tool_dataflows\local\execution\test_step;

defined('MOODLE_INTERNAL') || die();

// These are needed. Files will not be automatically included.
require_once(__DIR__ . '/local/execution/array_in_type.php');
require_once(__DIR__ . '/local/execution/array_out_type.php');
require_once(__DIR__ . '/local/execution/flow_callback_step.php');
require_once(__DIR__ . '/local/execution/test_step.php');

/**
 * Stock dataflows to be used in testing.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_dataflows {
    /**
     * Create a two step reader-writer using the static array in and array out test steps.
     *
     * @return array [<dataflow>, <steps>]
     */
    public static function array_in_array_out(): array {
        return self::sequence(['reader' => array_in_type::class, 'writer' => array_out_type::class]);
    }

    /**
     * Create a three step reader-callback-writer dataflow.
     *
     * @param string $readertype
     * @param string $writertype
     * @param callable $fn
     * @return array [<dataflow>, <steps>]
     */
    public static function reader_callback_writer(string $readertype, string $writertype, callable $fn): array {
        [$dataflow, $steps] = self::sequence([
            'reader' => $readertype,
            'callback' => flow_callback_step::class,
            'writer' => $writertype,
        ]);

        $steps['callback']->dodgyvars['callback'] = $fn;

        return [$dataflow, $steps];
    }

    /**
     * Create a dataflow from a sequence of steps.
     *
     * @param array $sequence Ordered list of step types indexed by name.
     * @return array
     */
    public static function sequence(array $sequence) {
        $dataflow = new dataflow();
        $dataflow->name = 'sequence';
        $dataflow->enabled = true;
        $dataflow->save();

        $steps = [];
        $prev = null;
        foreach ($sequence as $name => $type) {
            $step = new test_step();
            $step->name = $name;
            $step->type = $type;
            if ($prev) {
                $step->depends_on([$prev]);
            }
            $dataflow->add_step($step);
            $steps[$name] = $step;
            $prev = $step;
        }
        return [$dataflow, $steps];
    }
}
