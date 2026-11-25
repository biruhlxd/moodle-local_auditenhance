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
 * Scheduled tasks that are failing.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class failedtasks extends base {
    /**
     * Link to the scheduled tasks.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/tool/task/scheduledtasks.php');
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        global $DB;
        $tasks = $DB->get_records_select(
            'task_scheduled',
            'faildelay > 0 AND disabled = 0',
            null,
            'faildelay DESC',
            'id, classname, component, faildelay'
        );
        if (!$tasks) {
            return finding::pass($this->str('ok'));
        }
        $severity = severity::MEDIUM;
        $sample = [];
        foreach ($tasks as $task) {
            if ((int)$task->faildelay >= HOURSECS) {
                $severity = severity::HIGH;
            }
            $sample[] = ['label' => ltrim($task->classname, '\\') . ' (' . format_time((int)$task->faildelay) . ')',
                'url' => new \moodle_url('/admin/tool/task/schedule_task.php', ['task' => $task->classname])];
        }
        return finding::fail($severity, $this->str('fail', count($tasks)), [], count($tasks))
            ->with_sample($sample)
            ->with_link($this->get_fix_url());
    }
}
