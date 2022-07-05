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
     * @var string Indent space used for pretty printing. As far as can be determined, 4 spaces for
     * indenting is hardwired in json_encode() for pretty printing.
     */
    protected const INDENT = '    ';

    /**
     * Return the start of the output.
     *
     * @return string
     */
    public function start_output(): string {
        $output = '[' . PHP_EOL;

        if ($this->prettyprint) {
            $output .= self::INDENT;
        }
        return $output;
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
            $output .= ',' . PHP_EOL;
        }

        // Add encoded record to ouput.
        $flags = $this->prettyprint ? JSON_PRETTY_PRINT : 0;
        $output .= json_encode($record, $flags);

        // Add indenting inbetween records as json_encode() will only pretty print the record itself.
        if ($this->prettyprint) {
            $output = str_replace(PHP_EOL, PHP_EOL . self::INDENT, $output);
        }
        $this->sheetdatadded = true;
        return $output;
    }

    /**
     * Return the end of the input.
     *
     * @return string
     */
    public function close_output(): string {
        return  PHP_EOL . ']' . PHP_EOL;
    }
}
