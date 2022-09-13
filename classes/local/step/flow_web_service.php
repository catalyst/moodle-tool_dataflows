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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use core_user;
use core\session\manager;
use external_api;
use moodle_exception;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Yaml\Yaml;
use Throwable;
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
        global $DB, $CFG;

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

        require_once($CFG->dirroot . "/webservice/lib.php");
        $webservicemanager = new \webservice();
        $functions = $webservicemanager->get_not_associated_external_functions($data['id']);

        // List of tjhe web services functions.
        $options = [];
        foreach ($functions as $functionid => $functionname) {
            $function = external_api::external_function_info($functionname);
            if (empty($function->deprecated)) {
                $options[$function->name] = $function->name . ': ' . $function->description;
            }
        }
        $mform->addElement('searchableselector', 'config_webservice', get_string('webservice', 'webservice'), $options, ['']);

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
                'abortflow' => get_string('flow_web_service:abortflow', 'tool_dataflows'),
                'skiprecord' => get_string('flow_web_service:skiprecord', 'tool_dataflows'),
            ]
        );
        $mform->addHelpButton('config_failure', 'flow_web_service:failure', 'tool_dataflows');

        $mform->addElement('text' , 'config_path', get_string('flow_web_service:path', 'tool_dataflows'));
        $mform->hideIf('config_path', 'config_failure', 'neq', 'skiprecord');
        $mform->disabledIf('config_path', 'config_failure', 'neq', 'skiprecord');
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
    public function execute($input = null) {
        global $DB, $SESSION, $USER, $OUTPUT;
        // Store the previous user and session, setting it back once the step is finished.
        $previoususer = $USER;
        $session = $SESSION;
        $currentoutput = $OUTPUT;
        $outertransaction = $DB->is_transaction_started();

        $config = $this->get_config();
        $isdryrun = $this->is_dry_run();
        $userid = $DB->get_field('user', 'id', ['username' => $config->user]);
        $user = core_user::get_user($userid);
        $user->ignoresesskey = true;
        manager::init_empty_session();
        manager::set_user($user);
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

        if (!$isdryrun || !$this->hassideeffect) {
            $response = static::call_external_function($config->webservice, $params);
            // Restore the previous user to avoid any side-effects occuring in later steps / code.
            // Avoid moodle state errors because of webservice call - we are still in body.
            manager::set_user($previoususer);
            $SESSION = $session;
            $OUTPUT = $currentoutput;
            if ($response['error']) {
                if (!$outertransaction && $DB->is_transaction_started()) {
                    $DB->force_transaction_rollback();
                }
                if ($failure === 'abortflow') {
                    throw new moodle_exception($response['exception']->debuginfo ?? $response['exception']->message);
                }
                if ($failure === 'skiprecord') {
                    $this->enginestep->log('Warn skipping record: ' .
                        $response['exception']->debuginfo ?? $response['exception']->message);
                    return false;
                }
            }
            // Success - store any desired value for subsequent steps.
            // Output will then evaluate result.
            $this->set_variables('result', $response['data']);
        }
        return $input;
    }

    /**
     * This function is a replication of external api call_external_function, to bypass cron job servicerequireslogin
     * issues.
     *
     * Call an external function validating all params/returns correctly.
     *
     * Note that an external function may modify the state of the current page, so this wrapper
     * saves and restores tha PAGE and COURSE global variables before/after calling the external function.
     *
     * @param string $function A webservice function name.
     * @param array $args Params array (named params)
     * @return array containing keys for error (bool), exception and data.
     */
    public static function call_external_function($function, $args) {
        global $PAGE, $COURSE, $CFG, $SITE;

        require_once($CFG->libdir . "/pagelib.php");

        $externalfunctioninfo = external_api::external_function_info($function);

        $currentpage = $PAGE;
        $currentcourse = $COURSE;
        $response = [];

        try {
            // Taken straight from from setup.php.
            if (!empty($CFG->moodlepageclass)) {
                if (!empty($CFG->moodlepageclassfile)) {
                    require_once($CFG->moodlepageclassfile);
                }
                $classname = $CFG->moodlepageclass;
            } else {
                $classname = 'moodle_page';
            }
            $PAGE = new $classname();
            $COURSE = clone($SITE);

            // Do not allow access to write or delete webservices as a public user.
            if ($externalfunctioninfo->loginrequired && !WS_SERVER) {
                if (!isloggedin()) {
                    throw new moodle_exception('servicerequireslogin', 'webservice');
                } else {
                    require_sesskey();
                }
            }
            // Validate params, this also sorts the params properly, we need the correct order in the next part.
            $callable = [$externalfunctioninfo->classname, 'validate_parameters'];
            $params = call_user_func($callable,
                                     $externalfunctioninfo->parameters_desc,
                                     $args);
            $params = array_values($params);

            // Allow any Moodle plugin a chance to override this call. This is a convenient spot to
            // make arbitrary behaviour customisations. The overriding plugin could call the 'real'
            // function first and then modify the results, or it could do a completely separate
            // thing.
            $callbacks = get_plugins_with_function('override_webservice_execution');
            $result = false;
            foreach ($callbacks as $plugintype => $plugins) {
                foreach ($plugins as $plugin => $callback) {
                    $result = $callback($externalfunctioninfo, $params);
                    if ($result !== false) {
                        break 2;
                    }
                }
            }

            // If the function was not overridden, call the real one.
            if ($result === false) {
                $callable = [$externalfunctioninfo->classname, $externalfunctioninfo->methodname];
                $result = call_user_func_array($callable, $params);
            }

            // Validate the return parameters.
            if ($externalfunctioninfo->returns_desc !== null) {
                $callable = [$externalfunctioninfo->classname, 'clean_returnvalue'];
                $result = call_user_func($callable, $externalfunctioninfo->returns_desc, $result);
            }

            $response['error'] = false;
            $response['data'] = $result;
        } catch (Throwable $e) {
            $exception = get_exception_info($e);
            unset($exception->a);
            $exception->backtrace = format_backtrace($exception->backtrace, true);
            if (!debugging('', DEBUG_DEVELOPER)) {
                unset($exception->debuginfo);
                unset($exception->backtrace);
            }
            $response['error'] = true;
            $response['exception'] = $exception;
            // Do not process the remaining requests.
        }

        $PAGE = $currentpage;
        $COURSE = $currentcourse;

        return $response;
    }

    /**
     * A list of outputs and their description if applicable.
     *
     * These fields can be used as aliases in the custom output mapping
     *
     * @return  array of outputs
     */
    public function define_outputs(): array {
        return [
            'result' => ['*' => null],
        ];
    }
}
