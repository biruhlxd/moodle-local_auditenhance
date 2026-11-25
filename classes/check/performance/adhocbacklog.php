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
use local_auditenhance\local\settings;
use local_auditenhance\local\severity;

/**
 * Ad hoc tasks waiting too long or failing.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adhocbacklog extends base {
    /**
     * Link to the ad hoc tasks.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/tool/task/adhoctasks.php');
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        global $DB;
        $due = $DB->count_records_select('task_adhoc', 'nextruntime < :cutoff', ['cutoff' => time() - HOURSECS]);
        $failing = $DB->count_records_select('task_adhoc', 'faildelay > 0');
        $threshold = settings::threshold('adhocbacklog');
        $a = (object)['due' => $due, 'failing' => $failing, 'threshold' => $threshold];
        if ($due > $threshold) {
            return finding::fail(severity::MEDIUM, $this->str('backlog', $a), [], $due)->with_link($this->get_fix_url());
        }
        if ($failing) {
            return finding::fail(severity::LOW, $this->str('failing', $a), [], $failing)->with_link($this->get_fix_url());
        }
        return finding::pass($this->str('ok', $a));
    }
}
