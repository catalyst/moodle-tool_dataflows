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
 * Graph related helper functions
 *
 * @package    tool_dataflows
 * @author     Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright  Catalyst IT, 2022
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class graph {

    /**
     * Creates and returns an adjacency list based on the graph provided
     *
     * @param      array $graph of edges
     * @return     array adjacency list
     */
    public static function to_adjacency_list($graph): array {
        // Transform to adjacency list.
        $adjacencylist = [];
        foreach ($graph as [$src, $dest]) {
            $adjacencylist[$src][] = $dest;
        }
        return $adjacencylist;
    }

    /**
     * Returns whether or not the digraph provided is a Directed Acyclic Graph (DAG)
     *
     * Note: the data could be structured as {0, 1}, {1, 2}, {2, 3} using ids,
     * or names e.g. if planning to validate as part of an import or validating
     * before storing.
     *
     * @param      array $graph array of edges (node connections)
     * @return     bool whether or not the edges provided form a valid DAG
     */
    public static function is_dag($graph): bool {
        $departure = [];
        $discovered = [];
        $time = 0;

        $adjacencylist = self::to_adjacency_list($graph);

        // Perform a depth first search and set and apply various states.
        foreach (array_keys($adjacencylist) as $src) {
            if (!isset($discovered[$src])) {
                self::dfs($adjacencylist, $src, $discovered, $departure, $time);
            }
        }

        // Loop through and check if (src, dest) form a back-edge.
        foreach (array_keys($adjacencylist) as $src) {
            foreach ($adjacencylist[$src] as $dest) {
                if ($departure[$src] <= $departure[$dest]) {
                    return false; // Not a DAG since it contains a back-edge.
                }
            }
        }

        // Above checks did not fail (e.g. no back edges), so it is a valid DAG.
        return true;
    }

    /**
     * Depth first search helper method
     *
     * @param      array $adjacencylist adjacency list of srcs and their destinations
     * @param      int|string $node an int or string representation of the node in the graph
     * @param      array $discovered a hash map keeping track of the nodes visited
     * @param      array $departure a hash map keeping track of the departure time of all graph nodes
     * @param      int $time tracks current departure time
     * @return     int $time tracks current departure time
     */
    public static function dfs(array $adjacencylist, $node,  &$discovered, &$departure, &$time): int {
        // Mark node as discovered.
        $discovered[$node] = true;

        // For each node with a corresponding destination, check all the
        // destinations and update the departure value as required.
        if (isset($adjacencylist[$node])) {
            foreach ($adjacencylist[$node] as $dest) {
                if (!isset($discovered[$dest])) {
                    $time = self::dfs($adjacencylist, $dest, $discovered, $departure, $time);
                }
            }
        }

        $departure[$node] = $time;
        $time = $time + 1;
        return $time;
    }
}
