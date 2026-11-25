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

namespace local_auditenhance\local\content\source;

use local_auditenhance\local\content\content_item;
use local_auditenhance\local\content\content_source;

/**
 * Course summaries.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_summary implements content_source {
    /**
     * Id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'coursesummary';
    }

    /**
     * Always available.
     *
     * @return bool
     */
    public function is_available(): bool {
        return true;
    }

    /**
     * Read the next courses.
     *
     * @param int $afterid
     * @param int $limit
     * @return array
     */
    public function fetch(int $afterid, int $limit): array {
        global $DB;
        $sql = "SELECT c.id, c.fullname, c.summary, ctx.id AS contextid
                  FROM {course} c
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :level
                 WHERE c.id > :afterid AND c.id <> :siteid
              ORDER BY c.id";
        $records = $DB->get_records_sql($sql, ['level' => CONTEXT_COURSE, 'afterid' => $afterid, 'siteid' => SITEID], 0, $limit);
        $items = [];
        $lastid = 0;
        foreach ($records as $record) {
            $lastid = (int)$record->id;
            if ((string)$record->summary === '') {
                continue;
            }
            $items[] = new content_item(
                (string)$record->summary,
                (int)$record->contextid,
                (int)$record->id,
                'core_course',
                (int)$record->id,
                'summary',
                get_string('source_coursesummary', 'local_auditenhance', format_string($record->fullname)),
                (new \moodle_url('/course/edit.php', ['id' => $record->id]))->out(false)
            );
        }
        return [$lastid, $items];
    }
}
