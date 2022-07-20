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
 * Flow filter (transformer step) class
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow_transformer_filter extends flow_transformer_step {

    /** @var int[] number of input flows (min, max). */
    protected $inputflows = [1, 1];

    /** @var int[] number of output flows (min, max). */
    protected $outputflows = [1, 1];

    /**
     * Apply the filter based on configuration
     *
     * @param   mixed $record
     * @return  mixed $record
     */
    public function execute($record) {
        // TODO: implement.
        return $record;
    }
}

