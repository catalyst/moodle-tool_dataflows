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

/**
 * Stream writer step
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dataflows\local\step;

use tool_dataflows\local\execution\flow_engine_step;
use tool_dataflows\local\execution\iterators\iterator;
use tool_dataflows\local\execution\iterators\dataflow_iterator;
use tool_dataflows\manager;
use tool_dataflows\helper;

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

    /** @var string dataflow local encoder path */
    const LOCAL_ENCODER_PATH = 'tool_dataflows\local\formats\encoders';


    /**
     * Returns whether or not the step configured, has a side effect
     *
     * A side effect if it modifies some state variable value(s) outside its
     * local environment, which is to say if it has any observable effect other
     * than its primary effect of returning a value to the invoker of the
     * operation.
     *
     * For stream writers, it is considered to have a side effect if it writes to
     * anywhere outside of the scratch directory.
     *
     * @return     bool whether or not this step has a side effect
     * @link https://en.wikipedia.org/wiki/Side_effect_(computer_science)
     */
    public function has_side_effect(): bool {
        if (isset($this->stepdef)) {
            $config = $this->stepdef->config;
            if (isset($config->streamname)) {
                return !helper::path_is_relative($config->streamname);
            }
        }
        return true;
    }

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'streamname' => ['type' => PARAM_TEXT],
            'format' => ['type' => PARAM_TEXT],
            'prettyprint' => ['type' => PARAM_BOOL],
        ];
    }

    /**
     * Returns the fully qualified classname for the class provided
     *
     * @param   string $classname
     * @return  string empty string if the class is not found
     */
    public static function resolve_encoder(string $classname): string {
        if (class_exists($classname)) {
            return $classname;
        }

        $localencoderpath = self::LOCAL_ENCODER_PATH;
        if (class_exists("{$localencoderpath}\\{$classname}")) {
            return "{$localencoderpath}\\{$classname}";
        }

        return '';
    }

    /**
     * Get the iterator for the step, based on configurations.
     *
     * @return iterator
     */
    public function get_iterator(): iterator {
        $upstream = current($this->enginestep->upstreams);
        if ($upstream === false || !$upstream->is_flow()) {
            throw new \moodle_exception(get_string('non_reader_steps_must_have_flow_upstreams', 'tool_dataflows'));
        }

        $config = $this->enginestep->stepdef->config;

        // We make no output in a dry run.
        if ($this->enginestep->engine->isdryrun) {
            return new dataflow_iterator($this->enginestep, $upstream->iterator);
        }

        /*
         * Iterator class to write out to the stream.
         */
        return new class($this->enginestep, $config, $upstream->iterator) extends dataflow_iterator {
            /** @var resource stream handle. */
            private $handle;
            /** @var string name of the stream. */
            private $streamname;
            /** @var object dataformat writer. */
            private $writer;

            /**
             * Create an instance of this class.
             *
             * @param  flow_engine_step $step
             * @param  object $config
             * @param  iterator $input
             */
            public function __construct(flow_engine_step $step, $config, iterator $input) {
                $this->streamname = $step->engine->resolve_path($config->streamname);

                $this->handle = fopen($this->streamname, 'a');
                if ($this->handle === false) {
                    $step->log(error_get_last()['message']);
                    throw new \moodle_exception(get_string('writer_stream:failed_to_open_stream', 'tool_dataflows', $streamname));
                }

                $classname = writer_stream::resolve_encoder($config->format);
                $this->writer = new $classname();
                $this->writer->set_prettyprint($config->prettyprint ?? false);

                if (fwrite($this->handle, $this->writer->start_output()) === false) {
                    $step->log(error_get_last()['message']);
                    throw new \moodle_exception(get_string('writer_stream:failed_to_write_stream', 'tool_dataflows', $streamname));
                }

                parent::__construct($step, $input);
            }

            /**
             * Next item in the stream.
             *
             * @return object|bool A JSON compatible object, or false if nothing returned.
             */
            public function on_next() {
                $value = $this->input->current();
                if ($value !== false) {
                    if (fwrite($this->handle, $this->writer->encode_record($value, $this->iterationcount)) === false) {
                        $this->step->log(error_get_last()['message']);
                        throw new \moodle_exception(
                            get_string('writer_stream:failed_to_write_stream', 'tool_dataflows', $this->streamname)
                        );
                    }
                }
            }

            /**
             * Any custom handling for on_abort
             */
            public function on_abort() {
                if (fwrite($this->handle, $this->writer->close_output()) === false) {
                    $this->step->log(error_get_last()['message']);
                    throw new \moodle_exception(
                        get_string('writer_stream:failed_to_write_stream', 'tool_dataflows', $this->streamname)
                    );
                }
                fclose($this->handle);
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
            $format = self::resolve_encoder($config->format);
            if (!class_exists($format)) {
                $errors['config_format'] = get_string('format_not_supported', 'tool_dataflows', $config->format, true);
            }
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Perform any extra validation that is required only for runs.
     *
     * @return true|array Will return true or an array of errors.
     */
    public function validate_for_run() {
        $config = $this->stepdef->config;

        $error = helper::path_validate($config->streamname);
        if ($error !== true) {
            $errors['config_streamname'] = $error;
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Returns a list of encoder options - local and external
     *
     * For external encoders, it will use the FQCN, whereas local ones will use
     * just the basename of the class.
     *
     * @return  array of encoder options
     */
    public function get_encoder_options(): array {
        $options = array_reduce(manager::get_encoders(), function ($acc, $encoder) {
            $classname = get_class($encoder);
            $basename = substr($classname, strrpos($classname, '\\') + 1);
            // The format is currently hardcoded to be under the dataflow namespace (not using fqcn).
            // If the class is not under the local encoder path (e.g. for external encoders), it will store the value as a FQCN.
            // If it is local, it will only store the basename for conciseness.
            $key = $classname;
            if (strpos($classname, self::LOCAL_ENCODER_PATH) !== false) {
                $key = $basename;
            }
            $acc[$key] = $basename;
            return $acc;
        }, []);

        return $options;
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
        // Stream name.
        $mform->addElement('text', 'config_streamname', get_string('writer_stream:streamname', 'tool_dataflows'), ['size' => '50']);
        $mform->addElement('static', 'config_streamname_help', '',  get_string('path_help', 'tool_dataflows').
            \html_writer::nonempty_tag('pre', get_string('path_help_examples', 'tool_dataflows')));

        // Format.
        $mform->addElement(
            'select',
            'config_format',
            get_string('writer_stream:format', 'tool_dataflows'),
            $this->get_encoder_options()
        );
        $mform->addElement('static', 'config_format_help', '', get_string('writer_stream:format_help', 'tool_dataflows'));

        $mform->addElement('checkbox', 'config_prettyprint', get_string('writer_stream:prettyprint', 'tool_dataflows'));
        $mform->addElement('static', 'config_prettyprint_help', '', get_string('writer_stream:prettyprint_help', 'tool_dataflows'));
    }
}
