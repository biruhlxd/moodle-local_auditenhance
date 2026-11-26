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
use local_auditenhance\local\settings;
use local_auditenhance\local\severity;

/**
 * Free disk space for the Moodle data folder.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class diskspace extends base {
    /**
     * Severity when failing.
     *
     * @return string
     */
    public function get_default_severity(): string {
        return severity::HIGH;
    }

    /**
     * Severity for a share of free space.
     *
     * @param float $freepercent
     * @param int $threshold Percent below which space is low.
     * @return string|null Null when there is enough space.
     */
    public static function severity_for(float $freepercent, int $threshold): ?string {
        if ($freepercent < $threshold / 4) {
            return severity::CRITICAL;
        }
        if ($freepercent < $threshold / 2) {
            return severity::HIGH;
        }
        return $freepercent < $threshold ? severity::MEDIUM : null;
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        global $CFG;
        $free = @disk_free_space($CFG->dataroot);
        $total = @disk_total_space($CFG->dataroot);
        if (!$free || !$total) {
            return finding::skip($this->summary('unknown'));
        }
        $percent = 100 * $free / $total;
        $a = (object)['free' => display_size((int)$free), 'total' => display_size((int)$total),
            'percent' => format_float($percent, 0)];
        $severity = self::severity_for($percent, settings::threshold('diskfreepercent'));
        if ($severity === null) {
            return finding::pass($this->summary('ok', $a));
        }
        return finding::fail($severity, $this->summary('fail', $a));
    }
}
