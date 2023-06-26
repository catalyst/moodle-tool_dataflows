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

/**
 * Trigger dataflow settings.
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_dataflows\dataflows_table;
use tool_dataflows\manager;
use tool_dataflows\visualiser;

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

defined('MOODLE_INTERNAL') || die();

require_login();

admin_externalpage_setup('tool_dataflows_overview', '', null, '', ['pagelayout' => 'report']);

$context = context_system::instance();

// Check for caps.
require_capability('tool/dataflows:managedataflows', $context);

$url = new moodle_url('/admin/tool/dataflows/index.php');

// Configure any table specifics.
$table = new dataflows_table('dataflows_table');
$sqlfields = '{tool_dataflows}.id,
              {user}.*,
              {tool_dataflows}.*,
              (
                  SELECT count(*)
                  FROM {tool_dataflows_steps}
                  WHERE {tool_dataflows_steps}.dataflowid = {tool_dataflows}.id
              ) as stepcount';
$sqlfrom = '{tool_dataflows}
  LEFT JOIN {user}
         ON {user}.id = {tool_dataflows}.userid';
$sqlwhere = '1=1';
$sqlparams = [];
$table->set_sql($sqlfields, $sqlfrom, $sqlwhere, $sqlparams);
$table->make_columns();

if (manager::is_dataflows_readonly()) {
    \core\notification::warning(get_string('readonly_active', 'tool_dataflows'));
}

visualiser::display_dataflows_table($table, $url, get_string('overview', 'tool_dataflows'));
