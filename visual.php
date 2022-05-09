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
 * Renders the dataflow visually e.g. as an image / svg
 *
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// Ensure permissions/roles are correct.
require_login(null, false);
require_capability('moodle/site:config', context_system::instance());

// TODO: require an id to the dataflow so it can load and render the resulting graph/visual.
$type = optional_param('type', 'svg', PARAM_TEXT);

// Generate DOT script based on the configured workflow.
$dotscript = <<<EXAMPLE
    digraph G {
      rankdir=LR;
      node [shape = record,height=.1];
      "read users" -> "write local csv";
      "read users" -> "count of users by department";
      "count of users by department" -> "write shared csv";
    }
EXAMPLE;

// Generate the image based on the DOT script.
$contents = \tool_dataflows\visualiser::generate($dotscript, $type);

// If it's an non-svg image, it should send the appropriate headers to ensure it doesn't render as text.
if (in_array($type, ['gif', 'png', 'jpg', 'jpeg'])) {
    header("Content-Type: image/$type");
}

// Output the results to the client.
echo $contents;
