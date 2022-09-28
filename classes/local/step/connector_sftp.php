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
 * SFTP connector step type.
 *
 * Requires the SSH2 PECL extension to be installed.
 *
 * Also requires the OpenSSL and libssh2 libraries to be installed.
 *
 * See https://www.php.net/manual/en/book.ssh2.php
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connector_sftp extends connector_step {

    /** Shorthand sftp scheme for use in config. */
    const SFTP_PREFIX = 'sftp';
    /** The actual scheme used to access the server. */
    const SSH_SFTP_SCHEME = 'ssh2.sftp://';

    /**
     * Returns whether or not the step configured, has a side effect.
     *
     * @return     bool whether or not this step has a side effect
     * @link https://en.wikipedia.org/wiki/Side_effect_(computer_science)
     */
    public function has_side_effect(): bool {
        if (isset($this->stepdef)) {
            $config = $this->stepdef->config;
            return !helper::path_is_relative($config->target);
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
            'host' => ['type' => PARAM_TEXT, 'required' => true],
            'port' => ['type' => PARAM_INT, 'required' => true, 'default' => 22],
            'fingerprint' => ['type' => PARAM_TEXT],
            'username' => ['type' => PARAM_TEXT, 'required' => true],
            'password' => ['type' => PARAM_TEXT, 'required' => true, 'secret' => true],
            'source' => ['type' => PARAM_TEXT, 'required' => true],
            'target' => ['type' => PARAM_TEXT, 'required' => true],
        ];
    }

    /**
     * Custom elements for editing the connector.
     *
     * @param \MoodleQuickForm $mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {
        $mform->addElement('text', 'config_host', get_string('connector_sftp:host', 'tool_dataflows'));
        $mform->addElement('static', 'config_host_desc', '',  get_string('connector_sftp:host_desc', 'tool_dataflows'));
        $mform->addElement('text', 'config_port', get_string('connector_sftp:port', 'tool_dataflows'));
        $mform->setDefault('config_port', 22);
        $mform->addElement('text', 'config_fingerprint', get_string('connector_sftp:fingerprint', 'tool_dataflows'));
        $mform->addElement('static', 'config_fingerprint_desc', '',
                get_string('connector_sftp:fingerprint_desc', 'tool_dataflows'));
        $mform->addElement('text', 'config_username', get_string('username'));
        $mform->addElement('passwordunmask', 'config_password', get_string('password'));

        $mform->addElement('text', 'config_source', get_string('connector_sftp:source', 'tool_dataflows'));
        $mform->addElement('static', 'config_source_desc', '',  get_string('connector_sftp:source_desc', 'tool_dataflows').
                \html_writer::nonempty_tag('pre', get_string('connector_sftp:path_example', 'tool_dataflows').
                get_string('path_help_examples', 'tool_dataflows')));

        $mform->addElement('text', 'config_target', get_string('connector_sftp:target', 'tool_dataflows'));
        $mform->addElement('static', 'config_target_desc', '',  get_string('connector_sftp:target_desc', 'tool_dataflows').
                \html_writer::nonempty_tag('pre', get_string('connector_sftp:path_example', 'tool_dataflows').
                get_string('path_help_examples', 'tool_dataflows')));
    }

    /**
     * Validate the configuration settings.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        $errors = [];
        if (empty($config->host)) {
            $errors['config_host'] = get_string(
                'config_field_missing',
                'tool_dataflows',
                get_string('connector_sftp:host', 'tool_dataflows'),
                true
            );
        }
        if (empty($config->username)) {
            $errors['config_username'] = get_string(
                'config_field_missing',
                'tool_dataflows',
                get_string('username'),
                true
            );
        }
        if (empty($config->password)) {
            $errors['config_password'] = get_string(
                'config_field_missing',
                'tool_dataflows',
                get_string('password'),
                true
            );
        }
        if (empty($config->source)) {
            $errors['config_source'] = get_string(
                'config_field_missing',
                'tool_dataflows',
                get_string('connector_sftp:source', 'tool_dataflows'),
                true
            );
        }
        if (empty($config->target)) {
            $errors['config_target'] = get_string(
                'config_field_missing',
                'tool_dataflows',
                get_string('connector_sftp:target', 'tool_dataflows'),
                true
            );
        }

        $hasremote = true;
        // Check that at least one file config has an sftp:// scheme.
        if (!empty($config->source) && !empty($config->target)) {
            // Check if the source or target is an expression, and evaluate it if required.
            $sourceremote = helper::path_has_scheme($config->source, self::SFTP_PREFIX);
            $targetremote = helper::path_has_scheme($config->target, self::SFTP_PREFIX);
            $hasremote = $sourceremote || $targetremote;
        }
        if (!$hasremote) {
            $errormsg = get_string('connector_sftp:missing_remote', 'tool_dataflows', null, true);
            $errors['config_source'] = $errors['config_source'] ?? $errormsg;
            $errors['config_target'] = $errors['config_target'] ?? $errormsg;
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

        $errors = [];
        if (!extension_loaded('ssh2')) {
            $errors['not_installed'] = get_string('connector_sftp:no_ssh2', 'tool_dataflows');
        }

        $error = helper::path_validate($config->source);
        if ($error !== true) {
            $errors['config_source'] = $error;
        }

        $error = helper::path_validate($config->target);
        if ($error !== true) {
            $errors['config_target'] = $error;
        }

        return $errors ?: true;
    }

    /**
     * Executes the step
     *
     * Performs an SFTP call according to config parameters.
     *
     * @param  mixed|null $input
     * @return mixed
     */
    public function execute($input = null) {
        $config = $this->stepdef->config;

        $this->log("Connecting to $config->host:$config->port");
        if (($connection = @ssh2_connect($config->host, $config->port)) === false) {
            throw new \moodle_exception(get_string('connector_sftp:bad_host', 'tool_dataflows'));
        }

        try {
            if ($config->fingerprint && @ssh2_fingerprint($connection) !== $config->fingerprint) {
                throw new \moodle_exception(get_string('connector_sftp:bad_fingerprint', 'tool_dataflows'));
            }

            if (@ssh2_auth_password($connection, $config->username, $config->password) === false) {
                throw new \moodle_exception(get_string('connector_sftp:bad_auth', 'tool_dataflows'));
            }

            if (($sftp = @ssh2_sftp($connection)) === false) {
                throw new \moodle_exception(get_string('connector_sftp:bad_sftp', 'tool_dataflows'));
            }

            if (!$this->is_dry_run() || !$this->has_side_effect()) {
                $sftphost = self::SSH_SFTP_SCHEME . intval($sftp) . '/';
                $source = $this->resolve_path($config->source, $sftphost);
                $target = $this->resolve_path($config->target, $sftphost);

                $this->log("Copying '$source' to '$target'");
                if (@copy($source, $target) === false) {
                    throw new \moodle_exception(get_string('connector_sftp:copy_fail', 'tool_dataflows'));
                }
            }
        } finally {
            ssh2_disconnect($connection);
        }

        return true;
    }

    /**
     * Resolve a path for SFTP. Either a remote SFTP file name or a local path to be resolved normally.
     *
     * @param string $pathname
     * @param string $sftphost
     * @return string
     */
    public function resolve_path(string $pathname, string $sftphost): string {
        if (helper::path_has_scheme($pathname, self::SFTP_PREFIX)) {
            return str_replace(self::SFTP_PREFIX . '://', $sftphost, $pathname);
        }
        return $this->enginestep->engine->resolve_path($pathname);
    }
}
