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

namespace local_auditenhance\local\content;

use local_auditenhance\local\severity;

/**
 * Access to the results of the Brickfield accessibility toolkit (tool_brickfield).
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class brickfield {
    /** @var bool|null Cached answer of in_use(). */
    private static ?bool $inuse = null;

    /**
     * Is the toolkit installed and enabled?
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return class_exists('\tool_brickfield\accessibility')
            && \tool_brickfield\accessibility::is_accessibility_enabled();
    }

    /**
     * Has the toolkit stored any results?
     *
     * @return bool
     */
    public static function has_results(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('tool_brickfield_results')
            && $DB->record_exists('tool_brickfield_results', []);
    }

    /**
     * Should the audit read Brickfield results instead of scanning content for mapped checks?
     *
     * @return bool
     */
    public static function in_use(): bool {
        if (self::$inuse === null) {
            self::$inuse = self::is_enabled() && self::has_results();
        }
        return self::$inuse;
    }

    /**
     * Forget the cached answer.
     */
    public static function reset(): void {
        self::$inuse = null;
    }

    /**
     * Map a Brickfield severity to an audit severity.
     *
     * @param int $severity 1 severe, 2 moderate, 3 suggestion.
     * @return string
     */
    public static function to_severity(int $severity): string {
        return match ($severity) {
            1 => severity::MEDIUM,
            2 => severity::LOW,
            default => severity::INFO,
        };
    }

    /**
     * Description of a Brickfield check.
     *
     * @param string $shortname
     * @return string
     */
    public static function check_description(string $shortname): string {
        $identifier = 'checkdesc:' . str_replace('_', '', $shortname);
        if (get_string_manager()->string_exists($identifier, 'tool_brickfield')) {
            return get_string($identifier, 'tool_brickfield');
        }
        return $shortname;
    }
}
