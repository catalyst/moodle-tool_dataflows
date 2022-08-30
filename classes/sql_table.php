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

/**
 * A convinience class for table_sql handling within this plugin
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sql_table extends \table_sql {

    /**
     * Sets up, builds, and outputs the whole table
     *
     * An optional callable will allow you to customise any defaults or modify
     * the data AFTER it has been queried, but BEFORE it has been outputted.
     *
     * @param callable|null $transformer optional transformer
     */
    public function build(?callable $transformer = null) {
        $this->setup();

        $haspagination = false;
        if ($this->pagesize > 0) {
            $haspagination = true;
        }

        // Ensure pagination works if set up prior.
        $this->query_db($this->pagesize, false);
        $this->pageable($haspagination);

        // Defaults configuration for dataflow tables
        // No hide/show links under each column.
        $this->collapsible(false);
        // Columns are presorted.
        $this->sortable(false);
        // Table does not show download options by default, an import/export option will be available instead.
        $this->is_downloadable(false);

        // Transform the table as required (data, display options, etc).
        if (isset($transformer)) {
            $transformer($this);
        }

        // Build, close the recordset and output the table.
        $this->build_table();
        $this->close_recordset();
        $this->finish_output();
    }

    /**
     * Closes recordset (for use after building the table)
     */
    public function close_recordset() {
        if ($this->rawdata && ($this->rawdata instanceof \core\dml\recordset_walk ||
                $this->rawdata instanceof \moodle_recordset)) {
            $this->rawdata->close();
            $this->rawdata = null;
        }
    }

    /**
     * Sets the pagesize for the table
     *
     * @param int $pagesize
     */
    public function set_page_size(int $pagesize) {
        $this->pagesize = $pagesize;
    }
}
