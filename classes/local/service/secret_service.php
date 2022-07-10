<?php
// This file is part of Moodle - http://moodle.org/
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

namespace tool_dataflows\local\service;

/**
 * Secret Service
 *
 * A service which should handle and manage secrets, redaction, and more.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class secret_service {

    /** @var string a placeholder value for redacted secrets */
    const REDACTED_PLACEHOLDER = '*****';

    /**
     * Returns the data provided with some fields redacted.
     *
     * By default, it will use the REDACTED_PLACEHOLDER regardless if the value
     * had been set or not, purely because it's configured as a secret holdilng
     * value.
     *
     * @param   \stdClass $fields
     * @param   array $keystoredact
     * @return  \stdClass $redactedfields
     */
    public function redact_fields(\stdClass $fields, array $keystoredact): \stdClass {
        $fieldswithredaction = array_fill_keys($keystoredact, self::REDACTED_PLACEHOLDER);
        $redactedfields = (object) array_merge((array) $fields, $fieldswithredaction);
        return $redactedfields;
    }
}
