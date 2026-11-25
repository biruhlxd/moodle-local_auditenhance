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

namespace local_auditenhance\check\accessibility;

use local_auditenhance\check\base;
use local_auditenhance\local\finding;
use local_auditenhance\local\item;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Uploaded PDF and Office documents, listed for manual review.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class documents extends base {
    /** @var string[] Document types that need a manual accessibility review. */
    public const MIMETYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.presentation',
        'application/vnd.oasis.opendocument.spreadsheet',
    ];

    /** @var string[] File components holding course material (not student work, backups or drafts). */
    public const COMPONENTS = ['mod_resource', 'mod_folder', 'mod_page', 'mod_book', 'mod_label', 'course', 'mod_forum',
        'mod_assign', 'mod_quiz', 'question', 'mod_lesson', 'mod_glossary', 'mod_wiki'];

    /** @var string[] File areas that hold student work. */
    public const STUDENT_AREAS = ['submission_files', 'attachment', 'post', 'feedback_files', 'response_files', 'entry'];

    /**
     * Heavy: reads the files table.
     *
     * @return bool
     */
    public function is_quick(): bool {
        return false;
    }

    /**
     * Severity: these files are listed for review and do not lower the score.
     *
     * @return string
     */
    public function get_default_severity(): string {
        return severity::INFO;
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        global $DB;
        [$mimesql, $params] = $DB->get_in_or_equal(self::MIMETYPES, SQL_PARAMS_NAMED, 'mime');
        [$compsql, $compparams] = $DB->get_in_or_equal(self::COMPONENTS, SQL_PARAMS_NAMED, 'comp');
        [$areasql, $areaparams] = $DB->get_in_or_equal(self::STUDENT_AREAS, SQL_PARAMS_NAMED, 'area', false);
        $params += $compparams + $areaparams;
        $select = "f.mimetype $mimesql AND f.component $compsql AND f.filearea $areasql AND f.filename <> '.'";
        $total = $DB->count_records_sql("SELECT COUNT(1) FROM {files} f WHERE $select", $params);
        if (!$total) {
            return finding::pass($this->str('ok'));
        }
        $max = (int)$context->get_config('maxitems', 5000);
        $rs = $DB->get_recordset_sql("SELECT f.id, f.contextid, f.component, f.filearea, f.itemid, f.filename, f.userid
                                        FROM {files} f
                                       WHERE $select
                                    ORDER BY f.contextid, f.id", $params, 0, $max > 0 ? $max : 0);
        $seen = 0;
        foreach ($rs as $file) {
            $seen++;
            $ctx = \context::instance_by_id($file->contextid, IGNORE_MISSING);
            if (!$ctx) {
                continue;
            }
            $coursecontext = $ctx->get_course_context(false);
            $context->add_item($this, new item(
                'document',
                severity::INFO,
                (int)$file->contextid,
                $coursecontext ? (int)$coursecontext->instanceid : 0,
                (string)$file->component,
                (int)$file->itemid,
                (string)$file->filearea,
                (int)$file->userid,
                [
                    'label' => $ctx->get_context_name(false, true) . ': ' . $file->filename,
                    'url' => $ctx->get_url()->out(false),
                ]
            ));
        }
        $rs->close();
        $finding = $this->finding_from_items($context);
        $finding->itemcount = (int)$total;
        $finding->summary = $this->str('fail', $total);
        return $finding;
    }
}
