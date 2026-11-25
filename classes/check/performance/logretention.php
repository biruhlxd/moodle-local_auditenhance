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

namespace local_auditenhance\check\performance;

use local_auditenhance\check\base;
use local_auditenhance\local\finding;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Logs are kept forever.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class logretention extends base {
    /**
     * Link to the standard log settings.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/settings.php', ['section' => 'logsettingstandard']);
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        $enabled = array_filter(explode(',', (string)get_config('tool_log', 'enabled_stores')));
        if (!in_array('logstore_standard', $enabled, true)) {
            return finding::skip($this->str('notused'));
        }
        if ((int)get_config('logstore_standard', 'loglifetime') === 0) {
            return finding::fail(severity::MEDIUM, $this->str('fail'))->with_link($this->get_fix_url());
        }
        return finding::pass($this->str('ok', (int)get_config('logstore_standard', 'loglifetime')));
    }
}
