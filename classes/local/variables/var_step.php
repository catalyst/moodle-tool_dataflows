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

namespace tool_dataflows\local\variables;

use tool_dataflows\local\execution\engine;
use tool_dataflows\step;

/**
 * Variables subtree for a dataflow step.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class var_step extends var_object_visible {
    protected $stepdef;

    public function __construct(step $stepdef, var_object $parent, var_root $root) {
        parent::__construct($stepdef->alias, $parent, $root);
        $this->stepdef = $stepdef;
    }

    public function init() {
        // Define the structure and initialise.
        $this->set('id', $this->stepdef->id);
        $this->set('name', $this->stepdef->name);
        $this->set('alias', $this->stepdef->alias);
        $this->set('description', $this->stepdef->alias);
        $this->set('depends_on', $this->stepdef->get_dependencies_filled());
        $this->set('type', $this->stepdef->alias);

        $this->set('config', $this->stepdef->get_redacted_config());

        $this->set('vars',$this->stepdef->get('vars'));
        foreach (engine::STATUS_LABELS as $state) {
            $this->set("states.$state", null);
        }
    }
}
