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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_auditenhance\local\plugins;

/**
 * Token based helpers to find calls and patterns in PHP code.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class code_analyser {
    /** @var int[] Tokens ignored when looking at code. */
    private const IGNORED = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG, T_INLINE_HTML];

    /** @var string[] Superglobals holding request data. */
    public const INPUT_VARIABLES = ['$_GET', '$_POST', '$_REQUEST', '$_COOKIE'];

    /** @var string[] Functions that read request data. */
    public const INPUT_FUNCTIONS = ['optional_param', 'required_param', 'optional_param_array', 'required_param_array'];

    /**
     * Tokenise PHP code into [id, text, line] entries without white space and comments.
     *
     * @param string $code
     * @return array
     */
    public static function tokenise(string $code): array {
        if ($code === '') {
            return [];
        }
        try {
            $raw = token_get_all($code);
        } catch (\Throwable $e) {
            return [];
        }
        $tokens = [];
        $line = 1;
        foreach ($raw as $token) {
            if (is_array($token)) {
                $line = $token[2];
                if (in_array($token[0], self::IGNORED, true)) {
                    continue;
                }
                $tokens[] = [$token[0], $token[1], $token[2]];
            } else {
                $tokens[] = [0, $token, $line];
            }
        }
        return $tokens;
    }

    /**
     * Calls of plain functions (not methods): name, line and index of the opening parenthesis.
     *
     * @param array $tokens From {@see tokenise()}.
     * @return array List of ['name' => lower case name, 'line' => int, 'open' => int].
     */
    public static function function_calls(array $tokens): array {
        $calls = [];
        $names = [T_STRING];
        if (defined('T_NAME_FULLY_QUALIFIED')) {
            $names[] = T_NAME_FULLY_QUALIFIED;
        }
        $notbefore = [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST];
        if (defined('T_NULLSAFE_OBJECT_OPERATOR')) {
            $notbefore[] = T_NULLSAFE_OBJECT_OPERATOR;
        }
        $count = count($tokens);
        for ($i = 0; $i < $count - 1; $i++) {
            if (!in_array($tokens[$i][0], $names, true) || $tokens[$i + 1][1] !== '(') {
                continue;
            }
            if ($i > 0 && in_array($tokens[$i - 1][0], $notbefore, true)) {
                continue;
            }
            $calls[] = ['name' => strtolower(ltrim($tokens[$i][1], '\\')), 'line' => $tokens[$i][2], 'open' => $i + 1];
        }
        return $calls;
    }

    /**
     * Method calls on a variable, for example $DB->get_records_sql(.
     *
     * @param array $tokens
     * @param string $variable For example '$DB'.
     * @return array List of ['name' => lower case method, 'line' => int, 'open' => int].
     */
    public static function method_calls(array $tokens, string $variable): array {
        $calls = [];
        $count = count($tokens);
        for ($i = 0; $i < $count - 3; $i++) {
            if (
                $tokens[$i][0] === T_VARIABLE && $tokens[$i][1] === $variable && $tokens[$i + 1][0] === T_OBJECT_OPERATOR
                    && $tokens[$i + 2][0] === T_STRING && $tokens[$i + 3][1] === '('
            ) {
                $calls[] = ['name' => strtolower($tokens[$i + 2][1]), 'line' => $tokens[$i + 2][2], 'open' => $i + 3];
            }
        }
        return $calls;
    }

    /**
     * Tokens of the arguments of a call.
     *
     * @param array $tokens
     * @param int $open Index of the opening parenthesis.
     * @return array[] One list of tokens per argument.
     */
    public static function arguments(array $tokens, int $open): array {
        $args = [[]];
        $depth = 0;
        $count = count($tokens);
        for ($i = $open; $i < $count; $i++) {
            $text = $tokens[$i][1];
            if (
                in_array($text, ['(', '[', '{'], true) || $tokens[$i][0] === T_CURLY_OPEN
                    || $tokens[$i][0] === T_DOLLAR_OPEN_CURLY_BRACES
            ) {
                $depth++;
                if ($depth === 1) {
                    continue;
                }
            } else if (in_array($text, [')', ']', '}'], true)) {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            } else if ($text === ',' && $depth === 1) {
                $args[] = [];
                continue;
            }
            $args[count($args) - 1][] = $tokens[$i];
        }
        return $args;
    }

    /**
     * Do these tokens read request data?
     *
     * @param array $tokens
     * @return bool
     */
    public static function reads_input(array $tokens): bool {
        foreach ($tokens as $token) {
            if ($token[0] === T_VARIABLE && in_array($token[1], self::INPUT_VARIABLES, true)) {
                return true;
            }
            if ($token[0] === T_STRING && in_array(strtolower($token[1]), self::INPUT_FUNCTIONS, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Does an SQL argument put a variable straight into a value position?
     *
     * Looks for 'col = ' . $var, "col = $var", "col = '{$var}'" and request data anywhere in the SQL.
     * Fragments such as "WHERE $where" or "IN ($subsql)" are not reported.
     *
     * @param array $tokens Tokens of the SQL argument.
     * @return bool
     */
    public static function sql_has_injected_value(array $tokens): bool {
        if (self::reads_input($tokens)) {
            return true;
        }
        // A variable right after a comparison is a value; IN (...) usually holds a sub query or placeholders.
        $valueposition = '/(=|<>|!=|<|>|\bLIKE)\s*[\'"]?$/i';
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            [$id, $text] = $tokens[$i];
            if (
                $id === T_CONSTANT_ENCAPSED_STRING && isset($tokens[$i + 2]) && $tokens[$i + 1][1] === '.'
                    && $tokens[$i + 2][0] === T_VARIABLE
            ) {
                if (preg_match($valueposition, substr($text, 0, -1))) {
                    return true;
                }
            }
            if (
                $id === T_ENCAPSED_AND_WHITESPACE && isset($tokens[$i + 1])
                    && ($tokens[$i + 1][0] === T_VARIABLE || $tokens[$i + 1][0] === T_CURLY_OPEN)
            ) {
                if (preg_match($valueposition, $text)) {
                    return true;
                }
            }
        }
        return false;
    }
}
