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
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_dataflows\dataflow;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// Ensure permissions/roles are correct.
require_login(null, false);
require_capability('moodle/site:config', context_system::instance());

$id = required_param('id', PARAM_INT);
$type = optional_param('type', 'svg', PARAM_TEXT);
$hash = optional_param('hash', '', PARAM_TEXT);

// Generate DOT script based on the configured dataflow.
$dataflow = new dataflow($id);
$dotscript = $dataflow->get_dotscript();

// Generate the image based on the DOT script.
$contents = \tool_dataflows\visualiser::generate($dotscript, $type);

// If it's an non-svg image, it should send the appropriate headers to ensure it doesn't render as text.
if (in_array($type, ['gif', 'png', 'jpg', 'jpeg'])) {
    header("Content-Type: image/$type");
}

// Is the calling code knew the hash, and the hashes match then we can safely
// cache this in the browser forever.
if ($hash) {
    if ($hash === $dataflow->confighash) {
        header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + WEEKSECS));
        header('Cache-Control: public, max-age=604800, immutable');
        header('Pragma: ');
    }
} else {
    // If we don't have a hash lets fix that for next time.
    if (empty($dataflow->confighash)) {
        $dataflow->save_config_version();
    }
}

// Output the results to the client.
echo $contents;
