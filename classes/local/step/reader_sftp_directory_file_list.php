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

use tool_dataflows\local\execution\iterators\iterator;
use tool_dataflows\local\execution\iterators\dataflow_iterator;

/**
 * Gets the files in a remote SFTP directory, and make the list a reader source.
 *
 * @package   tool_dataflows
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright Catalyst IT, 2023
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reader_sftp_directory_file_list extends reader_step {
    use sftp_directory_file_list_trait;

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
    public function list_generator(): \Generator {
        $list = $this->run();

        foreach ($list as $filename) {
            yield (object) ['filename' => $filename];
        }
    }

    /**
     * Step callback handler
     *
     * @param mixed|null $input
     * @return mixed The altered value to be passed to the next step(s) in the flow, or false to skip the rest of this iteration.
     */
    public function execute($input = null) {
        return $input;
    }

    /**
     * Returns whether or not the step configured, has a side effect.
     *
     * @return     bool whether or not this step has a side effect
     * @link https://en.wikipedia.org/wiki/Side_effect_(computer_science)
     */
    public function has_side_effect(): bool {
        return false;
    }
}
