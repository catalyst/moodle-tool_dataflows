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

/**
 * Base class for reader step types.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class reader_step extends flow_step {

    /** @var int[] number of input flows (min, max). */
    protected $inputflows = [0, 0];

    /** @var int[] number of output connectors (min, max). */
    protected $inputconnectors = [0, 1];

    /**
     * {@inheritdoc}
     */
    public function get_group(): string {
        return 'readers';
    }

    /**
     * Reader steps have a producing iterator.
     * This enables the dataflow engine to pull the first record from the iterator.
     *
     * @return bool
     */
    public function has_producing_iterator(): bool {
        return true;
    }
}
