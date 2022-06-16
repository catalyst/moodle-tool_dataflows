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

use tool_dataflows\step;
use tool_dataflows\local\scheduler;
use tool_dataflows\local\execution\engine_step;


/**
 * CRON trigger class
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trigger_cron extends trigger_step {

    /** @var int[] number of output flows (min, max). */
    protected $outputflows = [0, 1];

    /** @var int[] number of output connectors (min, max). */
    protected $outputconnectors = [0, 1];

    protected static function form_define_fields(): array {
        return [
            'timestr' => ['type' => PARAM_TEXT],
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
        $mform->addElement('text', 'config_timestr', get_string('trigger_cron:timestr', 'tool_dataflows'));
        $mform->addRule('config_timestr', 'This field is required', 'required');
        $mform->addElement('static', 'config_timestr_help', '', get_string('trigger_cron:timestr_help', 'tool_dataflows'));
    }

    /**
     * Validate the configuration settings.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        $errors = [];
        if (empty($config->timestr)) {
            $errors['config_timestr'] = get_string('config_field_missing', 'tool_dataflows', 'timestr', true);
        } else {
            if (strtotime($config->timestr) === false) {
                $errors['config_timestr'] = get_string('config_field_invalid', 'tool_dataflows', 'timestr', true);
            }
        }
        return empty($errors) ? true : $errors;
    }

    /**
     * Hook function that gets called when a step has been saved.
     *
     * @param step $stepdef
     */
    public function on_save() {
        $newtime = scheduler::determine_next_scheduled_time(
            $this->stepdef->config->timestr,
            scheduler::get_last_scheduled_time($this->stepdef->dataflowid) ?: time()
        );

        scheduler::update_next_scheduled_time(
            $this->stepdef->dataflowid,
            $newtime
        );
    }

    /**
     * Hook function that gets called when a step has been saved.
     *
     * @param step $stepdef
     */
    public function on_delete() {
        global $DB;

        $DB->delete_records(scheduler::TABLE, ['dataflowid' => $this->stepdef->dataflowid]);
    }

    /**
     * Hook function that gets called when an engine step has been finalised.
     *
     * @throws \dml_exception
     */
    public function on_finalise() {
        if (!$this->enginestep->engine->isdryrun) {
            $dataflowid = $this->enginestep->stepdef->dataflowid;
            $newtime = scheduler::get_next_scheduled_time($dataflowid);
            scheduler::update_next_scheduled_time(
                $dataflowid,
                scheduler::determine_next_scheduled_time(
                    $this->enginestep->stepdef->config->timestr,
                    $newtime
                ),
                $newtime
            );
        }
    }
}
