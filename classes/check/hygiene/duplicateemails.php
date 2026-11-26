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

namespace local_auditenhance\check\hygiene;

use local_auditenhance\check\base;
use local_auditenhance\local\finding;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Email addresses used by more than one account.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class duplicateemails extends base {
    /**
     * Heavy: groups all accounts by email.
     *
     * @return bool
     */
    public function is_quick(): bool {
        return false;
    }

    /**
     * Link to the user list.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/user.php');
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        global $DB, $CFG;
        $sql = "SELECT LOWER(email) AS email, COUNT(1) AS accounts
                  FROM {user}
                 WHERE deleted = 0 AND email <> '' AND mnethostid = :mnethostid
              GROUP BY LOWER(email)
                HAVING COUNT(1) > 1
              ORDER BY COUNT(1) DESC";
        $rows = $DB->get_records_sql($sql, ['mnethostid' => $CFG->mnet_localhost_id]);
        if (!$rows) {
            return finding::pass($this->summary('ok'));
        }
        $sample = [];
        foreach ($rows as $row) {
            $sample[] = ['label' => $row->email . ' (' . $row->accounts . ')', 'url' => null];
        }
        $severity = !empty($CFG->authloginviaemail) ? severity::MEDIUM : severity::LOW;
        return finding::fail($severity, $this->summary('fail', count($rows)), [], count($rows))
            ->with_sample($sample)
            ->with_link($this->get_fix_url());
    }
}
