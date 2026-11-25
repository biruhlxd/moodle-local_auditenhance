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
 * Course section summaries.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_section implements content_source {
    /**
     * Id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'coursesection';
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
     * Read the next sections.
     *
     * @param int $afterid
     * @param int $limit
     * @return array
     */
    public function fetch(int $afterid, int $limit): array {
        global $DB;
        $sql = "SELECT cs.id, cs.course, cs.section, cs.name, cs.summary, c.fullname, ctx.id AS contextid
                  FROM {course_sections} cs
                  JOIN {course} c ON c.id = cs.course
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :level
                 WHERE cs.id > :afterid
              ORDER BY cs.id";
        $records = $DB->get_records_sql($sql, ['level' => CONTEXT_COURSE, 'afterid' => $afterid], 0, $limit);
        $items = [];
        $lastid = 0;
        foreach ($records as $record) {
            $lastid = (int)$record->id;
            if ((string)$record->summary === '') {
                continue;
            }
            $name = (string)$record->name !== '' ? format_string($record->name)
                : get_string('sectionname', 'local_auditenhance', $record->section);
            $a = (object)['section' => $name, 'course' => format_string($record->fullname)];
            $items[] = new content_item(
                (string)$record->summary,
                (int)$record->contextid,
                (int)$record->course,
                'core_course',
                (int)$record->id,
                'section',
                get_string('source_coursesection', 'local_auditenhance', $a),
                (new \moodle_url('/course/view.php', ['id' => $record->course], 'section-' . $record->section))->out(false)
            );
        }
        return [$lastid, $items];
    }
}
