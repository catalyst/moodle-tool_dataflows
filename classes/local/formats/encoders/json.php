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

namespace tool_dataflows\local\formats\encoders;

use tool_dataflows\local\formats\encoder_base;

/**
 * Encodes json.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class json extends encoder_base {

    /**
     * Return the start of the output.
     *
     * @return string
     */
    public function start_output(): string {
        return '[';
    }

    /**
     * Encode a single record
     *
     * @param mixed $record
     * @param int $rownum
     */
    public function encode_record($record, int $rownum): string {
        $output = '';
        if ($this->sheetdatadded) {
            $output .= ',';
        }

        $output .= json_encode($record) . PHP_EOL;

        $this->sheetdatadded = true;
        return $output;
    }

    /**
     * Return the end of the input.
     *
     * @return string
     */
    public function close_output(): string {
        return ']' . PHP_EOL;
    }
}
