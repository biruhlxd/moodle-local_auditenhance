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
 * Courses without any activity.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class emptycourses extends base {
    /**
     * Heavy: reads all course modules.
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
                WHERE c.id <> :siteid AND c.timecreated < :cutoff
                      AND NOT EXISTS (
                          SELECT 1
                            FROM {course_modules} cm
                            JOIN {modules} m ON m.id = cm.module
                       LEFT JOIN {forum} f ON m.name = 'forum' AND f.id = cm.instance
                           WHERE cm.course = c.id AND cm.deletioninprogress = 0
                                 AND (f.id IS NULL OR f.type <> 'news'))";
        $params = ['siteid' => SITEID, 'cutoff' => time() - settings::threshold('newcoursedays') * DAYSECS];
        $count = (int)$DB->count_records_sql("SELECT COUNT(1) $from", $params);
        if (!$count) {
            return finding::pass($this->summary('ok'));
        }
        return finding::fail(severity::LOW, $this->summary('fail', $count), [], $count)
            ->with_sample(self::course_sample($DB->get_records_sql("SELECT c.id, c.fullname $from ORDER BY c.id", $params, 0, 10)))
            ->with_link($this->get_fix_url());
    }

    /**
     * Sample entries for courses.
     *
     * @param \stdClass[] $courses With id and fullname.
     * @return array
     */
    public static function course_sample(array $courses): array {
        $sample = [];
        foreach ($courses as $course) {
            $sample[] = ['label' => format_string($course->fullname),
                'url' => new \moodle_url('/course/view.php', ['id' => $course->id])];
        }
        return $sample;
    }
}
