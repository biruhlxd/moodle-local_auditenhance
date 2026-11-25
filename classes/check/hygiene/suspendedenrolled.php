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
 * Suspended accounts that are still enrolled in courses.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class suspendedenrolled extends base {
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
        global $DB;
        $from = "FROM {user} u
                 JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = :active
                 JOIN {enrol} e ON e.id = ue.enrolid AND e.status = :enabled
                WHERE u.deleted = 0 AND u.suspended = 1";
        $params = ['active' => ENROL_USER_ACTIVE, 'enabled' => ENROL_INSTANCE_ENABLED];
        $count = (int)$DB->count_records_sql("SELECT COUNT(DISTINCT u.id) $from", $params);
        if (!$count) {
            return finding::pass($this->str('ok'));
        }
        $users = $DB->get_records_sql("SELECT DISTINCT u.* $from ORDER BY u.id", $params, 0, 10);
        $sample = [];
        foreach ($users as $user) {
            $sample[] = ['label' => fullname($user), 'url' => new \moodle_url('/user/profile.php', ['id' => $user->id])];
        }
        return finding::fail(severity::LOW, $this->str('fail', $count), [], $count)
            ->with_sample($sample)
            ->with_link($this->get_fix_url());
    }
}
