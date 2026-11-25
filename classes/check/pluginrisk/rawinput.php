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
 * Request data read without Moodle's parameter cleaning.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rawinput extends plugin_check_base {
    /**
     * Analyse a plugin.
     *
     * @param run_context $context
     * @param scanned_plugin $plugin
     */
    public function analyse_plugin(run_context $context, scanned_plugin $plugin): void {
        foreach ($plugin->get_php_files() as $file) {
            foreach ($plugin->get_tokens($file) as $token) {
                if ($token[0] === T_VARIABLE && in_array($token[1], code_analyser::INPUT_VARIABLES, true)) {
                    $this->report($context, $plugin, 'rawinput', severity::MEDIUM, $file, $token[2], $token[1]);
                }
            }
        }
    }
}
