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

use Symfony\Component\Yaml\Yaml;
use tool_dataflows\dataflow;
use tool_dataflows\helper;
use tool_dataflows\local\execution\engine;

/**
 * Variables subtree for a dataflow.
 *
 * @package   tool_dataflows
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class var_dataflow extends var_object_visible {

    /** @var dataflow The dataflow persistent object. */
    protected $dataflow;

    /**
     * Construct the object.
     *
     * @param dataflow $dataflow
     * @param var_root $root
     */
    public function __construct(dataflow $dataflow, var_root $root) {
        parent::__construct('dataflow', $root, $root);
        $this->dataflow = $dataflow;
    }

    /**
     * Initialises the tree structure.
     */
    public function init() {
        // Define the tree structure and initialise.
        $this->set('id', $this->dataflow->id);
        $this->set('name', $this->dataflow->name);

        $this->set('config.enabled', $this->dataflow->enabled);
        $this->set('config.concurrencyenabled', $this->dataflow->concurrencyenabled);

        $this->set('vars', $this->dataflow->get('vars'));

        foreach (engine::STATUS_LABELS as $state) {
            $this->set("states.$state", null);
        }
    }
}
