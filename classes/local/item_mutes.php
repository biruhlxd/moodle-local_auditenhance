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
 * Single items, or all items of one component, muted for a check.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_mutes {
    /** @var array|null Mutes by check: ['keys' => [key => true], 'components' => [component => true]]. Cached. */
    private static ?array $cache = null;

    /**
     * Load the mutes.
     *
     * @return array
     */
    private static function load(): array {
        global $DB;
        if (self::$cache === null) {
            self::$cache = [];
            foreach ($DB->get_records('local_auditenhance_itemmute', null, '', 'id, checkname, itemkey, component') as $mute) {
                if ($mute->itemkey !== '') {
                    self::$cache[$mute->checkname]['keys'][$mute->itemkey] = true;
                } else if ($mute->component !== '') {
                    self::$cache[$mute->checkname]['components'][$mute->component] = true;
                }
            }
        }
        return self::$cache;
    }

    /**
     * Forget the cached mutes.
     */
    public static function reset(): void {
        self::$cache = null;
    }

    /**
     * Is an item muted?
     *
     * @param string $checkref
     * @param string $itemkey
     * @param string $component
     * @return bool
     */
    public static function is_muted(string $checkref, string $itemkey, string $component): bool {
        $mutes = self::load()[$checkref] ?? null;
        if (!$mutes) {
            return false;
        }
        return isset($mutes['keys'][$itemkey]) || ($component !== '' && isset($mutes['components'][$component]));
    }

    /**
     * All item mutes.
     *
     * @return \stdClass[]
     */
    public static function get_all(): array {
        global $DB;
        return array_values($DB->get_records('local_auditenhance_itemmute', null, 'timecreated DESC, id DESC'));
    }

    /**
     * Mute one item, or all items of its component for its check.
     *
     * @param \stdClass $item Row of local_auditenhance_item.
     * @param bool $wholecomponent Mute every item of the item's component for this check.
     * @param string $reason
     * @return int Mute id.
     */
    public static function mute(\stdClass $item, bool $wholecomponent, string $reason): int {
        global $DB, $USER;
        $key = $wholecomponent ? '' : (string)$item->itemkey;
        $component = $wholecomponent ? (string)$item->component : '';
        if ($key === '' && $component === '') {
            throw new \coding_exception('Nothing to mute');
        }
        $detail = items::detail($item);
        $label = $wholecomponent ? $component : (string)($detail['label'] ?? $item->issue);
        $existing = $DB->get_record(
            'local_auditenhance_itemmute',
            ['checkname' => $item->checkname, 'itemkey' => $key, 'component' => $component]
        );
        $record = (object)['checkname' => $item->checkname, 'itemkey' => $key, 'component' => $component, 'label' => $label,
            'reason' => $reason, 'userid' => (int)$USER->id, 'timecreated' => time()];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_auditenhance_itemmute', $record);
        } else {
            $record->id = $DB->insert_record('local_auditenhance_itemmute', $record);
        }
        \local_auditenhance\event\item_muted::create([
            'context' => \context_system::instance(),
            'objectid' => $record->id,
            'other' => ['checkname' => $item->checkname, 'label' => $label],
        ])->trigger();
        self::reset();
        return (int)$record->id;
    }

    /**
     * Remove an item mute.
     *
     * @param int $muteid
     */
    public static function unmute(int $muteid): void {
        global $DB;
        $mute = $DB->get_record('local_auditenhance_itemmute', ['id' => $muteid]);
        if (!$mute) {
            return;
        }
        $DB->delete_records('local_auditenhance_itemmute', ['id' => $muteid]);
        \local_auditenhance\event\item_unmuted::create([
            'context' => \context_system::instance(),
            'objectid' => $muteid,
            'other' => ['checkname' => $mute->checkname, 'label' => (string)$mute->label],
        ])->trigger();
        self::reset();
    }
}
