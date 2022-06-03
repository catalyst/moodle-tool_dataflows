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

use tool_dataflows\local\execution\flow_engine_step;
use tool_dataflows\local\execution\iterators\iterator;
use tool_dataflows\local\execution\iterators\php_iterator;

/**
 * SQL reader step
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reader_sql extends reader_step {

    /**
     * Get the iterator for the step, based on configurations.
     *
     * @param flow_engine_step $step
     * @return iterator
     * @throws \moodle_exception
     */
    public function get_iterator(flow_engine_step $step): iterator {
        $query = $this->construct_query($step);
        return new class($step, $query) extends php_iterator {

            public function __construct(flow_engine_step $step, string $query) {
                global $DB;
                $input = $DB->get_recordset_sql($query);
                parent::__construct($step, $input);
            }

            public function abort() {
                $this->input->close();
                $this->finished = true;
            }
        };
    }

    /**
     * Constructs the SQL query from the configuration options.
     *
     * @param flow_engine_step $step
     * @return string
     * @throws \moodle_exception
     */
    protected function construct_query(flow_engine_step $step): string {
        $config = $step->stepdef->config;
        return $config->sql;
    }

    /**
     * Validate the configuration settings.
     *
     * @param object $config
     * @return true|\lang_string[] true if valid, an array of errors otherwise
     */
    public function validate_config($config) {
        $errors = [];
        if (!isset($config->sql)) {
            $errors['config_field_missing'] = get_string('config_field_missing', 'tool_dataflows', 'sql', true);
        }
        return empty($errors) ? true : $errors;
    }
}