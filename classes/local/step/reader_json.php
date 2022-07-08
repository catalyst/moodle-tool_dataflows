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

use html_writer;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use tool_dataflows\helper;
use tool_dataflows\local\execution\iterators\iterator;
use tool_dataflows\local\execution\iterators\dataflow_iterator;

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
            'pathtojson' => ['type' => PARAM_TEXT],
            'arrayexpression' => ['type' => PARAM_TEXT],
            'arraysortexpression' => ['type' => PARAM_TEXT],
        ];
    }

    /**
     * Get the iterator for the step, based on configurations.
     *
     * @return iterator
     */
    public function get_iterator(): iterator {
        $jsonarray = $this->parse_json();
        return new dataflow_iterator($this->enginestep, new \ArrayIterator($jsonarray));
    }

    /**
     * Parses json string to php array.
     *
     * @return mixed
     * @throws \moodle_exception
     */
    protected function parse_json() {
        $config = $this->enginestep->stepdef->config;
        $jsonstring = $this->get_json_string($config->pathtojson);

        $decodedjson = json_decode($jsonstring);
        if (is_null($decodedjson)) {
            throw new \moodle_exception(get_string('reader_json:failed_to_decode_json', 'tool_dataflows', $config->pathtojson));
        }

        $arrayexpression = $config->arrayexpression;
        $expressionlanguage = new ExpressionLanguage();
        $returnarray = $expressionlanguage->evaluate(
            $arrayexpression != '' ? 'data.'.$arrayexpression : 'data',
            [
                'data' => $decodedjson,
            ]
        );

        if (is_null($returnarray)) {
            throw new \moodle_exception(get_string('reader_json:failed_to_fetch_array',
                'tool_dataflows', $config->arrayexpression));
        }

        $sortbyexpression = $config->arraysortexpression;

        // Sort the parsed array if required.
        if ($sortbyexpression !== '') {
            return $this->sort_by_config_value($returnarray, $sortbyexpression);
        }

        return $returnarray;
    }

    /**
     * Parses stream to json string.
     *
     * @return string
     * @throws \moodle_exception
     */
    protected function get_json_string(string $path): string {
        $jsonstring = file_get_contents($this->enginestep->engine->resolve_path($path));
        if ($jsonstring === false) {
            $this->enginestep->log(error_get_last()['message']);
            throw new \moodle_exception(get_string('reader_json:failed_to_open_file', 'tool_dataflows', $path));
        }

        return $jsonstring;
    }

    /**
     * Sort array by config value.
     *
     * @param array $array
     * @param string $sortbyexpression
     */
    public static function sort_by_config_value(array $array, string $sortbyexpression): array {
        $expressionlanguage = new ExpressionLanguage();
        usort($array, function($a, $b) use ($sortbyexpression, $expressionlanguage) {
            $a = $expressionlanguage->evaluate(
                'data.'.$sortbyexpression,
                ['data' => $a]
            );
            $b = $expressionlanguage->evaluate(
                'data.'.$sortbyexpression,
                ['data' => $b]
            );
            return strnatcasecmp($a, $b);
        });
        return $array;
    }

    /**
     * Validate the configuration settings.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        $errors = [];
        if (!isset($config->pathtojson)) {
            $errors['config_pathtojson'] = get_string('config_field_missing', 'tool_dataflows', 'pathtojson', true);
        } else {
            $error = helper::path_validate($config->pathtojson);
            if ($error !== true) {
                $errors['config_pathtojson'] = $error;
            }
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
        $mform->addElement('text', 'config_pathtojson', get_string('reader_json:pathtojson', 'tool_dataflows'));
        $mform->addElement('static', 'config_json_path_help', '',
            get_string('reader_json:json_path_help', 'tool_dataflows',
                html_writer::nonempty_tag('code', 'file:///path/to/file.json')));

        // Array iterator value.
        $arrayexample = (object) [
            'data' => (object) [
                'list' => [
                    'users' => [
                        [ "id" => "1",  "userdetails" => ["firstname" => "Bob", "lastname" => "Smith", "name" => "Name1"]],
                    ],
                ]
            ],
            'modified' => [1654058940],
            'errors' => [],
        ];
        $jsonexample = html_writer::empty_tag('br').html_writer::nonempty_tag('code', json_encode($arrayexample, JSON_PRETTY_PRINT));
        $expression = html_writer::nonempty_tag('code', 'data.list.users');

        $mform->addElement('text', 'config_arrayexpression', get_string('reader_json:arrayexpression', 'tool_dataflows'));
        $mform->addElement('static', 'config_arrayexpression_help', '',
            get_string('reader_json:arrayexpression_help', 'tool_dataflows',
                ['jsonexample' => $jsonexample, 'expression' => $expression]));

        // JSON array sort by.
        $mform->addElement('text', 'config_arraysortexpression', get_string('reader_json:arraysortexpression', 'tool_dataflows'));
        $mform->addElement('static', 'config_arraysortexpression_help', '',
            get_string('reader_json:arraysortexpression_help', 'tool_dataflows',
                html_writer::nonempty_tag('code', 'usersdetails.firstname')));
    }
}
