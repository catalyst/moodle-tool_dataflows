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

namespace tool_dataflows\formats\encoders;

use tool_dataflows\formats\encoder_base;

/**
 * Encodes csv
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv extends encoder_base {
    /** @var int escape with slashes. */
    const ESC_SLASHES = 1;
    /** @var int escape with double quotes. */
    const ESC_DOUBLEQUOTES = 2;

    /** @var int options for encoding. */
    public $options = self::ESC_DOUBLEQUOTES;

    /**
     * Encode a single record
     *
     * @param mixed $record
     * @param int $rownum
     */
    public function encode_record($record, int $rownum): string {
        $line = [];
        foreach ($record as $field) {
            if (!is_scalar($field)) { // TODO: Just ignoring complex values for now.
                $line[] = '';
            } else if ($field === "ID") { // Special case for SYLK problem.
                $line[] = '"ID"';
            } else if ($this->options | self::ESC_DOUBLEQUOTES) {
                $line[] = $this->escape_field_doublequotes($field);
            } else if ($this->options | self::ESC_SLASHES) {
                $line[] = $this->escape_field_slashes($field);
            } else {
                 $line[] = $field;
            }
        }

        $output = implode(',', $line);
        if ($this->sheetdatadded) {
            $output = PHP_EOL . $output;
        }

        $this->sheetdatadded = true;
        return $output;
    }

    /**
     * Escapes special characters using double quotes.
     *
     * @param string $value
     * @return string
     */
    protected function escape_field_doublequotes(string $value): string {
        if ((mb_strpos($value, '"') !== false) || (mb_strpos($value, ',') !== false) ||
            (mb_strpos($value, "\n") !== false) || (mb_strpos($value, "\r") !== false)) {
            return '"' . str_replace('"', '""', $value) . '"';
        } else {
            return $value;
        }
    }

    /**
     * Escapes special characters using slashes.
     *
     * @param string $value
     * @return string
     */
    protected function escape_field_slashes(string $value): string {
        if ((mb_strpos ($value, '"') !== false) || (mb_strpos ($value, ',') !== false) ||
            (mb_strpos ($value, "\n") !== false) || (mb_strpos ($value, "\r") !== false)) {
            return '"' . str_replace('"', '\\"', str_replace ('\\', '\\\\', $value)) . '"';
        } else {
            return $value;
        }
    }
}
