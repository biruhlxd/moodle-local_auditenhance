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
 * Course categories without courses or subcategories.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class emptycategories extends base {
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
        $sql = "SELECT cc.id, cc.name
                  FROM {course_categories} cc
                 WHERE cc.coursecount = 0
                       AND NOT EXISTS (SELECT 1 FROM {course_categories} sub WHERE sub.parent = cc.id)
                       AND NOT EXISTS (SELECT 1 FROM {course} c WHERE c.category = cc.id)
              ORDER BY cc.sortorder";
        $categories = $DB->get_records_sql($sql);
        // The only category of a site is never reported.
        if (!$categories || $DB->count_records('course_categories') <= 1) {
            return finding::pass($this->summary('ok'));
        }
        $sample = [];
        foreach ($categories as $category) {
            $sample[] = ['label' => format_string($category->name),
                'url' => new \moodle_url('/course/management.php', ['categoryid' => $category->id])];
        }
        return finding::fail(severity::LOW, $this->summary('fail', count($categories)), [], count($categories))
            ->with_sample($sample)
            ->with_link($this->get_fix_url());
    }
}
