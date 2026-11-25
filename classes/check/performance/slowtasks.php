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
 * Scheduled tasks that take a long time.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class slowtasks extends base {
    /**
     * Heavy: reads the task log.
     *
     * @return bool
     */
    public function is_quick(): bool {
        return false;
    }

    /**
     * Link to the task logs.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/tasklogs.php');
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        global $DB;
        $limit = settings::threshold('slowtaskminutes') * MINSECS;
        // Type 0 is a scheduled task in the task log.
        $sql = "SELECT classname, MAX(timeend - timestart) AS duration, COUNT(1) AS runs
                  FROM {task_log}
                 WHERE type = 0 AND timestart > :since
              GROUP BY classname
                HAVING MAX(timeend - timestart) > :limit
              ORDER BY MAX(timeend - timestart) DESC";
        $tasks = $DB->get_records_sql($sql, ['since' => time() - WEEKSECS, 'limit' => $limit]);
        if (!$tasks) {
            return finding::pass($this->str('ok', format_time($limit)));
        }
        $sample = [];
        foreach ($tasks as $task) {
            $sample[] = ['label' => ltrim($task->classname, '\\') . ': ' . format_time((int)round($task->duration)),
                'url' => new \moodle_url('/admin/tasklogs.php', ['filter' => $task->classname])];
        }
        $a = (object)['count' => count($tasks), 'time' => format_time($limit)];
        return finding::fail(severity::LOW, $this->str('fail', $a), [], count($tasks))
            ->with_sample($sample)
            ->with_link($this->get_fix_url());
    }
}
