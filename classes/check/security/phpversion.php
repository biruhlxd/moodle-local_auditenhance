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
 * The PHP version still receives security fixes.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class phpversion extends base {
    /** @var array<string, string> End of security support of each PHP version (php.net). */
    public const SUPPORT = [
        '8.0' => '2023-11-26',
        '8.1' => '2025-12-31',
        '8.2' => '2026-12-31',
        '8.3' => '2027-12-31',
        '8.4' => '2028-12-31',
        '8.5' => '2029-12-31',
    ];

    /** @var int Warn this long before support ends. */
    public const ENDING_SOON = 90 * DAYSECS;

    /**
     * Support status of a PHP version.
     *
     * @param string $version For example 8.3.6.
     * @param int $now
     * @return string ended, ending, supported or newer.
     */
    public static function get_status(string $version, int $now): string {
        $branch = implode('.', array_slice(explode('.', $version), 0, 2));
        if (!isset(self::SUPPORT[$branch])) {
            return version_compare($branch, max(array_keys(self::SUPPORT)), '>') ? 'newer' : 'ended';
        }
        $end = strtotime(self::SUPPORT[$branch] . ' 23:59:59 UTC');
        if ($now > $end) {
            return 'ended';
        }
        return $now > $end - self::ENDING_SOON ? 'ending' : 'supported';
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        $version = PHP_VERSION;
        $branch = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $a = (object)['version' => $version, 'date' => isset(self::SUPPORT[$branch])
            ? userdate(strtotime(self::SUPPORT[$branch]), get_string('strftimedate', 'langconfig')) : ''];
        return match (self::get_status($version, time())) {
            'ended' => finding::fail(severity::HIGH, $this->summary('ended', $a)),
            'ending' => finding::fail(severity::LOW, $this->summary('ending', $a)),
            default => finding::pass($this->summary('ok', $a)),
        };
    }
}
