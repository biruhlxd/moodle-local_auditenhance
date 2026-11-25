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
 * Book chapters.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class book_chapter implements content_source {
    /**
     * Id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'bookchapter';
    }

    /**
     * Available when the book module is installed.
     *
     * @return bool
     */
    public function is_available(): bool {
        global $DB;
        return $DB->record_exists('modules', ['name' => 'book']) && $DB->get_manager()->table_exists('book_chapters');
    }

    /**
     * Read the next chapters.
     *
     * @param int $afterid
     * @param int $limit
     * @return array
     */
    public function fetch(int $afterid, int $limit): array {
        global $DB;
        $sql = "SELECT bc.id, bc.title, bc.content, cm.id AS cmid, cm.course, ctx.id AS contextid
                  FROM {book_chapters} bc
                  JOIN {book} b ON b.id = bc.bookid
                  JOIN {modules} m ON m.name = 'book'
                  JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = b.id AND cm.deletioninprogress = 0
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :level
                 WHERE bc.id > :afterid
              ORDER BY bc.id";
        $records = $DB->get_records_sql($sql, ['level' => CONTEXT_MODULE, 'afterid' => $afterid], 0, $limit);
        $items = [];
        $lastid = 0;
        foreach ($records as $record) {
            $lastid = (int)$record->id;
            if ((string)$record->content === '') {
                continue;
            }
            $items[] = new content_item(
                (string)$record->content,
                (int)$record->contextid,
                (int)$record->course,
                'mod_book',
                (int)$record->id,
                'chapter',
                get_string('source_bookchapter', 'local_auditenhance', shorten_text(format_string($record->title), 60)),
                (new \moodle_url('/mod/book/view.php', ['id' => $record->cmid, 'chapterid' => $record->id]))->out(false)
            );
        }
        return [$lastid, $items];
    }
}
