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

namespace tool_dataflows\local\execution;

use tool_dataflows\local\step\reader_sql;

/**
 * Test reader step type that during execution will write to all the scopes for testing purposes
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reader_sql_variable_setter extends reader_sql {

    /** @var string dataflow variable to set */
    public static $dataflowvar;

    /** @var string global (plugin scope) variable to set */
    public static $globalvar;

    /**
     * Return the definition of the fields available in this form.
     *
     * @return array
     */
    public static function form_define_fields(): array {
        return array_merge(
            parent::form_define_fields(),
            [
                'localvar' => ['type' => PARAM_TEXT],
            ]
        );
    }

    /**
     * Step callback handler
     *
     * Sets variables at different scopes for fun.
     *
     * @param   mixed $input
     * @return  mixed $input
     */
    public function execute($input = null) {
        parent::execute($input);
        // Updates a dataflow scoped variable.
        $this->enginestep->set_dataflow_var('dataflowvar', self::$dataflowvar);
        // Updates a global (plugin scoped) variable.
        $this->enginestep->set_global_var('globalvar', self::$globalvar);
        return $input;
    }
}
