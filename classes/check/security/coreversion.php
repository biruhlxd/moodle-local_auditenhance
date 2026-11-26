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

namespace local_auditenhance\check\security;

use local_auditenhance\check\base;
use local_auditenhance\local\finding;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Moodle core version is supported.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coreversion extends base {
    /**
     * End of general support and end of security support per branch (approximate, from the Moodle release calendar).
     */
    public const SUPPORT = [
        '401' => ['2023-12-11', '2025-12-08'],
        '402' => ['2024-04-22', '2024-10-07'],
        '403' => ['2024-10-07', '2025-04-21'],
        '404' => ['2025-04-21', '2025-10-06'],
        '405' => ['2025-10-06', '2027-10-04'],
        '500' => ['2026-04-20', '2026-10-05'],
        '501' => ['2026-10-05', '2027-04-19'],
        '502' => ['2027-04-19', '2027-10-04'],
        '503' => ['2027-10-04', '2029-10-01'],
    ];

    /** @var int Warn this long before security support ends. */
    public const ENDING_SOON = 90 * DAYSECS;

    /**
     * Severity when failing.
     *
     * @return string
     */
    public function get_default_severity(): string {
        return severity::CRITICAL;
    }

    /**
     * Link to the notifications page.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/index.php');
    }

    /**
     * Support status of a branch at a time.
     *
     * @param string $branch For example '405'.
     * @param int $now
     * @return string One of ended, ending, securityonly, supported, newer.
     */
    public static function get_support_status(string $branch, int $now): string {
        if (!isset(self::SUPPORT[$branch])) {
            return (int)$branch > max(array_map('intval', array_keys(self::SUPPORT))) ? 'newer' : 'ended';
        }
        [$general, $security] = array_map('strtotime', self::SUPPORT[$branch]);
        if ($now > $security) {
            return 'ended';
        }
        if ($now > $security - self::ENDING_SOON) {
            return 'ending';
        }
        return $now > $general ? 'securityonly' : 'supported';
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        global $CFG;
        $branch = (string)$CFG->branch;
        $status = self::get_support_status($branch, time());
        $a = (object)['release' => $CFG->release, 'date' => isset(self::SUPPORT[$branch])
            ? userdate(strtotime(self::SUPPORT[$branch][1]), get_string('strftimedate', 'langconfig')) : ''];
        $update = $this->core_update_available();
        switch ($status) {
            case 'ended':
                return finding::fail(severity::CRITICAL, $this->summary('ended', $a))->with_link($this->get_fix_url());
            case 'ending':
                return finding::fail(severity::MEDIUM, $this->summary('ending', $a))->with_link($this->get_fix_url());
            case 'securityonly':
                return finding::fail(severity::LOW, $this->summary('securityonly', $a))->with_link($this->get_fix_url());
        }
        if ($update) {
            return finding::fail(severity::LOW, $this->summary('update', $a))->with_link($this->get_fix_url());
        }
        return finding::pass($this->summary($status === 'newer' ? 'newer' : 'ok', $a));
    }

    /**
     * Is a newer core release available, according to the update checker?
     *
     * @return bool
     */
    protected function core_update_available(): bool {
        global $CFG;
        $checker = \core\update\checker::instance();
        if (!$checker->enabled() || !$checker->get_last_timefetched()) {
            return false;
        }
        $updates = $checker->get_update_info('core', [
            'minmaturity' => $CFG->updateminmaturity ?? MATURITY_STABLE,
            'notifybuilds' => !empty($CFG->updatenotifybuilds),
        ]);
        return !empty($updates);
    }
}
