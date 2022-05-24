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

namespace tool_dataflows;

use Symfony\Component\Yaml\Yaml;

/**
 * Exportable trait
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait exportable {
    /**
     * Exports this persistent's data in YAML format.
     *
     * @return string $contents YAML formatted export of the data
     */
    public function export() {
        // 4 levels of indentation before it starts to, JSONify / inline settings.
        $inline = 4;
        // 2 spaces per level of indentation.
        $indent = 2;
        $contents = Yaml::dump($this->get_export_data(), $inline, $indent);

        return $contents;
    }
}
