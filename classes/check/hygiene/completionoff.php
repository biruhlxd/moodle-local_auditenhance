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
use local_auditenhance\local\severity;

/**
 * Completion tracking is off.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completionoff extends base {
    /**
     * Link to the advanced features.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/settings.php', ['section' => 'optionalsubsystems']);
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        global $CFG, $DB;
        if (empty($CFG->enablecompletion)) {
            return finding::fail(severity::LOW, $this->summary('site'))->with_link($this->get_fix_url());
        }
        $select = 'id <> :siteid AND visible = 1 AND enablecompletion = 0';
        $params = ['siteid' => SITEID];
        $count = $DB->count_records_select('course', $select, $params);
        if (!$count) {
            return finding::pass($this->summary('ok'));
        }
        $courses = $DB->get_records_select('course', $select, $params, 'id', 'id, fullname', 0, 10);
        return finding::fail(severity::INFO, $this->summary('courses', $count), [], $count)
            ->with_sample(emptycourses::course_sample($courses));
    }
}
