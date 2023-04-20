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

/**
 * Encrypt/decrypt with GPG.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2023, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait gpg_trait {
    /**
     * Returns whether or not the step configured, has a side effect.
     *
     * @return     bool whether or not this step has a side effect
     * @link https://en.wikipedia.org/wiki/Side_effect_(computer_science)
     */
    public function has_side_effect(): bool {
        if (isset($this->stepdef)) {
            $config = $this->stepdef->config;
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
            'userid' => ['type' => PARAM_TEXT, 'required' => true],
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
        $mform->addElement('select', 'config_command', 'Comamnd', [
            'encrypt' => get_string('gpg:encrypt', 'tool_dataflows'),
            'decrypt' => get_string('gpg:decrypt', 'tool_dataflows'),
        ]);
        // Key user ID.
        $mform->addElement('text', 'config_userid', get_string('gpg:userid', 'tool_dataflows'));
        $mform->addRule('config_userid', get_string('required'), 'required', null, 'client');

        // From / Source path.
        $mform->addElement('text', 'config_from', get_string('flow_copy_file:from', 'tool_dataflows'));
        $mform->addRule('config_from', get_string('required'), 'required', null, 'client');

        // To / Target path.
        $mform->addElement('text', 'config_to', get_string('flow_copy_file:to', 'tool_dataflows'));
        $mform->addRule('config_to', get_string('required'), 'required', null, 'client');
    }

    /**
     * Executes the step and copies what is in $from, to the $to path
     *
     * @param  mixed|null $input
     * @return mixed
     */
    public function execute($input = null) {
        $variables = $this->get_variables();
        $config = $variables->get('config');
        $config->from = $this->enginestep->engine->resolve_path($config->from);
        $config->to = $this->enginestep->engine->resolve_path($config->to);

        if ($config->command === 'encrypt') {
            $executable = $this->get_encrypt_command($config);
        } else if ($config->command === 'decrypt') {
            $executable = $this->get_decrypt_command($config);
        }

        // We do not need to go any further if it is a dry run.
        if ($this->is_dry_run() && $this->has_side_effect()) {
            $this->enginestep->log("Would execute '$executable'");
            return true;
        }

        $output = [];
        $result = null;
        $this->enginestep->log("Executing '$executable'");
        exec($executable, $output, $result);
        $this->enginestep->log($result === 0 ? 'Success' : 'Fail');

        $variables->set('vars.success', $result === 0);

        return $input;
    }

    /**
     * Returns the command to be used for an encrypt run.
     *
     * @param string $config The step configuration.
     * @return string The executable to run.
     */
    public function get_encrypt_command($config): string {
        $path = get_config('tool_dataflows', 'gpg_exec_path');
        $keydir = get_config('tool_dataflows', 'gpg_key_dir');
        $executable = "$path --homedir $keydir -r $config->userid -o $config->to --encrypt $config->from";
        return $executable;
    }

    /**
     * Returns the command to be used for a decrypt run.
     *
     * @param string $config The step configuration.
     * @return string The executable to run.
     */
    public function get_decrypt_command($config): string {
        $path = get_config('tool_dataflows', 'gpg_exec_path');
        $keydir = get_config('tool_dataflows', 'gpg_key_dir');
        $executable = "$path --homedir $keydir -u $config->userid -o $config->to --decrypt $config->from";
        return $executable;
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

        return $errors ?: true;
    }

    /**
     * A list of outputs and their description if applicable.
     *
     * @return  array of outputs
     */
    public function define_outputs(): array {
        return ['success' => get_string('gpg:output_success', 'tool_dataflows')];
    }
}
