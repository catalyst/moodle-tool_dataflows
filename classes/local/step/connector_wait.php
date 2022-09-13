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

/**
 * Step that sleeps for a given amount of time.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connector_wait extends connector_step {

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'timesec' => ['type' => PARAM_TEXT, 'required' => true],
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
        $mform->addElement('text', 'config_timesec', get_string('connector_wait:timesec', 'tool_dataflows'));
    }

    /**
     * Executes the step
     *
     * This will sleep for the number of seconds set in the config.
     *
     * @return bool Returns true if successful, false otherwise.
     */
    public function execute($input = null) {
        $timesec = $this->stepdef->config->timesec;
        if (!is_int($timesec) && !(is_string($timesec) && ctype_digit($timesec))) {
            throw new \moodle_exception('connector_wait:not_integer', 'tool_dataflows', '', $timesec);
        }
        $this->enginestep->log("Waiting for $timesec seconds.");
        sleep($timesec);
        return true;
    }

    /**
     * Validate the configuration settings.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        if (empty($config->timesec)) {
            return ['config_timesec' => get_string('config_field_missing', 'tool_dataflows', 'timesec', true)];
        }
        return true;
    }
}
