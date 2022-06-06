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

use Symfony\Component\Yaml\Yaml;
use tool_dataflows\local\execution\flow_engine_step;
use tool_dataflows\local\execution\iterators\iterator;
use tool_dataflows\local\execution\iterators\map_iterator;

/**
 * Stream writer step. Writes to a PHP stream.
 *
 *  configuration is
 *      streamname: <php stream> (The stream to output to.)
 *      format: <format> (The name of the format to encode the output.)
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class writer_stream extends writer_step {

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    protected static function form_define_fields(): array {
        return [
            'streamname' => ['type' => PARAM_TEXT],
            'format' => ['type' => PARAM_TEXT],
        ];
    }

    /**
     * Get the iterator for the step, based on configurations.
     *
     * @param flow_engine_step $step
     * @return iterator
     */
    public function get_iterator(flow_engine_step $step): iterator {
        $upstream = current($step->upstreams);
        if ($upstream === false || !$upstream->is_flow()) {
            throw new \moodle_exception(get_string('non_reader_steps_must_have_flow_upstreams', 'tool_dataflows'));
        }

        $config = $step->stepdef->config;

        // We make no output in a dry run.
        if ($step->engine->isdryrun) {
            return new map_iterator($step, $upstream->iterator);
        }

        /*
         * Iterator class to write out to the stream.
         */
        return new class($step, $config->streamname, $config->format, $upstream->iterator) extends map_iterator {
            /** @var resource stream handle. */
            private $handle;
            /** @var object dataformat writer. */
            private $writer;

            public function __construct(flow_engine_step $step, string $streamname, string $format, iterator $input) {
                $this->handle = fopen($streamname, 'a');
                if ($this->handle === false) {
                    throw new \moodle_exception(get_string('unable_to_open_stream', 'tool_dataflows', $streamname));
                }
                $classname = 'tool_dataflows\local\formats\encoders\\' . $format;
                $this->writer = new $classname();

                fwrite($this->handle, $this->writer->start_output());

                parent::__construct($step, $input);
            }

            /**
             * Next item in the stream.
             *
             * @return object|bool A JSON compatible object, or false if nothing returned.
             */
            public function next() {
                if ($this->finished) {
                    return false;
                }

                $value = $this->input->next();
                ++$this->iterationcount;

                if ($value !== false) {
                    fwrite($this->handle, $this->writer->encode_record($value, $this->iterationcount));
                }

                if ($this->input->is_finished()) {
                    fwrite($this->handle, $this->writer->close_output());
                    $this->abort();
                }
                $this->step->log('Iteration ' . $this->iterationcount . ': ' . json_encode($value));
                return $value;
            }

            public function abort() {
                fclose($this->handle);
                $this->finished = true;
            }
        };
    }

    /**
     * Validate the configuration settings.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        $errors = [];
        if (!isset($config->streamname)) {
            $errors['config_streamname'] = get_string('config_field_missing', 'tool_dataflows', 'streamname', true);
        }
        if (!isset($config->format)) {
            $errors['config_format'] = get_string('config_field_missing', 'tool_dataflows', 'format', true);
        } else {
            if (!class_exists('tool_dataflows\local\formats\encoders\\' . $config->format)) {
                $errors['config_format'] = get_string('format_not_supported', 'tool_dataflows', $config->format, true);
            }
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * {@inheritdoc}
     *
     * @param \MoodleQuickForm &$mform
     */
    public function add_custom_form_inputs(\MoodleQuickForm &$mform) {
        // Stream name.
        $mform->addElement('text', 'config_streamname', get_string('writer_stream:streamname', 'tool_dataflows'));
        $mform->setType('config_streamname', PARAM_TEXT);

        // Format.
        $mform->addElement('text', 'config_format', get_string('writer_stream:format', 'tool_dataflows'));
        $mform->setType('config_format', PARAM_TEXT);
    }
}
