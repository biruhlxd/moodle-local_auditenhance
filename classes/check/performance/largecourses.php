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
 * Courses using the most file storage.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class largecourses extends base {
    /**
     * Heavy: sums the files of every course.
     *
     * @return bool
     */
    public function is_quick(): bool {
        return false;
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        global $DB;
        // Context paths only hold digits and slashes, so a plain LIKE is safe on every database.
        $like = 'ctx.path LIKE ' . $DB->sql_concat('cctx.path', "'/%'");
        $sql = "SELECT c.id, c.fullname, SUM(f.filesize) AS bytes
                  FROM {course} c
                  JOIN {context} cctx ON cctx.instanceid = c.id AND cctx.contextlevel = :courselevel
                  JOIN {context} ctx ON (ctx.id = cctx.id OR $like)
                  JOIN {files} f ON f.contextid = ctx.id AND f.filename <> '.'
                 WHERE c.id <> :siteid
              GROUP BY c.id, c.fullname
              ORDER BY SUM(f.filesize) DESC";
        $courses = $DB->get_records_sql($sql, ['courselevel' => CONTEXT_COURSE, 'siteid' => SITEID], 0, 10);
        $limit = settings::threshold('largecoursemb') * 1024 * 1024;
        $sample = [];
        $over = 0;
        foreach ($courses as $course) {
            if ((int)$course->bytes > $limit) {
                $over++;
            }
            $sample[] = ['label' => format_string($course->fullname) . ': ' . display_size((int)$course->bytes),
                'url' => new \moodle_url('/course/view.php', ['id' => $course->id])];
        }
        if (!$over) {
            return finding::pass($this->summary('ok', display_size($limit)))->with_sample($sample);
        }
        $a = (object)['count' => $over, 'size' => display_size($limit)];
        return finding::fail(severity::LOW, $this->summary('fail', $a), [], $over)->with_sample($sample);
    }
}
