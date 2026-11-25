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

namespace local_auditenhance\task;

use local_auditenhance\local\run_manager;

/**
 * Runs (part of) an audit and queues itself again until the run is finished.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_audit extends \core\task\adhoc_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskrunaudit', 'local_auditenhance');
    }

    /**
     * Run the task.
     */
    public function execute() {
        $data = $this->get_custom_data();
        $runid = (int)($data->runid ?? 0);
        if (!$runid) {
            return;
        }
        $budget = (int)(get_config('local_auditenhance', 'timebudget') ?: 120);
        \core\cron::setup_user();
        $finished = run_manager::execute($runid, $budget, function (int $percent, string $message): void {
            mtrace('... ' . $percent . '% ' . $message);
        });
        if (!$finished) {
            run_manager::queue_task($runid);
        }
    }
}
