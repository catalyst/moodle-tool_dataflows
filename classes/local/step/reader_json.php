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

use tool_dataflows\local\execution\flow_engine_step;
use tool_dataflows\local\execution\iterators\iterator;
use tool_dataflows\local\execution\iterators\dataflow_iterator;
use tool_dataflows\parser;

/**
 * JSON reader step
 *
 * @package   tool_dataflows
 * @author    Peter Sistrom <petersistrom@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reader_json extends reader_step {

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    protected static function form_define_fields(): array {
        return [
            'json' => ['type' => PARAM_TEXT],
            'arraykey' => ['type' => PARAM_TEXT],
            'arraysort' => ['type' => PARAM_TEXT],
        ];
    }

    /**
     * Get the iterator for the step, based on configurations.
     *
     * @return iterator
     * @throws \moodle_exception
     */
    public function get_iterator(): iterator {
        $jsonarray = $this->parse_json();
        return new dataflow_iterator($this->enginestep, new \ArrayIterator($jsonarray));
    }

    /**
     * Parses json string to php array.
     *
     * @return string
     */
    protected function parse_json(): array {

        $decodedjson = json_decode($this->enginestep->stepdef->config->json);
        $arraykey = $this->enginestep->stepdef->config->arraykey;

        // TODO handle $returnarray being null (e.g. not valid JSON or arraykey doesn't exist).
        $returnarray = $decodedjson->$arraykey;

        // TODO sort by config value.

        return $returnarray;
    }

    /**
     * Validate the configuration settings.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        $errors = [];
        if (empty($config->json)) {
            $errors['config_json'] = get_string('config_field_missing', 'tool_dataflows', 'json', true);
        }
        return empty($errors) ? true : $errors;
    }

    /**
     * Allows each step type to determine a list of optional/required form
     * inputs for their configuration
     *
     * It's recommended you prefix the additional config related fields to avoid
     * conflicts with any existing fields.
     *
     * @param \MoodleQuickForm &$mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {
        // JSON array source.
        $mform->addElement('textarea', 'config_json', get_string('reader_json:json', 'tool_dataflows'));
        $mform->addElement('static', 'config_json_help', '', get_string('reader_json:json_help', 'tool_dataflows'));

        // Array iterator value.
        $mform->addElement('text', 'config_arraykey', get_string('reader_json:arraykey', 'tool_dataflows'));
        $mform->addElement('static', 'config_arraykey_help', '', get_string('reader_json:arraykey_help', 'tool_dataflows'));

        // JSON array sort by.
        $mform->addElement('text', 'config_arraysort', get_string('reader_json:arraysort', 'tool_dataflows'));
        $mform->addElement('static', 'config_arraysort_help', '', get_string('reader_json:arraysort_help', 'tool_dataflows'));
    }
}
