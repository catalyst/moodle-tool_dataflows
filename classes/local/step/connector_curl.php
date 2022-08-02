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

use Symfony\Component\Yaml\Yaml;
use tool_dataflows\helper;

/**
 * CURL connector step type
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @author     Ghaly Marc-Alexandre <marc-alexandreghaly@catalyst-ca.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connector_curl extends connector_step {

    /** @var bool whether or not this step type (potentially) contains a side effect or not */
    protected $hassideeffect = true;

    /** @var int Time after which curl request is aborted */
    protected $timeout = 60;

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'curl' => ['type' => PARAM_TEXT],
            'destination' => ['type' => PARAM_PATH],
            'headers' => ['type' => PARAM_RAW],
            'method' => ['type' => PARAM_TEXT],
            'rawpostdata' => ['type' => PARAM_RAW],
            'sideeffects' => ['type' => PARAM_RAW],
            'timeout' => ['type' => PARAM_INT],
        ];
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
            'dbgcommand' => null,
            'response' => [
                'result' => get_string('connector_curl:output_response_result', 'tool_dataflows'),
                'info' => [
                    'http_code' => null,
                    'connect_time' => null,
                    'total_time' => null,
                    'size_upload' => null,
                    '*' => null,
                ],
                'destination' => get_string('connector_curl:destination', 'tool_dataflows'),
            ],
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
        $jsonexample = [
            'header-key' => 'header value',
            'another-key' => '1234',
        ];
        $ex['json'] = \html_writer::nonempty_tag('pre', json_encode($jsonexample, JSON_PRETTY_PRINT));
        $ex['yaml'] = '<pre>header-key: header value<br>another-key: 1234</pre>';

        $urlarray = [];
        $urlarray[] =& $mform->createElement('select', 'config_method', '', [
            'get'    => 'GET',
            'post'   => 'POST',
            'head'   => 'HEAD',
            'patch'  => 'PATCH',
            'put'    => 'PUT',
        ]);
        $urlarray[] =& $mform->createElement('text', 'config_curl', '');

        $mform->addGroup($urlarray, 'buttonar', get_string('connector_curl:curl', 'tool_dataflows'), [' '], false);
        $mform->addRule('buttonar', get_string('required'), 'required', null, 'server');

        $mform->addElement('textarea', 'config_headers', get_string('connector_curl:headers', 'tool_dataflows'),
                ['cols' => 50, 'rows' => 7]);
        $mform->addElement('static', 'headers_help', '', get_string('connector_curl:field_headers_help', 'tool_dataflows', $ex));

        $mform->addElement('textarea' , 'config_rawpostdata', get_string('connector_curl:rawpostdata', 'tool_dataflows'),
                ['cols' => 50, 'rows' => 7]);

        $mform->addElement('text', 'config_destination', get_string('connector_curl:destination', 'tool_dataflows'));
        $mform->addHelpButton('config_destination', 'connector_curl:destination', 'tool_dataflows');
        $mform->addElement('static', 'config_path_help', '',  get_string('path_help', 'tool_dataflows').
            \html_writer::nonempty_tag('pre', get_string('path_help_examples', 'tool_dataflows')));

        $mform->addElement('checkbox', 'config_sideeffects', get_string('connector_curl:sideeffects', 'tool_dataflows'),
                get_string('yes'));
        $mform->addHelpButton('config_sideeffects', 'connector_curl:sideeffects', 'tool_dataflows');

        $mform->hideIf('config_rawpostdata', 'config_method', 'eq', 'get');
        $mform->disabledIf('config_rawpostdata', 'config_method', 'eq', 'get');

        $mform->addElement('text', 'config_timeout', get_string('connector_curl:timeout', 'tool_dataflows'));
        $mform->addHelpButton('config_timeout', 'connector_curl:timeout', 'tool_dataflows');
    }

    /**
     * Validate the configuration settings.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        $errors = [];
        if (empty($config->curl)) {
            $errors['config_curl'] = get_string('config_field_missing', 'tool_dataflows', 'curl', true);
        }
        if (empty($config->rawpostdata) && ($config->method === 'put' || $config->method === 'post')) {
            $errors['config_rawpostdata'] = get_string('config_field_missing', 'tool_dataflows', 'rawpostdata', true);
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

        if (!empty($config->destination)) {
            $error = helper::path_validate($config->destination);
            if ($error !== true) {
                return ['config_destination' => $error];
            }
        }

        return true;
    }

    /**
     * Executes the step
     *
     * Performs a curl call according to given parameters.
     *
     * @return bool Returns true if successful, false otherwise.
     */
    public function execute(): bool {
        // Get variables.
        $config = $this->enginestep->stepdef->config;
        $isdryrun = $this->enginestep->engine->isdryrun;
        $method = $config->method;

        $this->enginestep->log($config->curl);

        $dbgcommand = 'curl -X ' .  strtoupper($method) . ' ' . $config->curl;
        $result = null;

        if (!empty($config->timeout)) {
            $this->timeout = (int) $config->timeout;
            $dbgcommand .= ' --max-time ' . $config->timeout;
        }

        $options = ['CURLOPT_TIMEOUT' => $this->timeout];

        if ($method === 'post') {
            $options['CURLOPT_POST'] = 1;
        }

        if ($method === 'put') {
            $options['CURLOPT_PUT'] = 1;
        }

        $curl = new \curl();

        $this->hassideeffect = !empty($config->sideeffects);

        // For put and post methods automatically overrides/has side effects.
        if ($config->method != 'get') {
            $this->hassideeffect = true;
        }

        // Provided a header is specified add header to request.
        if (!empty($config->headers)) {
            $headers = $config->headers;
            if (!is_array($headers)) {
                $headers = json_decode($headers, true);
            }
            if (is_null($headers)) {
                $headers = Yaml::parse($config->headers);
            }
            $curl->setHeader($headers);
            $dbgcommand .= ' -H \'' . $config->headers . '\'';
        }

        // Sets post data.
        if (!empty($config->rawpostdata)) {
            $options['CURLOPT_POSTFIELDS'] = $config->rawpostdata;
            $dbgcommand .= ' -d \'' . $config->rawpostdata . '\'';
        }

        // Download response to file provided destination is set.
        if (!empty($config->destination) && !$isdryrun) {
            if ($config->destination[0] === '/') {
                $config->destination = ltrim($config->destination, '/');
            }
            $config->destination = $this->enginestep->engine->resolve_path($config->destination);
            $file = fopen($config->destination, 'w');
            $options['CURLOPT_FILE'] = $file;
        }

        // Perform call.
        if (!$isdryrun) {
            $result = $curl->$method($config->curl, [], $options);
        }

        if (!empty($file)) {
            fclose($file);
        }

        $info = $curl->get_info();
        // Stores response to be reusable by other steps.
        // TODO : Once set_var api is refactored add response.
        $response = $curl->getResponse();
        $httpcode = $info['http_code'] ?? null;
        $destination = !empty($config->destination) ? $config->destination : null;
        $errno = $curl->get_errno();

        if (($httpcode >= 400 || empty($response) || $errno == 28) && !$isdryrun) {
            throw new \moodle_exception($httpcode . ':' . $result);
        }

        // Log the raw curl command.
        $this->enginestep->log($dbgcommand);
        $this->set_variables('dbgcommand', $dbgcommand);

        if (!$isdryrun) {
            // TODO: It would be good to define and list any fixed but exposed
            // fields which the user can use and map to on the edit page.
            $this->set_variables('response', (object) [
                'result' => $result,
                'info' => (object) $info,
                'destination' => $destination,
            ]);
        }
        return true;
    }
}
