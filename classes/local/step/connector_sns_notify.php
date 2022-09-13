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

use Aws\Exception\AwsException;

/**
 * Amazon SNS event notification step.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connector_sns_notify extends connector_step {

    /** @var bool whether or not this step type (potentially) contains a side effect or not */
    protected $hassideeffect = true;

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'region'  => ['type' => PARAM_TEXT, 'required' => true],
            'key'     => ['type' => PARAM_TEXT], // Empty if using sdk credentials.
            'secret'  => ['type' => PARAM_TEXT, 'secret' => true],
            'topic'   => ['type' => PARAM_TEXT, 'required' => true],
            'message' => ['type' => PARAM_TEXT, 'required' => true],
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
        $mform->addElement('text', 'config_region', get_string('connector_s3:region', 'tool_dataflows'));
        $mform->addElement('text', 'config_key', get_string('connector_s3:key', 'tool_dataflows'));
        $mform->addElement('passwordunmask', 'config_secret', get_string('connector_s3:secret', 'tool_dataflows'));
        $mform->addElement('text', 'config_topic', get_string('connector_sns_notify:topic', 'tool_dataflows'));
        $mform->addElement('textarea', 'config_message',
                get_string('connector_sns_notify:message', 'tool_dataflows'), ['cols' => 50, 'rows' => 7]);
    }

    /**
     * Executes the step
     *
     * This will take the input and perform S3 interaction functions.
     *
     * @return bool Returns true if successful, false otherwise.
     */
    public function execute($input = null) {
        global $CFG;

        try {
            // Only autoload the AWS SDK at runtime.
            require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');
        } catch (\Exception $e) {
            // TODO specific exception.
            $this->enginestep->log(get_string('local_aws_missing', 'tool_dataflows'));
            return false;
        }

        // Do not execute operations during a dry run.
        if ($this->enginestep->engine->isdryrun) {
            $this->enginestep->log('Do not send SNS notification as this is a dry run.');
            return true;
        }

        // Create the client.
        $config = $this->enginestep->stepdef->config;
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
            $snsclient = \local_aws\local\client_factory::get_client('\Aws\Sns\SnsClient', $connectionoptions);
        } catch (\Exception $e) {
            // TODO specific exception.
            $this->enginestep->log(get_string('s3_configuration_error', 'tool_dataflows'));
            return false;
        }

        // Send the message.
        try {
            // Obtain the ARN for the topic. Create the topic if not already existing.
            $result = $snsclient->createTopic(['Name' => $config->topic]);
            $topic = $result['TopicArn'];

            $this->enginestep->log(get_string('connector_sns_notify:sending_message', 'tool_dataflows', $topic));
            $this->enginestep->log($config->message);

            $snsclient->publish([
                'Message' => $config->message,
                'TopicArn' => $topic,
            ]);
        } catch (AwsException $e) {
            $this->enginestep->log($e->getMessage());
            return false;
        }

        return true;
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
            'region',
            'key',
            'secret',
            'topic',
            'message',
        ] as $field) {
            if (empty($config->$field)) {
                $errors["config_$field"] = get_string('config_field_missing', 'tool_dataflows', "$field", true);
            }
        }

        return empty($errors) ? true : $errors;
    }
}
