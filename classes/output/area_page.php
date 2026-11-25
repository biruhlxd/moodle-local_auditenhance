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
use local_auditenhance\local\mutes;
use local_auditenhance\local\registry;
use local_auditenhance\local\results;
use local_auditenhance\local\run_manager;
use local_auditenhance\local\scoring;
use local_auditenhance\local\severity;

/**
 * Area page: findings grouped by severity, then passed, not applicable and muted checks.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class area_page implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param area $area
     */
    public function __construct(
        /** @var area Area. */
        protected area $area,
    ) {
    }

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
        $groups = [];
        foreach (severity::all() as $severity) {
            $groups[$severity] = ['key' => $severity, 'name' => severity::get_name($severity), 'findings' => []];
        }
        $other = ['pass' => [], 'skip' => [], 'muted' => []];
        foreach ($registry->get_checks([$this->area->get_id()]) as $ref => $check) {
            $entry = $current[$ref] ?? null;
            $mute = $mutes[$ref] ?? null;
            $export = finding_exporter::export($check, $entry, $mute, $output);
            $finding = $entry['finding'] ?? null;
            if ($mute) {
                $other['muted'][] = $export;
            } else if ($finding && $finding->is_failure()) {
                $groups[$finding->severity]['findings'][] = $export;
            } else if ($finding && $finding->status === 'pass') {
                $other['pass'][] = $export;
            } else {
                $other['skip'][] = $export;
            }
        }
        $groups = array_values(array_filter($groups, fn($g) => !empty($g['findings'])));
        foreach ($groups as &$group) {
            usort($group['findings'], fn($a, $b) => $b['itemcount'] <=> $a['itemcount']);
        }
        $score = $scores['areas'][$this->area->get_id()] ?? null;
        $canrun = has_capability('local/auditenhance:run', \context_system::instance());
        $exports = [];
        $runid = $this->latest_runid();
        if ($runid) {
            foreach ($registry->get_export_formats('area:' . $this->area->get_id()) as $format) {
                $exports[] = ['name' => $format->get_name(), 'url' => (new \moodle_url(
                    '/local/auditenhance/export.php',
                    ['format' => $format->get_id(), 'scope' => 'area:' . $this->area->get_id(), 'runid' => $runid,
                    'sesskey' => sesskey()]
                ))->out(false)];
            }
        }
        return [
            'name' => $this->area->get_name(),
            'description' => $this->area->get_description(),
            'score' => $score,
            'hasscore' => $score !== null,
            'band' => scoring::band($score),
            'groups' => $groups,
            'hasproblems' => !empty($groups),
            'passed' => $other['pass'],
            'haspassed' => !empty($other['pass']),
            'skipped' => $other['skip'],
            'hasskipped' => !empty($other['skip']),
            'muted' => $other['muted'],
            'hasmuted' => !empty($other['muted']),
            'canrun' => $canrun,
            'runurl' => $this->area->get_url()->out(false),
            'sesskey' => sesskey(),
            'exports' => $exports,
            'hasexports' => !empty($exports),
            'active' => run_manager::get_active_run() !== null,
        ];
    }

    /**
     * Latest complete run with results in this area.
     *
     * @return int|null
     */
    protected function latest_runid(): ?int {
        return \local_auditenhance\local\items::get_latest_run_for_area($this->area->get_id());
    }
}
