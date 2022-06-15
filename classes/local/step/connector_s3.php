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
use tool_dataflows\local\execution\engine_step;

/**
 * S3 connector step type
 *
 * @package    tool_dataflows
 * @author     Peter Burnettt <peterburnett@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connector_s3 extends connector_step {

    /** @var bool whether or not this step type (potentially) contains a side effect or not */
    protected $hassideeffect = true;

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    protected static function form_define_fields(): array {
        return [
            'bucket'            => ['type' => PARAM_TEXT],
            'region'            => ['type' => PARAM_TEXT],
            'key'               => ['type' => PARAM_TEXT],
            'secret'            => ['type' => PARAM_TEXT],
            'source'            => ['type' => PARAM_TEXT],
            'target'            => ['type' => PARAM_TEXT],
            'sourceremote'      => ['type' => PARAM_BOOL]
        ];
    }

    /**
     * Executes the step
     *
     * This will take the input and perform S3 interaction functions.
     *
     * @param engine_step $enginestep the execution engine parent step context
     * @return bool Returns true if successful, false otherwise.
     */
    public function execute(engine_step $enginestep): bool {
        global $CFG;
        // Engine step contains the execution context, configuration, variables etc.
        $this->enginestep = $enginestep;

        try {
            // Only autoload the AWS SDK at runtime.
            require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');
        } catch (\Exception $e) {
            // TODO specific exception.
            $this->enginestep->log(get_string('local_aws_missing', 'tool_dataflows'));
            return false;
        }

        $config = $this->enginestep->stepdef->config;
        $connectionoptions = [
            'version' => 'latest',
            'region' => $config->region
        ];
        if ($config->key !== '') {
            $connectionoptions['credentials'] = [
                'key' => $config->key,
                'secret' => $config->secret
            ];
        }

        try {
            $s3client = \local_aws\local\client_factory::get_client('\Aws\S3\S3Client', $connectionoptions);
        } catch (\Exception $e) {
            // TODO specific exception.
            $this->enginestep->log(get_string('s3_configuration_error', 'tool_dataflows'));
            return false;
        }

        // Copy TO remote.
        if (!$config->sourceremote) {

            @$stream = fopen($config->source, 'r');
            if ($stream === false) {
                $this->enginestep->log(get_string('missing_source_file', 'tool_dataflows'));
                return false;
            }

            try {
                $s3client->putObject([
                    'Bucket' => $config->bucket,
                    'Body' => $stream,
                    'Key' => $config->target
                ]);
            } catch (\Aws\S3\Exception\S3Exception $e) {
                $this->enginestep->log(get_string('s3_copy_failed', 'tool_dataflows'));
                fclose($stream);
                return false;
            }
        } else {
            // copy FROM remote.
            try {
                $s3client->getObject([
                    'Bucket' => $config->bucket,
                    'Key' => $config->source,
                    'SaveAs' => $config->target
                ]);
            } catch (\Aws\S3\Exception\S3Exception $e) {
                $this->enginestep->log(get_string('s3_copy_failed', 'tool_dataflows'));
                return false;
            }
        }

        return true;
    }
}
