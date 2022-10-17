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
use tool_dataflows\task\process_dataflows;

/**
 * CRON trigger class
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trigger_cron extends trigger_step {

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return [
            'minute' => ['type' => PARAM_TEXT],
            'hour' => ['type' => PARAM_TEXT],
            'day' => ['type' => PARAM_TEXT],
            'month' => ['type' => PARAM_TEXT],
            'dayofweek' => ['type' => PARAM_TEXT],
            'disabled' => ['type' => PARAM_TEXT],
        ];
    }

    /**
     * Get the default data.
     *
     * @param \stdClass $data from the persistent form class
     * @return \stdClass
     */
    public function form_get_default_data(\stdClass $data): \stdClass {
        $data = parent::form_get_default_data($data);
        $fields = ['minute', 'hour', 'day', 'month', 'dayofweek'];
        foreach ($fields as $field) {
            if (!isset($data->{"config_$field"})) {
                $data->{"config_$field"} = '*';
            }
        }
        return $data;
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
        $mform->addElement('static', 'schedule_header', '', 'Schedule');

        if ($this->stepdef) {
            $times = scheduler::get_scheduled_times($this->stepdef->id);
            if (!(bool) $this->stepdef->dataflow->enabled) {
                $nextrun = get_string('trigger_cron:flow_disabled', 'tool_dataflows');
            } else if ($times->nextruntime > time()) {
                $nextrun = userdate($times->nextruntime);
            } else {
                $nextrun = get_string('asap', 'tool_task');
            }

            $mform->addElement(
                'static',
                'lastrun',
                get_string('lastruntime', 'tool_task'),
                $times->lastruntime ? userdate($times->lastruntime) : get_string('never')
            );
            $mform->addElement(
                'static',
                'nextrun',
                get_string('nextruntime', 'tool_task'),
                $nextrun
            );
        }

        $crontab = [];

        $element = $mform->createElement('text', 'config_minute', get_string('taskscheduleminute', 'tool_task'), ['size' => '5']);
        $element->setType(PARAM_RAW);
        $crontab[] = $element;

        $element = $mform->createElement('text', 'config_hour', get_string('taskschedulehour', 'tool_task'), ['size' => '5']);
        $element->setType(PARAM_RAW);
        $crontab[] = $element;

        $element = $mform->createElement('text', 'config_day', get_string('taskscheduleday', 'tool_task'), ['size' => '5']);
        $element->setType(PARAM_RAW);
        $crontab[] = $element;

        $element = $mform->createElement('text', 'config_month', get_string('taskschedulemonth', 'tool_task'), ['size' => '5']);
        $element->setType(PARAM_RAW);
        $crontab[] = $element;

        $element = $mform->createElement(
            'text',
            'config_dayofweek',
            get_string('taskscheduledayofweek', 'tool_task'),
            ['size' => '5']
        );
        $element->setType(PARAM_RAW);
        $crontab[] = $element;

        $mform->addGroup($crontab, 'crontab', get_string('trigger_cron:crontab', 'tool_dataflows'), '&nbsp;', false);
        $mform->addElement('static', 'crontab_desc', '', get_string('trigger_cron:crontab_desc', 'tool_dataflows'));
    }

    /**
     * Validate the configuration settings.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        $fields = ['minute', 'hour', 'day', 'month', 'dayofweek'];
        foreach ($fields as $field) {
            if (!self::validate_fields($field, $config->$field)) {
                return ['crontab' => get_string('trigger_cron:invalid', 'tool_dataflows', '', true)];
            }
        }
        return true;
    }

    /**
     * Helper function that validates the submitted data. Copied over from tool_task_edit_scheduled_task_form, as
     * it is removed from 4.0 onwards.
     *
     * Explanation of the regex:-
     *
     * \A\*\z - matches *
     * \A[0-5]?[0-9]\z - matches entries like 23
     * \A\*\/[0-5]?[0-9]\z - matches entries like * / 5
     * \A[0-5]?[0-9](,[0-5]?[0-9])*\z - matches entries like 1,2,3
     * \A[0-5]?[0-9]-[0-5]?[0-9]\z - matches entries like 2-10
     *
     * @param string $field field to validate
     * @param string $value value
     *
     * @return bool true if validation passes, false other wise.
     */
    public static function validate_fields($field, $value) {
        switch ($field) {
            case 'minute':
            case 'hour':
                $regex = "/\A\*\z|\A[0-5]?[0-9]\z|\A\*\/[0-5]?[0-9]\z|\A[0-5]?[0-9](,[0-5]?[0-9])*\z|\A[0-5]?[0-9]-[0-5]?[0-9]\z/";
                break;
            case 'day':
                $regex = "/\A\*\z|\A([1-2]?[0-9]|3[0-1])\z|\A\*\/([1-2]?[0-9]|3[0-1])\z|";
                $regex .= "\A([1-2]?[0-9]|3[0-1])(,([1-2]?[0-9]|3[0-1]))*\z|\A([1-2]?[0-9]|3[0-1])-([1-2]?[0-9]|3[0-1])\z/";
                break;
            case 'month':
                $regex = "/\A\*\z|\A([0-9]|1[0-2])\z|\A\*\/([0-9]|1[0-2])\z|\A([0-9]|1[0-2])(,([0-9]|1[0-2]))*\z|";
                $regex .= "\A([0-9]|1[0-2])-([0-9]|1[0-2])\z/";
                break;
            case 'dayofweek':
                $regex = "/\A\*\z|\A[0-6]\z|\A\*\/[0-6]\z|\A[0-6](,[0-6])*\z|\A[0-6]-[0-6]\z/";
                break;
            default:
                return false;
        }
        return (bool) preg_match($regex, $value);
    }

    /**
     * Return any miscellaneous, step type specific information that the user would be interested in.
     * For cron triggers, this is the next scheduled time to run the flow.
     *
     * @return string
     */
    public function get_details(): string {
        $times = scheduler::get_scheduled_times($this->stepdef->id);
        if (!(bool) $this->stepdef->dataflow->enabled) {
            $nextrun = get_string('trigger_cron:flow_disabled', 'tool_dataflows');
        } else if ($times !== false && $times->nextruntime > time()) {
            $nextrun = userdate($times->nextruntime, get_string('trigger_cron:strftime_datetime', 'tool_dataflows'));
        } else {
            $nextrun = get_string('asap', 'tool_task');
        }
        return get_string('trigger_cron:next_run_time', 'tool_dataflows', $nextrun);
    }

    /**
     * Get the next scheduled time
     *
     * @param  object $config step config
     * @return int time for the next run
     */
    public function get_next_scheduled_time(object $config) {
        $config->classname = process_dataflows::class;
        $times = scheduler::get_scheduled_times($this->stepdef->id);
        if ($times === false) {
            $config->lastruntime = 0;
            $config->nextruntime = 0;
        } else {
            $config = (object) array_merge(
                (array) $config,
                (array) $times
            );
        }

        $task = \core\task\manager::scheduled_task_from_record($config);
        $newtime = $task->get_next_scheduled_time();

        return $newtime;
    }

    /**
     * Hook function that gets called when a step has been saved.
     */
    public function on_save() {
        $config = $this->get_variables()->get('config');
        scheduler::set_scheduled_times(
            $this->stepdef->dataflowid,
            $this->stepdef->id,
            $this->get_next_scheduled_time($config)
        );
    }

    /**
     * Hook function that gets called when a step has been saved.
     */
    public function on_delete() {
        global $DB;

        $DB->delete_records(scheduler::TABLE, ['stepid' => $this->stepdef->id]);
    }

    /**
     * Hook function that gets called when an engine step has been initialised.
     */
    public function on_initialise() {
        if ($this->stepdef->dataflow->is_concurrency_enabled()) {
            // Reschedule on initialisation, so that it can run on the next cron schedule, even if this
            // run has not yet finished.
            $this->reschedule();
        }
    }

    /**
     * Hook function that gets called when an engine step has been finalised.
     */
    public function on_finalise() {
        if (!$this->stepdef->dataflow->is_concurrency_enabled()) {
            // Reschedule on finalise, to avoid conflicts.
            $this->reschedule();
        }
    }

    /**
     * Hook function that gets called when an engine step has been aborted.
     */
    public function on_abort() {
        if (!$this->stepdef->dataflow->is_concurrency_enabled()) {
            // Reschedule on aborts.
            $this->reschedule();
        }
    }

    /**
     * Reschedules the dataflow.
     */
    protected function reschedule() {
        if (!$this->enginestep->engine->isdryrun) {
            $config = $this->get_variables()->get('config');
            $newtime = $this->get_next_scheduled_time($config);

            scheduler::set_scheduled_times(
                $this->stepdef->dataflowid,
                $this->stepdef->id,
                $newtime,
                $config->nextruntime
            );
        }
    }
}
