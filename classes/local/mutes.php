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
 * Muted checks.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mutes {
    /**
     * All mutes by check reference.
     *
     * @return \stdClass[]
     */
    public static function get_all(): array {
        global $DB;
        $result = [];
        foreach ($DB->get_records('local_auditenhance_mute', null, 'timecreated DESC') as $record) {
            $result[$record->checkname] = $record;
        }
        return $result;
    }

    /**
     * References of muted checks.
     *
     * @return string[]
     */
    public static function get_muted_refs(): array {
        return array_keys(self::get_all());
    }

    /**
     * Is a check muted?
     *
     * @param string $checkref
     * @return bool
     */
    public static function is_muted(string $checkref): bool {
        global $DB;
        return $DB->record_exists('local_auditenhance_mute', ['checkname' => $checkref]);
    }

    /**
     * Mute a check.
     *
     * @param string $checkref
     * @param string $reason
     * @param int|null $userid Defaults to the current user.
     * @return int Mute id.
     */
    public static function mute(string $checkref, string $reason, ?int $userid = null): int {
        global $DB, $USER;
        $userid = $userid ?? (int)$USER->id;
        if ($existing = $DB->get_record('local_auditenhance_mute', ['checkname' => $checkref])) {
            $existing->reason = $reason;
            $existing->userid = $userid;
            $existing->timecreated = time();
            $DB->update_record('local_auditenhance_mute', $existing);
            $id = (int)$existing->id;
        } else {
            $id = (int)$DB->insert_record('local_auditenhance_mute', (object)[
                'checkname' => $checkref,
                'reason' => $reason,
                'userid' => $userid,
                'timecreated' => time(),
            ]);
        }
        \local_auditenhance\event\check_muted::create([
            'context' => \context_system::instance(),
            'objectid' => $id,
            'other' => ['checkname' => $checkref],
        ])->trigger();
        results::purge_cache();
        return $id;
    }

    /**
     * Unmute a check.
     *
     * @param string $checkref
     */
    public static function unmute(string $checkref): void {
        global $DB;
        $record = $DB->get_record('local_auditenhance_mute', ['checkname' => $checkref]);
        if (!$record) {
            return;
        }
        $DB->delete_records('local_auditenhance_mute', ['id' => $record->id]);
        \local_auditenhance\event\check_unmuted::create([
            'context' => \context_system::instance(),
            'objectid' => $record->id,
            'other' => ['checkname' => $checkref],
        ])->trigger();
        results::purge_cache();
    }
}
