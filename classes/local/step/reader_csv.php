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

use tool_dataflows\helper;
use tool_dataflows\local\execution\iterators\iterator;
use tool_dataflows\local\execution\iterators\dataflow_iterator;

/**
 * CSV reader step
 *
 * @package   tool_dataflows
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright Catalyst IT, 2022
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reader_csv extends reader_step {

    /** @var string */
    const DEFAULT_DELIMETER = ',';

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'path' => ['type' => PARAM_TEXT, 'required' => true],
            'headers' => ['type' => PARAM_TEXT],
            'overwriteheaders' => ['type' => PARAM_BOOL],
            'continueonerror' => ['type' => PARAM_BOOL],
            'delimiter' => ['type' => PARAM_TEXT],
        ];
    }

    /**
     * Get the iterator for the step, based on configurations.
     *
     * @return iterator
     */
    public function get_iterator(): iterator {
        return new dataflow_iterator($this->enginestep, $this->csv_contents_generator());
    }

    /**
     * Returns an iterator based on the results read from the CSV contents
     */
    public function csv_contents_generator() {
        $maxlinelength = 1000;
        $variables = $this->get_variables();
        $config = $variables->get('config');
        $strheaders = $config->headers;
        $overwriteheaders = !empty($config->overwriteheaders);
        $continueonerror = !empty($config->continueonerror);
        $delimiter = $config->delimiter ?: self::DEFAULT_DELIMETER;
        $path = $this->enginestep->engine->resolve_path($config->path);

        if (($handle = @fopen($path, 'r')) === false) {
            throw new \moodle_exception('writer_stream:failed_to_open_stream', 'tool_dataflows', '', $path);
        }

        try {
            // Prepare and resolve headers.
            if (empty($config->headers)) {
                $strheaders = fgets($handle);
            }

            // At this point, if headers is false, then the file is empty, and
            // so it should continue as if the file finished.
            if ($strheaders === false) {
                return;
            }

            // Used the configured headers in place of the first row if overwriteheaders is true.
            if ($overwriteheaders && $config->headers) {
                fgets($handle); // Move the pointer past the first line.
                $strheaders = $config->headers;
            }

            // Convert header string to an actual headers array.
            $headers = str_getcsv($strheaders, $delimiter);
            $numheaders = count($headers);
            $rownumber = 1; // First row is always headers.
            $errors = ['header_field_count_mismatch' => 0];
            while (($data = fgetcsv($handle, $maxlinelength, $delimiter)) !== false) {
                $rownumber++;
                $numfields = count($data);
                if ($numfields !== $numheaders) {
                    // Continue on (parse) error.
                    if ($continueonerror) {
                        $errors['header_field_count_mismatch'] += 1;
                        $this->log->error(
                            get_string('reader_csv:header_field_count_mismatch', 'tool_dataflows', (object) [
                                'numfields' => $numfields,
                                'numheaders' => $numheaders,
                                'rownumber' => $rownumber,
                            ]),
                            [
                                'fields' => $data,
                                'headers' => $headers,
                            ]
                        );

                        continue;
                    }

                    // Throw exception on error.
                    throw new \moodle_exception('reader_csv:header_field_count_mismatch', 'tool_dataflows', '', (object) [
                        'numfields' => $numfields,
                        'numheaders' => $numheaders,
                        'rownumber' => $rownumber,
                    ], json_encode([
                        'fields' => $data,
                        'headers' => $headers,
                    ]));
                }
                $record = array_combine($headers, $data);
                yield (object) $record;
            }
        } finally {
            fclose($handle);
        }

        $variables->set('errors', (object) $errors);
    }

    /**
     * Validate the configuration settings.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        $errors = [];
        $error = helper::path_validate($config->path);
        if ($error !== true) {
            $errors['config_path'] = $error;
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
     * @param \MoodleQuickForm $mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {
        // CSV array source.
        $mform->addElement('text', 'config_path', get_string('reader_csv:path', 'tool_dataflows'));

        // Input for the headers, which should be set if the file does not already have its own headers.
        // NOTE: Does NOT resolve the edge case if you want to "override" the header keys used.
        $mform->addElement('text', 'config_headers', get_string('reader_csv:headers', 'tool_dataflows'));
        $mform->addElement('static', 'config_headers_help', '', get_string('reader_csv:headers_help', 'tool_dataflows'));

        // Used when we want to replace the headers (or overwrite them) using the ones we supplied instead. Defaults to off.
        $mform->addElement('checkbox', 'config_overwriteheaders', get_string('reader_csv:overwriteheaders', 'tool_dataflows'),
                get_string('reader_csv:overwriteheaders_help', 'tool_dataflows'));
        $mform->hideIf('config_overwriteheaders', 'config_headers', 'eq', '');
        $mform->disabledIf('config_overwriteheaders', 'config_headers', 'eq', '');

        // Used when we want to replace the headers (or overwrite them) using the ones we supplied instead. Defaults to off.
        $mform->addElement('checkbox', 'config_continueonerror', get_string('reader_csv:continueonerror', 'tool_dataflows'),
                get_string('reader_csv:continueonerror_help', 'tool_dataflows'));

        // Delimiter.
        $mform->addElement(
            'text',
            'config_delimiter',
            get_string('reader_csv:delimiter', 'tool_dataflows'),
            ['placeholder' => self::DEFAULT_DELIMETER],
            self::DEFAULT_DELIMETER
        );
    }
}
