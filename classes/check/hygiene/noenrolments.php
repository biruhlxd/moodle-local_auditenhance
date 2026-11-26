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
use local_auditenhance\local\settings;
use local_auditenhance\local\severity;

/**
 * Visible courses that nobody is enrolled in.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class noenrolments extends base {
    /**
     * Heavy: checks the enrolments of every course.
     *
     * @return bool
     */
    public function is_quick(): bool {
        return false;
    }

    /**
     * Link to course management.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/course/management.php');
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        global $DB;
        $from = "FROM {course} c
                WHERE c.id <> :siteid AND c.visible = 1 AND c.timecreated < :cutoff
                      AND NOT EXISTS (
                          SELECT 1
                            FROM {enrol} e
                            JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = :active
                           WHERE e.courseid = c.id AND e.status = :enabled)";
        $params = ['siteid' => SITEID, 'cutoff' => time() - settings::threshold('newcoursedays') * DAYSECS,
            'active' => ENROL_USER_ACTIVE, 'enabled' => ENROL_INSTANCE_ENABLED];
        $count = (int)$DB->count_records_sql("SELECT COUNT(1) $from", $params);
        if (!$count) {
            return finding::pass($this->summary('ok'));
        }
        $courses = $DB->get_records_sql("SELECT c.id, c.fullname $from ORDER BY c.id", $params, 0, 10);
        return finding::fail(severity::LOW, $this->summary('fail', $count), [], $count)
            ->with_sample(emptycourses::course_sample($courses))
            ->with_link($this->get_fix_url());
    }
}
