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
class flow_web_service extends flow_step {

    /** @var int[] number of output flows (min, max). */
    protected $outputflows = [0, 1];

    /** @var bool whether or not this step type (potentially) has side effects, this will vary depending on Web Service */
    protected $hassideeffect = true;

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'webservice' => ['type' => PARAM_RAW_TRIMMED, 'required' => true],
            'user' => ['type' => PARAM_TEXT, 'required' => true],
            'parameters' => ['type' => PARAM_RAW],
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
     * @param \MoodleQuickForm $mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {
        $options = [
            'multiple' => false,
            'noselectionstring' => get_string('flow_web_service:selectuser', 'tool_dataflows'),
            'placeholder' => get_string('flow_web_service:selectuser', 'tool_dataflows'),
        ];

        $class = ['class' => 'badge badge-dark rounded-0'];
        $outputexample['somekey'] = \html_writer::nonempty_tag('span', 'steps.' . ($this->stepdef->alias ?? 'alias') . '.somekey',
            $class);
        $outputexample['id'] = \html_writer::nonempty_tag('span', 'steps.' . ($this->stepdef->alias ?? 'alias') . '.id', $class);
        $outputexample['username'] = \html_writer::nonempty_tag('span', 'steps.' . ($this->stepdef->alias ?? 'alias') . '.username',
            $class);

        $yaml = <<<EOF
users:
  - username: john1234
    password: Pwdtest123*&12
    firstname: john
    lastname: doe
    email: john@doe.ca
EOF;

        $examples['yaml'] = \html_writer::nonempty_tag('pre', $yaml);

        $mform->addElement('text', 'config_webservice', get_string('flow_web_service:webservice', 'tool_dataflows'));
        $mform->addElement('static', 'config_webservice_help', '',
            get_string('flow_web_service:webservice_help', 'tool_dataflows'));
        $mform->addElement('text', 'config_user', get_string('flow_web_service:user', 'tool_dataflows'));
        $mform->addHelpButton('config_user', 'flow_web_service:user', 'tool_dataflows');
        $mform->addElement('textarea', 'config_parameters', get_string('flow_web_service:parameters', 'tool_dataflows'),
            ['cols' => 50, 'rows' => 7]);
        $mform->addHelpButton('config_parameters', 'flow_web_service:parameters', 'tool_dataflows');
        $mform->addElement('static', 'parameters_help', '',
            get_string('flow_web_service:field_parameters_help', 'tool_dataflows', $examples));
        $mform->addElement('select', 'config_failure', get_string('flow_web_service:failure', 'tool_dataflows'),
            [
                'abortstep' => get_string('flow_web_service:abortstep', 'tool_dataflows'),
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
        if ($config->user) {
            // Gets user count and checks whether deleted and confirmed.
            $user = get_users(false, '', true, null, 'firstname ASC', '', '', '', '', '*', 'username = :username',
                ['username' => $config->user]);
            if (!$user) {
                $errors['config_user'] = get_string('config_user_invalid', 'tool_dataflows', $config->user, true);
            }
        }
        if ($config->failure === 'record' && empty($config->path)) {
            $errors['config_path'] = get_string('config_field_missing', 'tool_dataflows', 'path', true);
        }
        return empty($errors) ? true : $errors;
    }

    /**
     * Executes the step
     *
     * Performs web service call.
     *
     * @param   mixed $input
     * @return  mixed $input
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
        $userid = $DB->get_field('user', 'id', ['username' => $config->user]);
        $user = \core_user::get_user($userid);
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
        // Check for any data in input to replace.
        $replacement = $parser->evaluate_recursive($params, ['data' => $input]);
        // Ensure structures are arrays for webservice.
        $replacement = json_decode(json_encode($replacement), true);
        $params = array_replace_recursive($params, $replacement);
        // If password is need will throw exception.
        array_walk_recursive($params, function (&$item) {
            $item = strtolower($item);
        });
        $failure = !isset($config->failure) ? 'abortflow' : $config->failure;

        if (external_api::external_function_info($config->webservice)->type === 'read') {
            $this->hassideeffect = false;
        };

        if (!$isdryrun) {
            $response = external_api::call_external_function($config->webservice, $params);
            // Restore the previous user to avoid any side-effects occuring in later steps / code.
            // Avoid moodle state errors because of webservice call - we are still in body.
            \core\session\manager::set_user($previoususer);
            $SESSION = $session;
            $OUTPUT = $currentoutput;
            if ($response['error']) {
                if (!$outertransaction && $DB->is_transaction_started()) {
                    $DB->force_transaction_rollback();
                }
                if ($failure === 'abortflow') {
                    throw new \moodle_exception($response['exception']->debuginfo ?? $response['exception']->message);
                }
                if ($failure === 'abortstep') {
                    $this->enginestep->log($response['exception']->debuginfo ?? $response['exception']->message);
                    return false;
                }
            }
            // Success - store any desired value for subsequent steps.
            // Output will then evaluate result.
            $this->set_variables('result', $response['data']);
        }
        return $input;
    }
}
