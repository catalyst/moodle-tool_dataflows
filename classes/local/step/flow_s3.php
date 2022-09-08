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

use tool_dataflows\helper;

/**
 * S3 flow step
 *
 * @package    tool_dataflows
 * @author     Peter Burnettt <peterburnett@catalyst-au.net>
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow_s3 extends flow_step {

    /** @var int[] number of output flows (min, max). */
    protected $outputflows = [0, 1];

    /** @var int[] number of output connectors (min, max). */
    protected $outputconnectors = [0, 1];

    /** @var string the prefix identifier for an s3 path, e.g. s3://path/to/file. */
    const S3_PREFIX = 's3://';

    /**
     * Returns whether or not the step configured, has a side effect.
     *
     * For s3 connectors, it is considered to have a side effect if the target is
     * anywhere outside of the scratch directory.
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
            'bucket'            => ['type' => PARAM_TEXT, 'required' => true],
            'region'            => ['type' => PARAM_TEXT, 'required' => true],
            'key'               => ['type' => PARAM_TEXT], // Empty if using sdk credentials.
            'secret'            => ['type' => PARAM_TEXT, 'secret' => true],
            'source'            => ['type' => PARAM_TEXT, 'required' => true],
            'target'            => ['type' => PARAM_TEXT, 'required' => true],
        ];
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
        $mform->addElement('text', 'config_bucket', get_string('connector_s3:bucket', 'tool_dataflows'));
        $mform->addElement('text', 'config_region', get_string('connector_s3:region', 'tool_dataflows'));
        $mform->addElement('text', 'config_key', get_string('connector_s3:key', 'tool_dataflows'));
        $mform->addElement('passwordunmask', 'config_secret', get_string('connector_s3:secret', 'tool_dataflows'));
        $mform->addElement('text', 'config_source', get_string('connector_s3:source', 'tool_dataflows'), ['size' => '50']);
        $mform->addElement('static', 'config_json_path_help', '', get_string('connector_s3:source_help', 'tool_dataflows').
            \html_writer::nonempty_tag('pre', get_string('connector_s3:path_example', 'tool_dataflows').
            get_string('path_help_examples', 'tool_dataflows')));
        $mform->addElement('text', 'config_target', get_string('connector_s3:target', 'tool_dataflows'), ['size' => '50']);
        $mform->addElement('static', 'config_json_path_help', '', get_string('connector_s3:target_help', 'tool_dataflows').
            \html_writer::nonempty_tag('pre', get_string('connector_s3:path_example', 'tool_dataflows').
            get_string('path_help_examples', 'tool_dataflows')));
    }

    /**
     * Executes the step
     *
     * This will take the input and perform S3 interaction functions.
     *
     * @return mixed
     */
    public function execute($input = null) {
        global $CFG;
        // Engine step contains the execution context, configuration, variables etc.

        try {
            // Only autoload the AWS SDK at runtime.
            require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');
        } catch (\Exception $e) {
            // TODO specific exception.
            $this->enginestep->log(get_string('local_aws_missing', 'tool_dataflows'));
            return $input;
        }

        $config = $this->get_config();
        $connectionoptions = [
            'version' => 'latest',
            'region' => $config->region,
        ];
        if ($config->key !== '') {
            $connectionoptions['credentials'] = [
                'key' => $config->key,
                'secret' => $config->secret,
            ];
        }

        try {
            $s3client = \local_aws\local\client_factory::get_client('\Aws\S3\S3Client', $connectionoptions);
        } catch (\Exception $e) {
            // TODO specific exception.
            $this->enginestep->log(get_string('s3_configuration_error', 'tool_dataflows'));
            return $input;
        }

        // Check source path.
        $sourceins3 = $this->has_s3_path($config->source);
        $source = $this->resolve_path($config->source, $sourceins3);

        // Resolve target path.
        $targetins3 = $this->has_s3_path($config->target);
        $target = $this->resolve_path($config->target, $targetins3);

        // Fix target (check if it is a directory, and use the source's basename).
        if (substr($target, -1) === '/') {
            $target .= basename($source);
        }

        // Do not execute s3 operations during a dry run.
        if ($this->enginestep->engine->isdryrun) {
            $this->enginestep->log("Skipping copy to '{$target}' as this is a dry run.");
            return $input;
        }

        // PUT - Handle local source file to s3 path.
        if (!$sourceins3 && $targetins3) {
            @$stream = fopen($source, 'r');
            if ($stream === false) {
                $this->enginestep->log(get_string('missing_source_file', 'tool_dataflows'));
                return $input;
            }

            try {
                $s3client->putObject([
                    'Bucket' => $config->bucket,
                    'Body' => $stream,
                    'Key' => $target,
                ]);
            } catch (\Aws\S3\Exception\S3Exception $e) {
                $this->enginestep->log(get_string('s3_copy_failed', 'tool_dataflows', $e->getAwsErrorMessage()));
                return $input;
            } finally {
                fclose($stream);
            }
        }

        // GET - Handle s3 path to local source file.
        if ($sourceins3 && !$targetins3) {
            // Copy FROM remote.
            try {
                $s3client->getObject([
                    'Bucket' => $config->bucket,
                    'Key' => $source,
                    'SaveAs' => $target,
                ]);
            } catch (\Aws\S3\Exception\S3Exception $e) {
                $this->enginestep->log(get_string('s3_copy_failed', 'tool_dataflows', $e->getAwsErrorMessage()));
                return $input;
            }
        }

        // COPY - Handle s3 to s3 copying.
        // Ref: https://docs.aws.amazon.com/code-samples/latest/catalog/php-s3-s3-copying-objects.php.html.
        if ($sourceins3 && $targetins3) {
            try {
                $s3client->copyObject([
                    'Bucket'     => $config->bucket,
                    'CopySource' => "{$config->bucket}/{$source}",
                    'Key'        => $target,
                ]);
            } catch (\Aws\S3\Exception\S3Exception $e) {
                $this->enginestep->log(get_string('s3_copy_failed', 'tool_dataflows', $e->getAwsErrorMessage()));
                return $input;
            }
        }

        return $input;
    }

    /**
     * Resolves the path provided, based on whether or not it lives in s3.
     *
     * @param   string $path
     * @param   bool $ins3 whether or not the path is a location in s3.
     * @return  string resolved path
     */
    public function resolve_path(string $path, bool $ins3): string {
        // S3 Path: when the path is in s3, trim the prefix.
        if ($ins3) {
            // The path returned is the substring starting from where the prefix ends.
            return substr($path, strlen(self::S3_PREFIX));
        }

        // Local/other path: resolved using the engine's resolve path method.
        return $this->enginestep->engine->resolve_path($path);
    }

    /**
     * Validate the configuration settings.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        $errors = [];

        // Check mandatory fields.
        foreach ([
            'bucket',
            'region',
            'source',
            'target',
        ] as $field) {
            if (empty($config->$field)) {
                $errors["config_$field"] = get_string('config_field_missing', 'tool_dataflows', "$field", true);
            }
        }

        // Ensure the source is a file, not a directory (for now).
        if (!$this->has_s3_path($config->source) && is_dir($config->source)) {
            $errormsg = get_string('connector_s3:source_is_a_directory', 'tool_dataflows', null, true);
            $errors['config_source'] = $errormsg;
        }

        // Check source/target has an s3:// path set once both source and targets have been set.
        if (!empty($config->source) && !empty($config->target)) {
            // Check if the source or target is an expression, and evaluate it if required.
            $sourceins3 = $this->has_s3_path($config->source);
            $targetins3 = $this->has_s3_path($config->target);
            $hass3path = $sourceins3 || $targetins3;
        }
        if (isset($hass3path) && $hass3path === false) {
            $errormsg = get_string('connector_s3:missing_s3_source_or_target', 'tool_dataflows', null, true);
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

        $error = helper::path_validate($config->source);
        if ($error !== true) {
            $errors['config_source'] = $error;
        }

        $error = helper::path_validate($config->target);
        if ($error !== true) {
            $errors['config_target'] = $error;
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Returns whether the path is marked as being in s3
     *
     * @param   string $path
     * @return  bool
     */
    public function has_s3_path(string $path) {
        return strpos($path, self::S3_PREFIX) === 0;
    }
}
