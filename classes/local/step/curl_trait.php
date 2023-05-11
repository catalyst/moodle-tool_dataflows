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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * CURL trait
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @author     Ghaly Marc-Alexandre <marc-alexandreghaly@catalyst-ca.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait curl_trait {

    /**
     * Returns whether or not the step configured, has a side effect.
     *
     * For curl connectors, it is considered to have a side effect if the target is
     * anywhere outside of the scratch directory, the method is anything other than
     * 'get' or 'head', or if the 'has side effects' setting is checked.
     *
     * @return     bool whether or not this step has a side effect
     * @link https://en.wikipedia.org/wiki/Side_effect_(computer_science)
     */
    public function has_side_effect(): bool {
        if (isset($this->stepdef)) {
            $config = $this->get_variables()->get('config');

            // Destination is outside of scratch directory.
            if (!(empty($config->destination) || helper::path_is_relative($config->destination))) {
                return true;
            }

            // Request is anything other than 'get' or 'head'.
            if (!($config->method == 'get' || $config->method == 'head')) {
                return true;
            }

            // Side effects setting is checked.
            if (!empty($config->sideeffects)) {
                return true;
            }

            return false;
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
        $ex = \html_writer::nonempty_tag(
            'pre',
            htmlspecialchars(get_string('connector_curl:header_format', 'tool_dataflows') . PHP_EOL)
        );

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
            $errors['config_curl'] = get_string(
                'config_field_missing',
                'tool_dataflows',
                get_string('connector_curl:curl', 'tool_dataflows'),
                true
            );
        }
        if (empty($config->rawpostdata) && ($config->method === 'put' || $config->method === 'post')) {
            $errors['config_rawpostdata'] = get_string(
                'config_field_missing',
                'tool_dataflows',
                get_string('connector_curl:rawpostdata', 'tool_dataflows'),
                true
            );
        }
        return empty($errors) ? true : $errors;
    }

    /**
     * Perform any extra validation that is required only for runs.
     *
     * @return true|array Will return true or an array of errors.
     */
    public function validate_for_run() {
        $config = $this->get_variables()->get('config');

        if (!empty($config->destination)) {
            $error = helper::path_validate($config->destination);
            if ($error !== true) {
                return ['config_destination' => $error];
            }
        }

        if (!empty($config->headers)) {
            $headers = helper::extract_http_headers($config->headers);
            if (!is_array($headers)) {
                return ['config_headers' => get_string('connector_curl:headersnotvalid', 'tool_dataflows')];
            }
        }

        return true;
    }

    /**
     * Executes the step
     *
     * Performs a curl call according to given parameters.
     *
     * @param  mixed|null $input
     * @return mixed
     */
    public function execute($input = null) {
        // Get variables.
        $variables = $this->get_variables();
        $config = $variables->get('config');
        $method = $config->method;

        $this->enginestep->log($config->curl);

        // Construct a bash curl command.
        // See https://manpages.org/curl.
        $dbgcommand = 'curl -s -X ' .  strtoupper($method) . ' ' . $config->curl;

        // Extract timeout.
        $timeout = (int) $config->timeout ?: self::DEFAULT_TIMEOUT;

        $dbgcommand .= ' --max-time ' . $timeout;
        $options = ['CURLOPT_TIMEOUT' => $timeout];

        // Extract headers.
        $headers = helper::extract_http_headers($config->headers);
        if ($headers === false) {
            throw new \moodle_exception(get_string('connector_curl:headers_invalid', 'tool_dataflows'));
        }

        // Add headers to bash command. Headers with no value are ended with a ';' in accordance with the man page.
        if (!empty($headers)) {
            foreach ($headers as $name => $value) {
                if (trim($value) !== '') {
                    $header = "$name:$value";
                } else {
                    $header = "$name;";
                }
                $dbgcommand .= ' -H ' . helper::bash_escape($header);
            }
        }

        // Sets post data.
        if (!empty($config->rawpostdata)) {
            $options['CURLOPT_POSTFIELDS'] = $config->rawpostdata;
            $dbgcommand .= ' --data-raw ' . helper::bash_escape($config->rawpostdata);
        }

        // Download response to file provided destination is set.
        if (!empty($config->destination)) {
            if ($config->destination[0] === '/') {
                $config->destination = ltrim($config->destination, '/');
            }
            $config->destination = $this->enginestep->engine->resolve_path($config->destination);
            $file = fopen($config->destination, 'w');
            $options['CURLOPT_FILE'] = $file;
            $dbgcommand .= ' --output ' . helper::bash_escape($config->destination);
        }

        // Log the raw curl command.
        $this->enginestep->log($dbgcommand);
        $variables->set('dbgcommand', $dbgcommand);

        // We do not need to go any further if curl is not going to be called.
        if ($this->enginestep->engine->isdryrun && $this->has_side_effect()) {
            return $input;
        }

        if ($method === 'post') {
            $options['CURLOPT_POST'] = 1;
        }

        if ($method === 'put') {
            $options['CURLOPT_PUT'] = 1;
        }

        $curl = new \curl();

        // Provided a header is specified, add header to request.
        if (!empty($headers)) {
            $this->set_headers($curl, $headers);
        }

        // Perform call.
        $this->enginestep->log('Performing curl call.');
        $result = $curl->$method($config->curl, $options['CURLOPT_POSTFIELDS'] ?? [], $options);

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

        if (($httpcode >= self::HTTP_ERROR || $errno == CURLE_OPERATION_TIMEDOUT)) {
            throw new \moodle_exception($httpcode . ':' . $result);
        }

        // TODO: It would be good to define and list any fixed but exposed
        // fields which the user can use and map to on the edit page.
        $variables->set('response', (object) [
            'result' => $result,
            'info' => (object) $info,
            'destination' => $destination,
        ]);

        return $input;
    }

    /**
     * Sets header to curl instance
     *
     * Prepares headers to proper format for setHeader method.
     *
     * @param \curl $curl RESTful cURL object
     * @param array $headers headers to sanitize
     */
    protected function set_headers(\curl $curl, array $headers) {
        foreach ($headers as $key => $value) {
            $curlheaders[] = "$key: $value";
        }
        $curl->setHeader($curlheaders);
    }
}
