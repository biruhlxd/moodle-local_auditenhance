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
 * Plugin settings: thresholds used by checks and their defaults.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class settings {
    /**
     * Numeric thresholds and their defaults.
     *
     * @return array<string, int>
     */
    public static function get_thresholds(): array {
        return [];
    }

    /**
     * Other settings shown on the settings page.
     *
     * @return \admin_setting[]
     */
    public static function get_extra_settings(): array {
        return [];
    }

    /**
     * A numeric threshold.
     *
     * @param string $name
     * @return int
     */
    public static function threshold(string $name): int {
        $defaults = self::get_thresholds();
        $value = get_config('local_auditenhance', $name);
        if ($value === false || $value === '' || !is_numeric($value)) {
            return $defaults[$name] ?? 0;
        }
        return (int)$value;
    }
}
