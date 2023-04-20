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

namespace tool_dataflows\local\step;

use tool_dataflows\helper;
use tool_dataflows\local\execution\iterators\iterator;
use tool_dataflows\local\execution\iterators\dataflow_iterator;

/**
 * Gets the files in a directory, and make the list a reader source.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reader_directory_file_list extends reader_step {
    use directory_file_list_trait;

    /**
     * Get the iterator for the step, based on configurations.
     *
     * @return iterator
     */
    public function get_iterator(): iterator {
        return new dataflow_iterator($this->enginestep, $this->list_generator());
    }

    /**
     * Generator for the flow iterator. Yields the filenames in the list.
     *
     * @return \Generator
     */
    public function list_generator() {
        $list = $this->run();

        foreach ($list as $filename) {
            yield (object) ['filename' => $filename];
        }
    }
}
