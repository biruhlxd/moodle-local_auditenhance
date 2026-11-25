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

namespace local_auditenhance\check\pluginrisk;

use local_auditenhance\local\plugins\code_analyser;
use local_auditenhance\local\plugins\plugin_check_base;
use local_auditenhance\local\plugins\scanned_plugin;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Deprecated or removed core functions used by plugins.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class deprecatedapis extends plugin_check_base {
    /** @var string[] Core functions removed in recent Moodle versions. */
    public const REMOVED = ['add_to_log', 'events_trigger', 'get_context_instance', 'print_error', 'get_referer',
        'coursemodule_visible_for_user', 'user_get_participants', 'role_cap_duplicate', 'get_courses_wmanagers',
        'print_textarea', 'calendar_preferences_button', 'get_course_participants', 'is_course_participant',
        'get_recent_enrolments', 'print_arrow', 'session_get_instance', 'get_records_csv', 'put_records_csv'];

    /** @var string[]|null Deprecated function names, cached. */
    protected static ?array $deprecated = null;

    /**
     * Names of the functions in core's deprecatedlib.php.
     *
     * @return string[]
     */
    public static function get_deprecated_functions(): array {
        global $CFG;
        if (self::$deprecated === null) {
            self::$deprecated = [];
            $tokens = code_analyser::tokenise((string)@file_get_contents($CFG->libdir . '/deprecatedlib.php'));
            $count = count($tokens);
            for ($i = 0; $i < $count - 1; $i++) {
                if ($tokens[$i][0] === T_FUNCTION && $tokens[$i + 1][0] === T_STRING) {
                    self::$deprecated[] = strtolower($tokens[$i + 1][1]);
                }
            }
        }
        return self::$deprecated;
    }

    /**
     * Analyse a plugin.
     *
     * @param run_context $context
     * @param scanned_plugin $plugin
     */
    public function analyse_plugin(run_context $context, scanned_plugin $plugin): void {
        $deprecated = array_flip(self::get_deprecated_functions());
        $removed = array_flip(self::REMOVED);
        foreach ($plugin->get_php_files() as $file) {
            foreach (code_analyser::function_calls($plugin->get_tokens($file)) as $call) {
                $name = $call['name'];
                if (!isset($deprecated[$name]) && !isset($removed[$name])) {
                    continue;
                }
                if (function_exists($name) && !isset($deprecated[$name])) {
                    // Defined by the plugin or another plugin under the same name.
                    continue;
                }
                $issue = function_exists($name) ? 'deprecatedapi' : 'removedapi';
                $this->report(
                    $context,
                    $plugin,
                    $issue,
                    $issue === 'removedapi' ? severity::HIGH : severity::MEDIUM,
                    $file,
                    $call['line'],
                    $name . '()'
                );
            }
        }
    }
}
