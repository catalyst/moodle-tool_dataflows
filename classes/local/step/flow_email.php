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

use tool_dataflows\parser;

/**
 * Email notification step.
 *
 * @package   tool_dataflows
 * @author    Brendan Heywood <brendan@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow_email extends flow_step {

    /** @var bool whether or not this step type (potentially) contains a side effect or not */
    protected $hassideeffect = true;

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'to'      => ['type' => PARAM_TEXT, 'required' => true],
            'name'    => ['type' => PARAM_TEXT],
            'subject' => ['type' => PARAM_TEXT, 'required' => true],
            'message' => ['type' => PARAM_TEXT, 'required' => true],
        ];
    }

    /**
     * Email config fields.
     *
     * @param \MoodleQuickForm $mform
     */
    public function form_add_custom_inputs(\MoodleQuickForm &$mform) {
        $mform->addElement('text', 'config_to', get_string('flow_email:to', 'tool_dataflows'));
        $mform->addElement('text', 'config_name', get_string('flow_email:name', 'tool_dataflows'));
        $mform->addElement('text', 'config_subject', get_string('flow_email:subject', 'tool_dataflows'), 'size=50');
        $mform->addElement('textarea', 'config_message',
                get_string('flow_email:message', 'tool_dataflows'), ['cols' => 50, 'rows' => 10]);
    }

    /**
     * Sends the email.
     *
     * @param   mixed $input
     * @return  mixed $input
     */
    public function execute($input = null) {

        // Do not execute operations during a dry run.
        if ($this->enginestep->engine->isdryrun) {
            $this->enginestep->log('Do not send email notification as this is a dry run.');
            return true;
        }

        $config = $this->enginestep->stepdef->config;

        $parser = new parser();
        $parser->evaluate_recursive($config, ['data' => $input]);

        $toemail = $config->to;

        // First try to match the email with a Moodle user.
        $to = \core_user::get_user_by_email($toemail);

        // Otherwise send it with a dummy account.
        if (!$to) {
            $to = \core_user::get_noreply_user();
            $to->email = $toemail;
            $to->firstname = $config->name;
            $to->emailstop = 0;
            $to->maildisplay = true;
            $to->mailformat = 1;
        }

        $noreplyuser = \core_user::get_noreply_user();
        $subject = $config->subject;
        $message = $config->message;
        $messagehtml = format_text($message, FORMAT_MOODLE);

        // Send the email. We use raw email rather than a Moodle message
        // so we can easily send to users outside of Moodle.
        email_to_user($to, $noreplyuser, $subject, $message, $messagehtml);

        $this->enginestep->log(get_string('flow_email:sending_message', 'tool_dataflows', $toemail ));
        $this->enginestep->log($subject);
        $this->enginestep->log($message);

        return $input;
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
            'to',
            'subject',
            'message',
        ] as $field) {
            if (empty($config->$field)) {
                $errors["config_$field"] = get_string('config_field_missing', 'tool_dataflows', "$field", true);
            }
        }

        return empty($errors) ? true : $errors;
    }
}
