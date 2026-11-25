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

namespace local_auditenhance\output;

use local_auditenhance\local\items;
use local_auditenhance\local\registry;
use local_auditenhance\local\run_manager;

/**
 * Accessibility by course: issue counts per course.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class courses_page implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param string $areaid
     * @param int $page
     * @param int $perpage
     */
    public function __construct(
        /** @var string Area id. */
        protected string $areaid,
        /** @var int Page. */
        protected int $page = 0,
        /** @var int Per page. */
        protected int $perpage = 50,
    ) {
    }

    /**
     * Data for the template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $OUTPUT;
        $runid = items::get_latest_run_for_area($this->areaid);
        $data = ['hasrun' => $runid !== null, 'courses' => []];
        if (!$runid) {
            return $data;
        }
        $data['rundate'] = userdate(run_manager::get_run($runid)->finished);
        $registry = registry::instance();
        $total = items::count_courses($runid, $this->areaid);
        foreach (items::count_by_course($runid, $this->areaid, $this->page * $this->perpage, $this->perpage) as $row) {
            $bycheck = items::count_by_check_for_course($runid, (int)$row->courseid, $this->areaid);
            arsort($bycheck);
            $checks = [];
            foreach ($bycheck as $ref => $count) {
                $check = $registry->get_check($ref);
                $checks[] = ['name' => $check ? $check->get_name() : $ref, 'count' => $count];
            }
            $data['courses'][] = [
                'name' => format_string($row->fullname),
                'shortname' => format_string($row->shortname),
                'url' => (new \moodle_url('/course/view.php', ['id' => $row->courseid]))->out(false),
                'detailurl' => (new \moodle_url('/local/auditenhance/course.php', ['id' => $row->courseid]))->out(false),
                'items' => (int)$row->items,
                'checks' => $checks,
            ];
        }
        $data['hascourses'] = !empty($data['courses']);
        $data['total'] = $total;
        $data['pagingbar'] = $OUTPUT->paging_bar(
            $total,
            $this->page,
            $this->perpage,
            new \moodle_url('/local/auditenhance/courses.php')
        );
        $exports = [];
        foreach ($registry->get_export_formats('area:' . $this->areaid) as $format) {
            $exports[] = ['name' => $format->get_name(), 'url' => (new \moodle_url(
                '/local/auditenhance/export.php',
                ['format' => $format->get_id(), 'scope' => 'area:' . $this->areaid, 'runid' => $runid,
                'sesskey' => sesskey()]
            ))->out(false)];
        }
        $data['exports'] = $exports;
        $data['hasexports'] = !empty($exports);
        return $data;
    }
}
