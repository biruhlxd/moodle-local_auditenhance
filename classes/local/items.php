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
 * Queries on stored items, for the per course and per component screens.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class items {
    /**
     * Latest complete run with results in an area.
     *
     * @param string $areaid
     * @return int|null
     */
    public static function get_latest_run_for_area(string $areaid): ?int {
        global $DB;
        $sql = "SELECT MAX(r.runid)
                  FROM {local_auditenhance_result} r
                  JOIN {local_auditenhance_run} run ON run.id = r.runid AND run.status = :status
                 WHERE r.area = :area";
        $runid = $DB->get_field_sql($sql, ['status' => run_manager::STATUS_COMPLETE, 'area' => $areaid]);
        return $runid ? (int)$runid : null;
    }

    /**
     * Item counts per check for one course.
     *
     * @param int $runid
     * @param int $courseid
     * @param string|null $areaid
     * @return array<string, int> Check reference => count.
     */
    public static function count_by_check_for_course(int $runid, int $courseid, ?string $areaid = null): array {
        global $DB;
        $params = ['runid' => $runid, 'courseid' => $courseid];
        $where = 'runid = :runid AND courseid = :courseid';
        if ($areaid !== null) {
            $where .= ' AND area = :area';
            $params['area'] = $areaid;
        }
        return array_map('intval', $DB->get_records_sql_menu(
            "SELECT checkname, COUNT(1) FROM {local_auditenhance_item} WHERE $where GROUP BY checkname",
            $params
        ));
    }

    /**
     * Items of one check in one course.
     *
     * @param int $runid
     * @param int $courseid
     * @param string $checkref
     * @param int $limit 0 for all.
     * @return \stdClass[]
     */
    public static function get_for_course(int $runid, int $courseid, string $checkref, int $limit = 0): array {
        global $DB;
        return array_values($DB->get_records(
            'local_auditenhance_item',
            ['runid' => $runid, 'courseid' => $courseid, 'checkname' => $checkref],
            'id',
            '*',
            0,
            $limit
        ));
    }

    /**
     * Courses with items in an area, most items first.
     *
     * @param int $runid
     * @param string $areaid
     * @param int $limitfrom
     * @param int $limitnum
     * @return \stdClass[] With courseid, fullname, shortname, items.
     */
    public static function count_by_course(int $runid, string $areaid, int $limitfrom = 0, int $limitnum = 0): array {
        global $DB;
        $sql = "SELECT i.courseid, c.fullname, c.shortname, COUNT(1) AS items
                  FROM {local_auditenhance_item} i
                  JOIN {course} c ON c.id = i.courseid
                 WHERE i.runid = :runid AND i.area = :area AND i.courseid > 0
              GROUP BY i.courseid, c.fullname, c.shortname
              ORDER BY COUNT(1) DESC, i.courseid";
        return array_values($DB->get_records_sql($sql, ['runid' => $runid, 'area' => $areaid], $limitfrom, $limitnum));
    }

    /**
     * Number of courses with items in an area.
     *
     * @param int $runid
     * @param string $areaid
     * @return int
     */
    public static function count_courses(int $runid, string $areaid): int {
        global $DB;
        return (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT courseid)
                                              FROM {local_auditenhance_item}
                                             WHERE runid = :runid AND area = :area AND courseid > 0",
            ['runid' => $runid, 'area' => $areaid]
        );
    }

    /**
     * Item counts per component in an area, with the highest severity.
     *
     * @param int $runid
     * @param string $areaid
     * @return \stdClass[] With component, items and severities (comma separated).
     */
    public static function count_by_component(int $runid, string $areaid): array {
        global $DB;
        $sql = "SELECT component, severity, COUNT(1) AS items
                  FROM {local_auditenhance_item}
                 WHERE runid = :runid AND area = :area
              GROUP BY component, severity";
        $result = [];
        foreach ($DB->get_recordset_sql($sql, ['runid' => $runid, 'area' => $areaid]) as $row) {
            if (!isset($result[$row->component])) {
                $result[$row->component] = (object)['component' => $row->component, 'items' => 0,
                    'severity' => severity::INFO, 'counts' => []];
            }
            $entry = $result[$row->component];
            $entry->items += (int)$row->items;
            $entry->counts[$row->severity] = (int)$row->items;
            $entry->severity = severity::max($entry->severity, $row->severity);
        }
        uasort($result, fn($a, $b) => [severity::rank($b->severity), $b->items] <=> [severity::rank($a->severity), $a->items]);
        return $result;
    }

    /**
     * Items of one component in an area.
     *
     * @param int $runid
     * @param string $areaid
     * @param string $component
     * @return \stdClass[]
     */
    public static function get_for_component(int $runid, string $areaid, string $component): array {
        global $DB;
        return array_values($DB->get_records(
            'local_auditenhance_item',
            ['runid' => $runid, 'area' => $areaid, 'component' => $component],
            'checkname, id'
        ));
    }

    /**
     * Decoded detail of an item.
     *
     * @param \stdClass $item
     * @return array
     */
    public static function detail(\stdClass $item): array {
        $detail = json_decode($item->detail ?? '', true);
        return is_array($detail) ? $detail : [];
    }
}
