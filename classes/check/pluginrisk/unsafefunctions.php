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
 * Unsafe PHP functions: eval, shell commands and unserialize on request data.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unsafefunctions extends plugin_check_base {
    /** @var string[] Functions that run shell commands. */
    public const SHELL = ['exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open', 'pcntl_exec'];

    /**
     * Severity when failing.
     *
     * @return string
     */
    public function get_default_severity(): string {
        return severity::HIGH;
    }

    /**
     * Analyse a plugin.
     *
     * @param run_context $context
     * @param scanned_plugin $plugin
     */
    public function analyse_plugin(run_context $context, scanned_plugin $plugin): void {
        foreach ($plugin->get_php_files() as $file) {
            $tokens = $plugin->get_tokens($file);
            foreach ($tokens as $token) {
                if ($token[0] === T_EVAL) {
                    $this->report($context, $plugin, 'eval', severity::HIGH, $file, $token[2], 'eval()');
                } else if ($token[0] === 0 && $token[1] === chr(96)) {
                    // A backtick runs a shell command.
                    $this->report($context, $plugin, 'shell', severity::HIGH, $file, $token[2], chr(96) . '...' . chr(96));
                }
            }
            foreach (code_analyser::function_calls($tokens) as $call) {
                if (in_array($call['name'], self::SHELL, true)) {
                    $this->report($context, $plugin, 'shell', severity::HIGH, $file, $call['line'], $call['name'] . '()');
                } else if ($call['name'] === 'unserialize') {
                    $args = code_analyser::arguments($tokens, $call['open']);
                    if (code_analyser::reads_input($args[0] ?? [])) {
                        $this->report(
                            $context,
                            $plugin,
                            'unserializeinput',
                            severity::HIGH,
                            $file,
                            $call['line'],
                            'unserialize()'
                        );
                    }
                }
            }
        }
    }
}
