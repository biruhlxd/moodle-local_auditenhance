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

namespace local_auditenhance\local;

/**
 * Scoring engine.
 *
 * Area score = 100 minus the penalties of failed, non-muted checks, floored at 0. Checks that are
 * skipped, could not run or are muted do not count. An area with no applicable checks has no score.
 * Overall score = weighted average of the area scores that exist.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scoring {
    /**
     * Score of one area.
     *
     * @param finding[] $findings Findings of the area's checks, muted checks already removed.
     * @return int|null Null when no check applies.
     */
    public static function area_score(array $findings): ?int {
        $applicable = false;
        $penalty = 0;
        foreach ($findings as $finding) {
            if (!$finding->is_applicable()) {
                continue;
            }
            $applicable = true;
            if ($finding->is_failure()) {
                $penalty += severity::penalty($finding->severity);
            }
        }
        if (!$applicable) {
            return null;
        }
        return max(0, 100 - $penalty);
    }

    /**
     * Overall score.
     *
     * @param array<string, int|null> $areascores Area id => score.
     * @param array<string, int> $weights Area id => weight.
     * @return int|null Null when no area has a score or all weights are 0.
     */
    public static function overall_score(array $areascores, array $weights): ?int {
        $total = 0;
        $weightsum = 0;
        foreach ($areascores as $areaid => $score) {
            if ($score === null) {
                continue;
            }
            $weight = max(0, (int)($weights[$areaid] ?? 0));
            $total += $score * $weight;
            $weightsum += $weight;
        }
        if ($weightsum === 0) {
            return null;
        }
        return (int)round($total / $weightsum);
    }

    /**
     * Area and overall scores for a set of findings.
     *
     * @param array<string, finding> $findings Check reference => finding.
     * @param string[] $muted Muted check references.
     * @param registry|null $registry
     * @return array{areas: array<string, int|null>, overall: int|null}
     */
    public static function compute(array $findings, array $muted = [], ?registry $registry = null): array {
        $registry = $registry ?? registry::instance();
        $byarea = [];
        foreach ($registry->get_areas() as $area) {
            $byarea[$area->get_id()] = [];
        }
        foreach ($findings as $ref => $finding) {
            $check = $registry->get_check($ref);
            if (!$check || in_array($ref, $muted, true)) {
                continue;
            }
            $byarea[$check->get_area()][] = $finding;
        }
        $areascores = [];
        $weights = [];
        foreach ($registry->get_areas() as $area) {
            $areascores[$area->get_id()] = self::area_score($byarea[$area->get_id()]);
            $weights[$area->get_id()] = $area->get_weight();
        }
        return ['areas' => $areascores, 'overall' => self::overall_score($areascores, $weights)];
    }

    /**
     * Name of the band a score falls in, for colouring.
     *
     * @param int|null $score
     * @return string good, fair, poor or none.
     */
    public static function band(?int $score): string {
        if ($score === null) {
            return 'none';
        }
        if ($score >= 80) {
            return 'good';
        }
        return $score >= 50 ? 'fair' : 'poor';
    }
}
