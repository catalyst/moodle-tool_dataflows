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
 * Version
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2022, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2022051900;
$plugin->release = 2022051900;

$plugin->requires = 2017051500;    // Our lowest supported Moodle (3.3.0).

$plugin->supported = [35, 401];    // Available as of Moodle 3.9.0 or later.
// TODO $plugin->incompatible = ;  // Available as of Moodle 3.9.0 or later.

$plugin->component = 'tool_dataflows';
$plugin->maturity = MATURITY_ALPHA;

$plugin->dependencies = [];
