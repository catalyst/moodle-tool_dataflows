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
 * Encodes csv
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv extends encoder_base {
    /** @var string Field separator. */
    public $delimiter = ',';

    /**
     * Encode a single record
     *
     * @param mixed $record
     * @param int $rownum
     */
    public function encode_record($record, int $rownum): string {
        $fields = (array) $record;
        $output = '';
        // Output headers if writing the first line.
        if (!$this->sheetdatadded) {
            $output .= self::str_putcsv(array_keys($fields), $this->delimiter);
        }
        $output .= self::str_putcsv($fields, $this->delimiter);

        $this->sheetdatadded = true;
        return $output;
    }

    /**
     * Wrapper for fputcsv to encode an array into a CSV string.
     *
     * @param array $fields
     * @param string $delimiter
     * @param string $enclosure
     * @return string
     * @throws \moodle_exception
     */
    public static function str_putcsv(array $fields, string $delimiter = ',', string $enclosure = '"'): string {
        $fp = fopen('php://memory', 'r+b');
        try {
            if (fputcsv($fp, $fields, $delimiter, $enclosure) === false) {
                throw new \moodle_exception(get_string('writer_csv:fail_to_encode', 'tool_dataflows'));
            }
            rewind($fp);
            $data = stream_get_contents($fp);
            return $data;
        } finally {
            fclose($fp);
        }
    }
}
