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

namespace tool_dataflows\local\step;

use coding_exception;
use stdClass;
use tool_dataflows\helper;

/**
 * File compression trait
 *
 * @package    tool_dataflows
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait compression_trait {
    /**
     * Returns whether or not the step configured, has a side effect.
     *
     * @return bool whether or not this step has a side effect
     */
    public function has_side_effect(): bool {
        if (isset($this->stepdef)) {
            $config = $this->get_variables()->get('config');
            return !helper::path_is_relative($config->to);
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
            'command' => ['type' => PARAM_TEXT, 'required' => true],
            'method' => ['type' => PARAM_TEXT, 'required' => true],
            'from'   => ['type' => PARAM_TEXT, 'required' => true],
            'to'     => ['type' => PARAM_TEXT, 'required' => true],
        ];
    }

    /**
     * Custom elements for editing the connector.
     *
     * @param \MoodleQuickForm $mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {

        $mform->addElement('select', 'config_command', get_string('compression:command', 'tool_dataflows'), [
            'compress' => get_string('compression:compress', 'tool_dataflows'),
            'decompress' => get_string('compression:decompress', 'tool_dataflows'),
        ]);

        // Build the selector options using the supported methods as the source.
        $supportedmethods = $this->get_supported_methods();
        $methodkeys = array_keys($supportedmethods);
        $methodvalues = array_column($supportedmethods, 'name');
        $methodoptions = array_combine($methodkeys, $methodvalues);

        $mform->addElement('select', 'config_method', get_string('compression:method', 'tool_dataflows'), $methodoptions);

        // From / Source path.
        $mform->addElement('text', 'config_from', get_string('compression:from', 'tool_dataflows'));
        $mform->addRule('config_from', get_string('required'), 'required', null, 'client');

        // To / Target path.
        $mform->addElement('text', 'config_to', get_string('compression:to', 'tool_dataflows'));
        $mform->addRule('config_to', get_string('required'), 'required', null, 'client');
    }

    /**
     * Executes the step
     *
     * @param  mixed|null $input
     * @return mixed
     */
    public function execute($input = null) {
        $variables = $this->get_variables();
        $config = $variables->get('config');
        $config->from = $this->enginestep->engine->resolve_path($config->from);
        $config->to = $this->enginestep->engine->resolve_path($config->to);

        // Check that the from path exists.
        if (!is_file($config->from)) {
            $this->log->error($config->from . ' file does not exist');
            $variables->set('vars.success', false);
            return $input;
        }

        // We do not need to go any further if it is a dry run.
        if ($this->is_dry_run() && $this->has_side_effect()) {
            return $input;
        }

        $result = $this->execute_method($config);

        if ($result !== true) {
            // Log the error.
            $this->log->error($result);
        }

        $variables->set('vars.success', $result === true);

        return $input;
    }

    /**
     * Perform any extra validation that is required only for runs.
     *
     * @return true|array Will return true or an array of errors.
     */
    public function validate_for_run() {
        $config = $this->get_variables()->get('config');

        $errors = [];

        $error = helper::path_validate($config->from);
        if ($error !== true) {
            $errors['config_from'] = $error;
        }

        $error = helper::path_validate($config->to);
        if ($error !== true) {
            $errors['config_to'] = $error;
        }

        // Valid the chosen methods executable is actually executable.
        $method = $this->get_method($config->method);
        $error = ($method->isexecutable)();
        if ($error !== true) {
            $errors['config_method'] = $error;
        }

        return $errors ?: true;
    }

    /**
     * A list of outputs and their description if applicable.
     *
     * @return  array of outputs
     */
    public function define_outputs(): array {
        return ['success' => get_string('compression:output_success', 'tool_dataflows')];
    }

    /**
     * Returns the method info that has been selected in the configuration.
     *
     * @param string $method method name
     * @return object method information (name, path, etc...)
     */
    private function get_method(string $method): stdClass {
        $methods = $this->get_supported_methods();

        // If not defined it means something has gone very wrong.
        // this should almost always be defined.
        if (!isset($methods[$method])) {
            throw new coding_exception($method . ' is not defined as a supported method.');
        }

        return $methods[$method];
    }

    /**
     * Returns an array of supported methods by this step and information about them.
     *
     * @return array array of objects containing the information about each method
     */
    private function get_supported_methods(): array {
        return [
            'gzip' => (object) [
                'name' => get_string('compression:method:gzip', 'tool_dataflows'),
                'isexecutable' => function() {
                    return self::validate_executable(get_config('tool_dataflows', 'gzip_exec_path'));
                }
            ]
        ];
    }

    /**
     * Validates the executable
     *
     * @param string $path path to executable.
     * @return string|true string if error, else true if valid.
     */
    private static function validate_executable(string $path) {
        if (!is_executable($path)) {
            return get_string('compression:error:invalidexecutable', 'tool_dataflows', [
                'path' => $path
            ]);
        }

        return true;
    }

    /**
     * Executes the configured method.
     *
     * @param object $config step configuration
     * @return string|true string if error, else true if success
     */
    private function execute_method($config) {
        switch ($config->method) {
            case 'gzip':
                return $this->execute_gzip($config);
            default:
                throw new coding_exception($config->method . ' has no executable setup.');
        }
    }

    /**
     * Executes the gzip method.
     *
     * @param object $config
     * @return string|error string if error, else true if success.
     */
    private function execute_gzip($config) {
        $gzip = get_config('tool_dataflows', 'gzip_exec_path');
        $from = escapeshellarg($config->from);
        $to = escapeshellarg($config->to);

        $compressionmode = $config->command == 'decompress' ? '-d' : '';
        $movefilename = $config->command == 'compress' ? $config->from . '.gz' : rtrim($config->from, '.gz');
        $movefilename = escapeshellarg($movefilename);

        // See https://www.gnu.org/software/gzip/manual/html_node/Invoking-gzip.html.
        // -f: force override destination file if it exists
        // -v: verbose
        // -k: keep input file
        // 2>&1: pipe stderror to stdout.
        $gzipcommand = "{$gzip} -f -v -k {$compressionmode} {$from} 2>&1 && mv {$movefilename} {$to}";
        $this->log->debug("Command: " . $gzipcommand);

        // Execute the gzip command.
        $output = [];
        $result = null;
        exec($gzipcommand, $output, $result);
        $success = $result === 0;

        // Emit in error logs.
        if (!$success) {
            return implode(PHP_EOL, $output);
        }

        return true;
    }
}
