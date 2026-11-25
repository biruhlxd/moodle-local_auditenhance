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
 * Facts about the site used by several checks.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_info {
    /** @var array Request cache. */
    private static array $cache = [];

    /**
     * Number of users who accessed the site in the last days.
     *
     * @param int $days
     * @return int
     */
    public static function active_users(int $days = 30): int {
        global $DB;
        $key = 'active' . $days;
        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = $DB->count_records_select(
                'user',
                'deleted = 0 AND lastaccess > :since',
                ['since' => time() - $days * DAYSECS]
            );
        }
        return self::$cache[$key];
    }

    /**
     * Is the site large, as defined by the largesiteusers threshold?
     *
     * @return bool
     */
    public static function is_large(): bool {
        return self::active_users() > settings::threshold('largesiteusers');
    }

    /**
     * Is the site meant to be private (not for search engines and anonymous visitors)?
     *
     * @return bool
     */
    public static function is_private(): bool {
        global $CFG;
        $visibility = get_config('local_auditenhance', 'sitevisibility') ?: 'auto';
        if ($visibility === 'private') {
            return true;
        }
        if ($visibility === 'public') {
            return false;
        }
        return !empty($CFG->forcelogin);
    }

    /**
     * Class name of the session handler in use, without leading backslash.
     *
     * @return string
     */
    public static function session_handler(): string {
        global $CFG;
        if (!empty($CFG->session_handler_class)) {
            return ltrim($CFG->session_handler_class, '\\');
        }
        if (!empty($CFG->dbsessions)) {
            return 'core\session\database';
        }
        return 'core\session\file';
    }

    /**
     * Forget cached values.
     */
    public static function reset(): void {
        self::$cache = [];
    }
}
