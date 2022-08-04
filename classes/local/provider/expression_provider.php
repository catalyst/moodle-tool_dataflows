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

namespace tool_dataflows\local\provider;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class expression_provider implements ExpressionFunctionProviderInterface {

    /**
     * Returns the functions made available as helpers to expressions
     *
     * @return array of functions
     */
    public function getFunctions(): array { // phpcs:ignore
        $fromjson = new ExpressionFunction('fromJSON', function ($str) {
            return sprintf('(is_string(%1$s) ? json_decode(%1$s) : %1$s)', $str);
        }, function ($arguments, $str) {
            if (!is_string($str)) {
                return $str;
            }
            return json_decode($str);
        });

        // Isset as a function.
        $isset = new ExpressionFunction('isset', function ($str)
        {
            return sprintf('isset(%1$s)', $str);
        }, function ($arguments, $var)
        {
            return isset($var);
        });

        return [
            $fromjson,
            $isset,
            ExpressionFunction::fromPhp('count', 'count'),
        ];
    }
}
