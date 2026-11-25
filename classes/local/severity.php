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

use core\check\result;

/**
 * Severity levels used on every finding.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class severity {
    /** @var string Critical. */
    public const CRITICAL = 'critical';
    /** @var string High. */
    public const HIGH = 'high';
    /** @var string Medium. */
    public const MEDIUM = 'medium';
    /** @var string Low. */
    public const LOW = 'low';
    /** @var string Information only. */
    public const INFO = 'info';

    /** @var int[] Default score penalty per severity. */
    public const DEFAULT_PENALTIES = [
        self::CRITICAL => 30,
        self::HIGH => 15,
        self::MEDIUM => 7,
        self::LOW => 3,
        self::INFO => 0,
    ];

    /**
     * All severities, most severe first.
     *
     * @return string[]
     */
    public static function all(): array {
        return [self::CRITICAL, self::HIGH, self::MEDIUM, self::LOW, self::INFO];
    }

    /**
     * Is this a known severity?
     *
     * @param string $severity
     * @return bool
     */
    public static function is_valid(string $severity): bool {
        return in_array($severity, self::all(), true);
    }

    /**
     * Rank of a severity: higher is more severe.
     *
     * @param string $severity
     * @return int
     */
    public static function rank(string $severity): int {
        $index = array_search($severity, array_reverse(self::all()), true);
        return $index === false ? 0 : $index;
    }

    /**
     * The more severe of two severities.
     *
     * @param string $a
     * @param string $b
     * @return string
     */
    public static function max(string $a, string $b): string {
        return self::rank($a) >= self::rank($b) ? $a : $b;
    }

    /**
     * Raise a severity by a number of steps, up to critical.
     *
     * @param string $severity
     * @param int $steps
     * @return string
     */
    public static function raise(string $severity, int $steps = 1): string {
        $ordered = array_reverse(self::all());
        $index = min(count($ordered) - 1, self::rank($severity) + $steps);
        return $ordered[$index];
    }

    /**
     * Score penalty for a failed check of this severity, taking settings into account.
     *
     * @param string $severity
     * @return int
     */
    public static function penalty(string $severity): int {
        $configured = get_config('local_auditenhance', 'penalty_' . $severity);
        if ($configured !== false && $configured !== '' && is_numeric($configured)) {
            return max(0, (int)$configured);
        }
        return self::DEFAULT_PENALTIES[$severity] ?? 0;
    }

    /**
     * Localised name of a severity.
     *
     * @param string $severity
     * @return string
     */
    public static function get_name(string $severity): string {
        return get_string('severity_' . $severity, 'local_auditenhance');
    }

    /**
     * Map a severity to a status of the core check API.
     *
     * @param string $severity
     * @return string
     */
    public static function to_core_status(string $severity): string {
        return match ($severity) {
            self::CRITICAL => result::CRITICAL,
            self::HIGH => result::ERROR,
            self::MEDIUM, self::LOW => result::WARNING,
            default => result::INFO,
        };
    }
}
