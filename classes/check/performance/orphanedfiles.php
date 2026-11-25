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
use local_auditenhance\local\severity;

/**
 * Files whose context no longer exists.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class orphanedfiles extends base {
    /**
     * Heavy: joins the files table with the contexts.
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
        $sql = "SELECT COUNT(1) AS files, SUM(f.filesize) AS bytes
                  FROM {files} f
             LEFT JOIN {context} ctx ON ctx.id = f.contextid
                 WHERE ctx.id IS NULL AND f.filename <> '.'";
        $record = $DB->get_record_sql($sql);
        $count = (int)($record->files ?? 0);
        if (!$count) {
            return finding::pass($this->str('ok'));
        }
        $a = (object)['count' => $count, 'size' => display_size((int)$record->bytes)];
        return finding::fail(severity::LOW, $this->str('fail', $a), [], $count);
    }
}
