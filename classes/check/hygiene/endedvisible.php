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

namespace local_auditenhance\check\hygiene;

use local_auditenhance\check\base;
use local_auditenhance\local\finding;
use local_auditenhance\local\run_context;
use local_auditenhance\local\settings;
use local_auditenhance\local\severity;

/**
 * Courses that ended but are still visible.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class endedvisible extends base {
    /**
     * Link to course management.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/course/management.php');
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        global $DB;
        $days = settings::threshold('courseendgracedays');
        $select = 'id <> :siteid AND visible = 1 AND enddate > 0 AND enddate < :cutoff';
        $params = ['siteid' => SITEID, 'cutoff' => time() - $days * DAYSECS];
        $count = $DB->count_records_select('course', $select, $params);
        if (!$count) {
            return finding::pass($this->str('ok'));
        }
        $courses = $DB->get_records_select('course', $select, $params, 'enddate', 'id, fullname', 0, 10);
        $a = (object)['count' => $count, 'days' => $days];
        return finding::fail(severity::LOW, $this->str('fail', $a), [], $count)
            ->with_sample(emptycourses::course_sample($courses))
            ->with_link($this->get_fix_url());
    }
}
