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
 * SQL built by joining variables into the query instead of using placeholders.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sqlconcat extends plugin_check_base {
    /** @var string[] $DB methods whose first argument is SQL or an SQL fragment. */
    public const SQL_METHODS = ['get_record_sql', 'get_records_sql', 'get_records_sql_menu', 'get_recordset_sql',
        'get_field_sql', 'get_fieldset_sql', 'count_records_sql', 'record_exists_sql', 'execute', 'get_in_or_equal_sql',
        'get_record_select', 'get_records_select', 'get_records_select_menu', 'get_recordset_select', 'get_field_select',
        'get_fieldset_select', 'count_records_select', 'record_exists_select', 'delete_records_select', 'set_field_select'];

    /** @var string[] Methods whose SQL is the second argument (after the table name). */
    public const SELECT_METHODS = ['get_record_select', 'get_records_select', 'get_records_select_menu',
        'get_recordset_select', 'get_field_select', 'get_fieldset_select', 'count_records_select', 'record_exists_select',
        'delete_records_select', 'set_field_select'];

    /**
     * Analyse a plugin.
     *
     * @param run_context $context
     * @param scanned_plugin $plugin
     */
    public function analyse_plugin(run_context $context, scanned_plugin $plugin): void {
        foreach ($plugin->get_php_files() as $file) {
            $tokens = $plugin->get_tokens($file);
            foreach (code_analyser::method_calls($tokens, '$DB') as $call) {
                if (!in_array($call['name'], self::SQL_METHODS, true)) {
                    continue;
                }
                $args = code_analyser::arguments($tokens, $call['open']);
                $index = in_array($call['name'], self::SELECT_METHODS, true) ? 1 : 0;
                if ($call['name'] === 'set_field_select') {
                    $index = 3;
                }
                if (isset($args[$index]) && code_analyser::sql_has_injected_value($args[$index])) {
                    $this->report(
                        $context,
                        $plugin,
                        'sqlconcat',
                        severity::MEDIUM,
                        $file,
                        $call['line'],
                        '$DB->' . $call['name'] . '()'
                    );
                }
            }
        }
    }
}
