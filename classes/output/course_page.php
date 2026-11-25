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

use local_auditenhance\local\area;
use local_auditenhance\local\items;
use local_auditenhance\local\registry;
use local_auditenhance\local\run_manager;
use local_auditenhance\local\severity;

/**
 * Results of one area for one course: counts per check and sample items.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_page implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param \stdClass $course
     * @param area $area
     * @param int|null $runid Run to show, null when there is none.
     * @param int $samplesize Items shown per check, 0 for all.
     */
    public function __construct(
        /** @var \stdClass Course. */
        protected \stdClass $course,
        /** @var area Area. */
        protected area $area,
        /** @var int|null Run id. */
        protected ?int $runid,
        /** @var int Sample size. */
        protected int $samplesize = 5,
    ) {
    }

    /**
     * Data for the template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $data = ['hasrun' => $this->runid !== null, 'checks' => [], 'total' => 0, 'areaname' => $this->area->get_name()];
        if ($this->runid === null) {
            return $data;
        }
        $run = run_manager::get_run($this->runid);
        $data['rundate'] = userdate($run->finished);
        $counts = items::count_by_check_for_course($this->runid, (int)$this->course->id, $this->area->get_id());
        foreach (registry::instance()->get_checks([$this->area->get_id()]) as $ref => $check) {
            $count = $counts[$ref] ?? 0;
            if (!$count) {
                continue;
            }
            $entries = [];
            $worst = severity::INFO;
            foreach (items::get_for_course($this->runid, (int)$this->course->id, $ref, $this->samplesize) as $item) {
                $detail = items::detail($item);
                $worst = severity::max($worst, $item->severity);
                $entries[] = [
                    'label' => $detail['label'] ?? $item->issue,
                    'url' => $detail['url'] ?? null,
                    'snippet' => $detail['snippet'] ?? null,
                    'severity' => severity::get_name($item->severity),
                    'severityclass' => $item->severity,
                ];
            }
            $data['checks'][] = [
                'name' => $check->get_name(),
                'count' => $count,
                'why' => $check->get_explanation(),
                'fix' => $check->get_fix(),
                'items' => $entries,
                'more' => $this->samplesize && $count > count($entries) ? $count - count($entries) : 0,
            ];
            $data['total'] += $count;
        }
        $data['noissues'] = !$data['checks'];
        return $data;
    }
}
