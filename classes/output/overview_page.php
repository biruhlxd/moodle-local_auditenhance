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

use local_auditenhance\local\mutes;
use local_auditenhance\local\registry;
use local_auditenhance\local\results;
use local_auditenhance\local\run_manager;
use local_auditenhance\local\scoring;
use local_auditenhance\local\severity;

/**
 * Overview: overall score, area scores, top problems and run status.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview_page implements \renderable, \templatable {
    /** @var int Number of problems listed. */
    public const TOP = 10;

    /**
     * Data for the template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $registry = registry::instance();
        $current = results::get_current(true, $registry);
        $scores = results::get_current_scores($current, $registry);
        $mutes = mutes::get_all();
        $data = [
            'overall' => $scores['overall'],
            'hasoverall' => $scores['overall'] !== null,
            'band' => scoring::band($scores['overall']),
            'areas' => [],
            'top' => [],
            'canrun' => has_capability('local/auditenhance:run', \context_system::instance()),
            'runurl' => (new \moodle_url('/local/auditenhance/index.php'))->out(false),
            'sesskey' => sesskey(),
        ];
        $failures = results::get_top_failures($current, 0, null, $registry);
        foreach ($registry->get_areas() as $area) {
            $counts = array_fill_keys(severity::all(), 0);
            foreach ($failures as $ref => $entry) {
                if ($registry->get_check($ref)->get_area() === $area->get_id()) {
                    $counts[$entry['finding']->severity]++;
                }
            }
            $score = $scores['areas'][$area->get_id()] ?? null;
            $data['areas'][] = [
                'id' => $area->get_id(),
                'name' => $area->get_name(),
                'description' => $area->get_description(),
                'url' => $area->get_url()->out(false),
                'score' => $score,
                'hasscore' => $score !== null,
                'band' => scoring::band($score),
                'problems' => array_sum($counts),
                'critical' => $counts[severity::CRITICAL],
                'high' => $counts[severity::HIGH],
                'weight' => $area->get_weight(),
            ];
        }
        foreach (array_slice($failures, 0, self::TOP, true) as $ref => $entry) {
            $data['top'][] = finding_exporter::export($registry->get_check($ref), $entry, $mutes[$ref] ?? null, $output);
        }
        $data['hastop'] = !empty($data['top']);

        $latest = run_manager::get_latest_complete_run();
        if ($latest) {
            $data['lastrun'] = userdate($latest->finished);
            $data['lastrunscore'] = $latest->score;
        }
        $active = run_manager::get_active_run();
        if ($active) {
            $data['active'] = [
                'progress' => (int)$active->progress,
                'status' => get_string('runstatus_' . $active->status, 'local_auditenhance'),
                'queued' => $active->status === run_manager::STATUS_QUEUED,
            ];
        }
        $data['mutedcount'] = count($mutes);
        $data['mutesurl'] = (new \moodle_url('/local/auditenhance/mutes.php'))->out(false);
        return $data;
    }
}
