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

use phpseclib3\Crypt\Common\AsymmetricKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use tool_dataflows\helper;

/**
 * SFTP step trait.
 *
 * Uses phpseclib. See https://phpseclib.com
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait sftp_trait {

    /** @var string Shorthand sftp scheme for use in config. */
    protected static $sftpprefix = 'sftp';

    /** @var int Default port to connect to. */
    protected static $defaultport = 22;

    /** @var array SFTP objects to use for performance reasons. */
    protected $sftp = [];

    /**
     * Returns whether or not the step configured, has a side effect.
     *
     * @return     bool whether or not this step has a side effect
     * @link https://en.wikipedia.org/wiki/Side_effect_(computer_science)
     */
    public function has_side_effect(): bool {
        if (isset($this->stepdef)) {
            $config = $this->get_variables()->get('config');
            if (isset($config->target)) {
                return !helper::path_is_relative($config->target);
            }
        }
        return true;
    }

    /**
     * Return the definition of the fields available in this form.
     *
     * @param string $behaviour 'copy' or something else.
     * @return array
     */
    public static function form_define_fields($behaviour = 'copy'): array {
        $fields = [
            'host' => ['type' => PARAM_TEXT, 'required' => true],
            'port' => ['type' => PARAM_INT, 'required' => true, 'default' => self::$defaultport],
            'hostpubkey' => ['type' => PARAM_TEXT],
            'username' => ['type' => PARAM_TEXT, 'required' => true],
            'password' => ['type' => PARAM_TEXT, 'secret' => true],
            'privkeyfile' => ['type' => PARAM_TEXT],
        ];

        if ($behaviour === 'copy') {
            $fields = array_merge($fields, [
                'source' => ['type' => PARAM_TEXT, 'required' => true],
                'target' => ['type' => PARAM_TEXT, 'required' => true],
            ]);
        }

        return $fields;
    }

    /**
     * Core fields for this step
     *
     * @param \MoodleQuickForm $mform
     */
    public function form_add_core_inputs(\MoodleQuickForm &$mform) {
        $mform->addElement('text', 'config_host', get_string('connector_sftp:host', 'tool_dataflows'));
        $mform->addElement('static', 'config_host_desc', '',  get_string('connector_sftp:host_desc', 'tool_dataflows'));
        $mform->addElement('text', 'config_port', get_string('connector_sftp:port', 'tool_dataflows'));
        $mform->setDefault('config_port', self::$defaultport);
        $mform->addElement('text', 'config_hostpubkey', get_string('connector_sftp:hostpubkey', 'tool_dataflows'));
        $mform->addElement('static', 'config_hostpubkey_desc', '', get_string('connector_sftp:hostpubkey_desc', 'tool_dataflows'));

        $mform->addElement('text', 'config_username', get_string('username'));
        $mform->addElement('passwordunmask', 'config_password', get_string('password'));
        $mform->addElement('static', 'config_password_desc', '', get_string('connector_sftp:password_desc', 'tool_dataflows'));

        $mform->addElement('text', 'config_privkeyfile', get_string('connector_sftp:privkeyfile', 'tool_dataflows'));
        $mform->addElement('static', 'config_keyfile_desc', '', get_string('connector_sftp:keyfile_desc', 'tool_dataflows'));
    }

    /**
     * Custom elements for editing the connector.
     *
     * @param \MoodleQuickForm $mform
     * @param string $behaviour default to the step.
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform, $behaviour = 'copy') {
        $this->form_add_core_inputs($mform);

        if ($behaviour === 'copy') {
            $mform->addElement('text', 'config_source', get_string('connector_sftp:source', 'tool_dataflows'));
            $mform->addElement('static', 'config_source_desc', '',  get_string('connector_sftp:source_desc', 'tool_dataflows').
                \html_writer::nonempty_tag(
                    'pre',
                    get_string('connector_sftp:path_example', 'tool_dataflows'). get_string('path_help_examples', 'tool_dataflows')
                )
            );

            $mform->addElement('text', 'config_target', get_string('connector_sftp:target', 'tool_dataflows'));
            $mform->addElement('static', 'config_target_desc', '',  get_string('connector_sftp:target_desc', 'tool_dataflows').
                \html_writer::nonempty_tag(
                    'pre',
                    get_string('connector_sftp:path_example', 'tool_dataflows'). get_string('path_help_examples', 'tool_dataflows')
                )
            );
        }
    }

    /**
     * Validate the configuration settings.
     *
     * @param object $config
     * @param string $behaviour
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config, $behaviour = 'copy') {
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

        $noprivfile = empty($config->privkeyfile);

        // If no key file is given, then a password is required.
        if ($noprivfile && empty($config->password)) {
            $errors['config_password'] = get_string(
                'config_field_missing',
                'tool_dataflows',
                get_string('password'),
                true
            );
        }

        // Copy step checks.
        if ($behaviour === 'copy') {
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
        }

        $hasremote = true;
        // Check that at least one file config has an sftp:// scheme.
        if (!empty($config->source) && !empty($config->target)) {
            // Check if the source or target is an expression, and evaluate it if required.
            $sourceremote = helper::path_has_scheme($config->source, self::$sftpprefix);
            $targetremote = helper::path_has_scheme($config->target, self::$sftpprefix);
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
     * @param string $behaviour
     * @return true|array Will return true or an array of errors.
     */
    public function validate_for_run($behaviour = 'copy') {
        $config = $this->get_variables()->get('config');

        $errors = [];

        if ($behaviour === 'copy') {
            $error = helper::path_validate($config->source);
            if ($error !== true) {
                $errors['config_source'] = $error;
            }

            $error = helper::path_validate($config->target);
            if ($error !== true) {
                $errors['config_target'] = $error;
            }
        }
        if (!empty($config->privkeyfile)) {
            $error = helper::path_validate($config->privkeyfile);
            if ($error !== true) {
                $errors['config_privkeyfile'] = $error;
            }
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
        $stepvars = $this->get_variables();
        $config = $stepvars->get('config');

        // At this point we need to disconnect once we are finished.
        try {
            // Skip if it is a dry run.
            if ($this->is_dry_run() && $this->has_side_effect()) {
                return $input;
            }

            $sftp = $this->init_sftp($config);

            $sourceisremote = helper::path_is_scheme($config->source, self::$sftpprefix);
            $targetisremote = helper::path_is_scheme($config->target, self::$sftpprefix);
            $sourcepath = $this->resolve_path($config->source);
            $targetpath = $this->resolve_path($config->target);

            // Copying from remote to remote, but have to download it first.
            if ($sourceisremote && $targetisremote) {
                $this->copy_remote_to_remote($sftp, $sourcepath, $targetpath);
                return $input;
            }

            // Download from remote.
            if ($sourceisremote) {
                $this->download($sftp, $sourcepath, $targetpath);
                return $input;
            }

            // Upload to remote.
            $this->upload($sftp, $sourcepath, $targetpath);
        } catch (\Throwable $e) {
            if (isset($sftp)) {
                $sftp->disconnect();
            }
        }

        return $input;
    }

    /**
     * Hook function that gets called when an engine step has been aborted.
     */
    public function on_abort() {
        if (isset($this->sftp)) {
            foreach ($this->sftp as $s) {
                $s->disconnect();
            }
        }
    }

    /**
     * Hook function that gets called when an engine step has been finalised.
     */
    public function on_finalise() {
        if (isset($this->sftp)) {
            foreach ($this->sftp as $s) {
                $s->disconnect();
            }
        }
    }

    /**
     * Checks and loads the appropriate key, based on config
     *
     * @return AsymmetricKey|string
     */
    private function load_key() {
        // Because variables use redacted values by default, we evaluate the secret explicitly.
        $password = $this->get_variables()->evaluate($this->stepdef->config->password);

        $config = $this->stepdef->config;
        // Use key authorisation if privkeyfile is set.
        if (!empty($config->privkeyfile)) {
            $privkeycontents = file_get_contents($this->resolve_path($config->privkeyfile));
            $privkeypassphrase = $password ?: false;
            return PublicKeyLoader::load($privkeycontents, $privkeypassphrase);
        }

        // Fallback to password authorisation.
        return $password;
    }

    /**
     * Checks and verifies the public host key, setting it by default if empty
     *
     * @param SFTP $sftp
     * @param string $hostpubkey
     */
    private function check_public_host_key(SFTP $sftp, string $hostpubkey) {
        $serverpublichostkey = $sftp->getServerPublicHostKey();
        if ($serverpublichostkey === false) {
            throw new \moodle_exception('connector_sftp:bad_host', 'tool_dataflows');
        }

        // Compare and ensure stored and remote public host keys match.
        if (!empty($hostpubkey) && $hostpubkey !== $serverpublichostkey) {
            throw new \moodle_exception('connector_sftp:bad_hostpubkey', 'tool_dataflows');
        }

        if (empty($hostpubkey)) {
            $this->get_variables()->set('hostpubkey', $serverpublichostkey);
            $this->stepdef->set_config_by_name('hostpubkey', $serverpublichostkey);
            $this->stepdef->save();
        }
    }

    /**
     * Uploads a local file to a remote path
     *
     * @param SFTP $sftp
     * @param string $sourcepath
     * @param string $targetpath
     */
    private function upload(SFTP $sftp, string $sourcepath, string $targetpath) {
        $this->log("Uploading from '$sourcepath' to '$targetpath'");
        if (!$sftp->put($targetpath, $sourcepath, SFTP::SOURCE_LOCAL_FILE)) {
            throw new \moodle_exception('connector_sftp:copy_fail', 'tool_dataflows', '', $sftp->getLastSFTPError());
        }
    }

    /**
     * Downloads a remote file to a local path
     *
     * @param SFTP $sftp
     * @param string $sourcepath
     * @param string $targetpath
     */
    private function download(SFTP $sftp, string $sourcepath, string $targetpath) {
        $this->log("Downloading from '$sourcepath' to '$targetpath'");
        if (!$sftp->get($sourcepath, $targetpath)) {
            throw new \moodle_exception('connector_sftp:copy_fail', 'tool_dataflows', '', $sftp->getLastSFTPError());
        }
    }

    /**
     * Copies a file from one remote source to another location in the same remote source.
     *
     * @param SFTP $sftp
     * @param string $sourcepath
     * @param string $targetpath
     */
    private function copy_remote_to_remote(SFTP $sftp, string $sourcepath, string $targetpath) {
        $tmppath = $this->enginestep->engine->create_temporary_file();
        $this->download($sftp, $sourcepath, $tmppath);
        $this->upload($sftp, $tmppath, $targetpath);
    }

    /**
     * Lists files in a directory
     *
     * NOTE: no support for globbing on recursive files. Potentially expensive
     * if recursive flag is enabled all the time.
     *
     * @param SFTP $sftp
     * @param string $path
     * @param bool $recursive
     * @return array
     */
    private function list(SFTP $sftp, string $path, $recursive = false): array {
        return $sftp->nlist($path, $recursive) ?: [];
    }

    /**
     * Resolve a path for SFTP. Either a remote SFTP file name or a local path to be resolved normally.
     *
     * @param string $pathname
     * @return string
     */
    public function resolve_path(string $pathname): string {
        if (helper::path_is_scheme($pathname, self::$sftpprefix)) {
            return substr($pathname, strlen(self::$sftpprefix) + strlen('://'));
        }
        return $this->enginestep->engine->resolve_path($pathname);
    }

    /**
     * Initialise the SFTP object and perform an initial connection test
     *
     * @param object $config
     * @return SFTP
     */
    private function init_sftp($config): SFTP {
        // Use existing cached SFTP object if available.
        $cachekey = implode('|', [$config->host, $config->port, $config->username]);
        if (isset($this->sftp[$cachekey])) {
            return $this->sftp[$cachekey];
        }

        // Create and connect to SFTP.
        $this->log->debug("Connecting to {$config->host}:{$config->port}");
        $sftp = new SFTP($config->host, $config->port);
        $this->check_public_host_key($sftp, $config->hostpubkey);

        $key = $this->load_key();
        if (!$sftp->login($config->username, $key)) {
            throw new \moodle_exception('connector_sftp:bad_auth', 'tool_dataflows');
        }

        $sftp->enableDatePreservation();

        // Cache sftp since it takes a while to attempt initial connection.
        $this->sftp[$cachekey] = $sftp;

        return $sftp;
    }
}
