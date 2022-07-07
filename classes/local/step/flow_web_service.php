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

use external_api;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Yaml\Yaml;
use tool_dataflows\parser;

/**
 * Flow web service
 *
 * @package    tool_dataflows
 * @author     Ghaly Marc-Alexandre <marc-alexandreghaly@catalyst-ca.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow_web_service extends flow_logic_step {

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    protected static function form_define_fields(): array {
        return [
            'webservice' => ['type' => PARAM_RAW_TRIMMED, 'required' => true],
            'user' => ['type' => PARAM_TEXT, 'required' => true],
            'parameters' => ['type' => PARAM_RAW],
            'datastore' => ['type' => PARAM_RAW_TRIMMED],
            'failure' => ['type' => PARAM_TEXT, 'required' => true],
            'path' => ['type' => PARAM_PATH],
        ];
    }

    /**
     * Allows each step type to determine a list of optional/required form
     * inputs for their configuration
     *
     * It's recommended you prefix the additional config related fields to avoid
     * conflicts with any existing fields.
     *
     * @param \MoodleQuickForm &$mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {
        $users = get_users(true, '', true, null, 'firstname ASC', '', '', '', 25, 'id, firstname, lastname, email');
        $visibleusers = [];
        foreach ($users as $user) {
            $visibleusers[$user->id] = $user->firstname . ' ' . $user->lastname;
        }
        $options = [
            'multiple' => false,
            'noselectionstring' => get_string('flow_web_service:selectuser', 'tool_dataflows'),
            'placeholder' => get_string('flow_web_service:selectuser', 'tool_dataflows'),
        ];

        $jsonexample = [
            'users' => [
                0 => [
                    'username' => 'john1234',
                    'password' => 'Pwdtest123*&12',
                    'firstname' => 'john',
                    'lastname' => 'doe',
                    'email' => 'john@doe.ca',
                ],
            ],
        ];
        $examples['yaml'] = \html_writer::nonempty_tag('pre', Yaml::dump($jsonexample, 3));
        $examples['json'] = \html_writer::nonempty_tag('pre', json_encode($jsonexample, JSON_PRETTY_PRINT));
        $mform->addElement('text', 'config_webservice', get_string('flow_web_service:webservice', 'tool_dataflows'));
        $mform->addElement('static', 'config_webservice_help', '',
            get_string('flow_web_service:webservice_help', 'tool_dataflows'));
        $mform->addElement('autocomplete', 'config_user',
            get_string('flow_web_service:user', 'tool_dataflows'), $visibleusers, $options);
        $mform->addElement('textarea', 'config_parameters', get_string('flow_web_service:parameters', 'tool_dataflows'),
            ['cols' => 50, 'rows' => 7]);
        $mform->addHelpButton('config_parameters', 'flow_web_service:parameters', 'tool_dataflows');
        $mform->addElement('static', 'parameters_help', '',
            get_string('flow_web_service:field_parameters_help', 'tool_dataflows', $examples));
        $mform->addElement('text', 'config_datastore', get_string('flow_web_service:datastore', 'tool_dataflows'));
        $mform->addHelpButton('config_datastore', 'flow_web_service:datastore', 'tool_dataflows');
        $mform->addElement('static', 'datastore_example', '', get_string('flow_web_service:datastore_example', 'tool_dataflows',
            $this->stepdef->alias ?? 'alias'));
        $mform->addElement('select', 'config_failure', get_string('flow_web_service:failure', 'tool_dataflows'),
            [
                'abortstep' => get_string('flow_web_service:abortstep', 'tool_dataflows'),
                'record' => get_string('flow_web_service:recordfailure', 'tool_dataflows'),
                'abortflow' => get_string('flow_web_service:abortflow', 'tool_dataflows'),
            ]
        );
        $mform->addHelpButton('config_failure', 'flow_web_service:failure', 'tool_dataflows');
        $mform->addElement('text' , 'config_path', get_string('flow_web_service:path', 'tool_dataflows'));
        $mform->hideIf('config_path', 'config_failure', 'neq', 'record');
        $mform->disabledIf('config_path', 'config_failure', 'neq', 'record');
    }

    /**
     * Validate the configuration settings.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        $errors = [];
        if (empty($config->webservice)) {
            $errors['config_webservice'] = get_string('config_field_missing', 'tool_dataflows', 'webservice', true);
        }
        if (empty($config->user)) {
            $errors['config_user'] = get_string('config_field_missing', 'tool_dataflows', 'user', true);
        }
        if ($config->failure === 'record' && empty($config->path)) {
            $errors['config_path'] = get_string('config_field_missing', 'tool_dataflows', 'path', true);
        }
        return empty($errors) ? true : $errors;
    }

    /**
     * Returns an array value for given key.
     * @param array $array
     * @param string $needle
     * @return array|bool
     */
    public function array_search_key($array, $needle) {
        foreach ($array as $key => $value) {
            if ($key == $needle) {
                return $value;
            }
            if (is_array($value)) {
                if (($result = $this->array_search_key($value, $needle)) !== false) {
                    return $result;
                }
            }
        }
        return false;
    }

    /**
     * Executes the step
     *
     * Performs web service call.
     *
     * @param object|mixed $input
     * @return bool Returns true if successful, false otherwise.
     */
    public function execute($input) {
        global $DB, $SESSION, $USER, $OUTPUT;
        // Store the previous user and session, setting it back once the step is finished.
        $previoususer = $USER;
        $session = $SESSION;
        $currentoutput = $OUTPUT;
        $outertransaction = $DB->is_transaction_started();

        $config = $this->enginestep->stepdef->config;
        $isdryrun = $this->enginestep->engine->isdryrun;
        $functionname = $config->webservice;

        $user = \core_user::get_user($config->user);
        $user->ignoresesskey = true;
        \core\session\manager::init_empty_session();
        \core\session\manager::set_user($user);
        set_login_session_preferences();

        // Fake it till you make it - set the the lastaccess in advance to avoid
        // this value being updated in the database via user_accesstime_log() as
        // we are not actually logging in and accessing the site as this user.
        $USER->lastaccess = time();

        $params = json_decode($config->parameters, true);
        if (is_null($params)) {
            $params = Yaml::parse($config->parameters);
        }
        // Updates parameters passed to WS.
        $parser = new parser();
        $replacement = $parser->array_evaluate_recursive($params, $input);
        $params = array_replace_recursive($params, $replacement);
        $failure = !isset($config->failure) ? 'abortflow' : $config->failure;
        $path = $config->path ?? null;
        if ($path) {
            if ($path[0] === '/') {
                $path = ltrim($path, '/');
            }
        }

        if (!$isdryrun) {
            $response = external_api::call_external_function($functionname, $params);

            if ($response['error']) {
                // Restore the previous user to avoid any side-effects occuring in later steps / code.
                // Avoid moodle state errors because of webservice call - we are still in body.
                \core\session\manager::set_user($previoususer);
                $SESSION = $session;
                $OUTPUT = $currentoutput;

                // Throw an exception to be propagated for proper error capture.
                if (!$outertransaction && $DB->is_transaction_started()) {
                    $DB->force_transaction_rollback();
                }
                if ($failure === 'abortflow') {
                    throw new \moodle_exception($response['exception']->debuginfo);
                }
                if ($failure === 'abortstep') {
                    $this->enginestep->log($response['exception']->debuginfo);
                    return false;
                }
                if ($failure === 'record') {
                    $destination = $this->enginestep->engine->resolve_path($path);
                    file_put_contents($destination, $response['exception']);
                    return false;
                }
            }
            // Success - store any desired value for subsequent steps.
            if (!empty($config->datastore)) {
                $responsekeys = explode(',', $config->datastore);
                foreach ($responsekeys as $key) {
                    $value = $this->array_search_key($response['data'], $key);
                    $value = $value[$key];
                    $key = $this->stepdef->alias . '_' . $key;
                    $input->{$key} = $value;
                }
            }
            // Restore the previous user to avoid any side-effects occuring in later steps / code.
            \core\session\manager::set_user($previoususer);
            $SESSION = $session;
            $OUTPUT = $currentoutput;
        } else {
            // Restore the previous user to avoid any side-effects occuring in later steps / code.
            \core\session\manager::set_user($previoususer);
            $SESSION = $session;
            $OUTPUT = $currentoutput;
        }
        return true;
    }
}
