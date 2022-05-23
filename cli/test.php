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

namespace tool_dataflows;

use tool_dataflows\execution\engine;
use tool_dataflows\dataflow;
use tool_dataflows\step;
use Symfony\Component\Yaml\Yaml;

/**
 * Temp test for SQL query.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('CLI_SCRIPT', true);

require(__DIR__.'/../../../../config.php');

// Crate the dataflow.
$dataflow = new dataflow();
$dataflow->name = 'sql-step';
$dataflow->save();

$reader = new step();
$reader->name = 'reader';
$reader->type = 'tool_dataflows\step\sql_reader';

// Set the SQL query via a YAML config string.
$reader->config = Yaml::dump(['sql' => 'select * from {config}']);
$dataflow->add_step($reader);

$writer = new step();
$writer->name = 'writer';
$writer->type = 'tool_dataflows\step\mtrace';

$writer->depends_on([$reader]);
$dataflow->add_step($writer);

// Execute.
$engine = new engine($dataflow);
$engine->execute();

