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

namespace tool_dataflows\external;

// Moved as part of https://tracker.moodle.org/browse/MDL-78049 so this is
// required to redirect sites using older versions of Moodle to the previous
// implementation.
// Once the base supported version is 4.2, this is no longer required.
if (class_exists(\core_external\external_api::class)) {
    \class_alias(\core_external\external_api::class, external_api::class);
} else if (class_exists(\external_api::class)) {
    \class_alias(\external_api::class, external_api::class);
}
