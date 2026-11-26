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
 * Very large files.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class largefiles extends base {
    /**
     * Heavy: reads the files table.
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
        $limit = settings::threshold('largefilemb') * 1024 * 1024;
        $select = "filesize > :limit AND filename <> '.'";
        $count = $DB->count_records_select('files', $select, ['limit' => $limit]);
        if (!$count) {
            return finding::pass($this->summary('ok', display_size($limit)));
        }
        $sample = [];
        $files = $DB->get_records_select(
            'files',
            $select,
            ['limit' => $limit],
            'filesize DESC',
            'id, filename, filesize, component, filearea, contextid',
            0,
            10
        );
        foreach ($files as $file) {
            $context = \context::instance_by_id($file->contextid, IGNORE_MISSING);
            $sample[] = ['label' => $file->filename . ' (' . display_size((int)$file->filesize) . ', ' . $file->component . ')',
                'url' => $context ? $context->get_url() : null];
        }
        $a = (object)['count' => $count, 'size' => display_size($limit)];
        return finding::fail(severity::LOW, $this->summary('fail', $a), [], $count)->with_sample($sample);
    }
}
