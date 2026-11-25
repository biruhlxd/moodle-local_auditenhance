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
 * Question texts (latest version of each question).
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_text implements content_source {
    /**
     * Id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'question';
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
     * Read the next questions.
     *
     * @param int $afterid
     * @param int $limit
     * @return array
     */
    public function fetch(int $afterid, int $limit): array {
        global $DB;
        $sql = "SELECT q.id, q.name, q.questiontext, q.createdby, qc.contextid
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                 WHERE q.id > :afterid AND q.parent = 0
                       AND qv.version = (SELECT MAX(v.version)
                                           FROM {question_versions} v
                                          WHERE v.questionbankentryid = qbe.id)
              ORDER BY q.id";
        $records = $DB->get_records_sql($sql, ['afterid' => $afterid], 0, $limit);
        $items = [];
        $lastid = 0;
        foreach ($records as $record) {
            $lastid = (int)$record->id;
            if ((string)$record->questiontext === '') {
                continue;
            }
            $context = \context::instance_by_id($record->contextid, IGNORE_MISSING);
            $coursecontext = $context ? $context->get_course_context(false) : null;
            $items[] = new content_item(
                (string)$record->questiontext,
                (int)$record->contextid,
                $coursecontext ? (int)$coursecontext->instanceid : 0,
                'core_question',
                (int)$record->id,
                'questiontext',
                get_string('source_question', 'local_auditenhance', shorten_text(format_string($record->name), 60)),
                (new \moodle_url('/question/bank/previewquestion/preview.php', ['id' => $record->id]))->out(false),
                (int)$record->createdby
            );
        }
        return [$lastid, $items];
    }
}
