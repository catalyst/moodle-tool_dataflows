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

use Symfony\Component\ExpressionLanguage\Token;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\ExpressionLanguage\TokenStream;

/**
 * Class adding modification to Symfony Lexer to suit dataflow needs.
 *
 * @package    tool_dataflows
 * @author     Ghaly Marc-Alexandre <marc-alexandreghaly@catalyst-ca.net>
 * @copyright  Catalyst IT, 2023
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dataflow_lexer extends \Symfony\Component\ExpressionLanguage\Lexer {
    /**
     * Tokenizes an expression.
     *
     * @param string $expression The expression to tokenize
     *
     * @return TokenStream A token stream instance
     *
     * @throws SyntaxError
     */
    public function tokenize($expression)
    {
        $expression = str_replace(["\r", "\n", "\t", "\v", "\f"], ' ', $expression);
        $cursor = 0;
        $tokens = [];
        $brackets = [];
        $end = \strlen($expression);

        while ($cursor < $end) {
            if (' ' == $expression[$cursor]) {
                ++$cursor;

                continue;
            }

            if (preg_match('/[0-9]+(?:\.[0-9]+)?/A', $expression, $match, 0, $cursor)) {
                // Numbers.
                // Floats.
                $number = (float) $match[0];
                if (preg_match('/^[0-9]+$/', $match[0]) && $number <= \PHP_INT_MAX) {
                    // Integers lower than the maximum.
                    $number = (int) $match[0];
                }
                $tokens[] = new Token(Token::NUMBER_TYPE, $number, $cursor + 1);
                $cursor += \strlen($match[0]);
            } elseif (preg_match("/{'(\W[a-zA-Z_\x7f-\xff][a-zA-Z0-9_.\x7f-\xff]*)'}/A", $expression, $match, 0, $cursor)) {
                // Names litteral.
                $tokens[] = new Token(Token::NAME_TYPE, $match[1], $cursor + 1);
                $cursor += \strlen($match[0]);
            } elseif (false !== strpos('([{', $expression[$cursor])) {
                // Opening bracket.
                $brackets[] = [$expression[$cursor], $cursor];

                $tokens[] = new Token(Token::PUNCTUATION_TYPE, $expression[$cursor], $cursor + 1);
                ++$cursor;
            } elseif (false !== strpos(')]}', $expression[$cursor])) {
                // Closing bracket.
                if (empty($brackets)) {
                    throw new SyntaxError(sprintf('Unexpected "%s".', $expression[$cursor]), $cursor, $expression);
                }

                list($expect, $cur) = array_pop($brackets);
                if ($expression[$cursor] != strtr($expect, '([{', ')]}')) {
                    throw new SyntaxError(sprintf('Unclosed "%s".', $expect), $cur, $expression);
                }

                $tokens[] = new Token(Token::PUNCTUATION_TYPE, $expression[$cursor], $cursor + 1);
                ++$cursor;
            } elseif (preg_match('/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\'/As', $expression, $match, 0, $cursor)) {
                // Strings.
                $tokens[] = new Token(Token::STRING_TYPE, stripcslashes(substr($match[0], 1, -1)), $cursor + 1);
                $cursor += \strlen($match[0]);
            } elseif (preg_match('/(?<=^|[\s(])not in(?=[\s(])|\!\=\=|(?<=^|[\s(])not(?=[\s(])|(?<=^|[\s(])and(?=[\s(])|\=\=\=|\>\=|(?<=^|[\s(])or(?=[\s(])|\<\=|\*\*|\.\.|(?<=^|[\s(])in(?=[\s(])|&&|\|\||(?<=^|[\s(])matches|\=\=|\!\=|\*|~|%|\/|\>|\||\!|\^|&|\+|\<|\-/A', $expression, $match, 0, $cursor)) {
                // Operators.
                $tokens[] = new Token(Token::OPERATOR_TYPE, $match[0], $cursor + 1);
                $cursor += \strlen($match[0]);
            } elseif (false !== strpos('.,?:', $expression[$cursor])) {
                // Punctuation.
                $tokens[] = new Token(Token::PUNCTUATION_TYPE, $expression[$cursor], $cursor + 1);
                ++$cursor;
            } elseif (preg_match('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/A', $expression, $match, 0, $cursor)) {
                // Names.
                $tokens[] = new Token(Token::NAME_TYPE, $match[0], $cursor + 1);
                $cursor += \strlen($match[0]);
            } else {
                // Unlexable.
                throw new SyntaxError(sprintf('Unexpected character "%s".', $expression[$cursor]), $cursor, $expression);
            }
        }

        $tokens[] = new Token(Token::EOF_TYPE, null, $cursor + 1);

        if (!empty($brackets)) {
            list($expect, $cur) = array_pop($brackets);
            throw new SyntaxError(sprintf('Unclosed "%s".', $expect), $cur, $expression);
        }

        return new TokenStream($tokens, $expression);
    }
}
