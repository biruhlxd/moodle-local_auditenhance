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

namespace local_auditenhance\local\scanner;

use local_auditenhance\local\content\brickfield;
use local_auditenhance\local\content\brickfield_mapped;
use local_auditenhance\local\item;
use local_auditenhance\local\run_context;

/**
 * Reads the results of the Brickfield accessibility toolkit in batches and records them as items of the matching checks.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class brickfield_scanner implements scanner {
    /**
     * Id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'brickfield';
    }

    /**
     * Interface of the checks fed.
     *
     * @return string
     */
    public function get_check_interface(): string {
        return brickfield_mapped::class;
    }

    /**
     * Needed when Brickfield has results.
     *
     * @param array $checks
     * @return bool
     */
    public function is_needed(array $checks): bool {
        return brickfield::in_use();
    }

    /**
     * Process one batch of results.
     *
     * @param run_context $context
     * @param array $checks
     * @param string|null $cursor
     * @return string|null
     */
    public function process_batch(run_context $context, array $checks, ?string $cursor): ?string {
        global $DB;
        $map = [];
        foreach ($checks as $check) {
            foreach ($check->get_brickfield_checks() as $shortname) {
                $map[$shortname][] = $check;
            }
        }
        if (!$map) {
            return null;
        }
        $batchsize = max(1, (int)$context->get_config('batchsize', 200));
        [$insql, $params] = $DB->get_in_or_equal(array_keys($map), SQL_PARAMS_NAMED);
        $sql = "SELECT r.id, r.errorcount, ch.shortname, ch.severity, a.contextid, a.courseid, a.component, a.itemid,
                       a.fieldorarea
                  FROM {tool_brickfield_results} r
                  JOIN {tool_brickfield_checks} ch ON ch.id = r.checkid
                  JOIN {tool_brickfield_content} co ON co.id = r.contentid AND co.iscurrent = 1
                  JOIN {tool_brickfield_areas} a ON a.id = co.areaid
                 WHERE r.id > :afterid AND r.errorcount > 0 AND ch.shortname $insql
              ORDER BY r.id";
        $params['afterid'] = (int)$cursor;
        $rows = $DB->get_records_sql($sql, $params, 0, $batchsize);
        $lastid = 0;
        foreach ($rows as $row) {
            $lastid = (int)$row->id;
            $ctx = \context::instance_by_id($row->contextid, IGNORE_MISSING);
            $label = ($ctx ? $ctx->get_context_name(false, true) : $row->component) . ': ' .
                brickfield::check_description($row->shortname);
            foreach ($map[$row->shortname] as $check) {
                $context->add_item($check, new item(
                    'bf_' . $row->shortname,
                    brickfield::to_severity((int)$row->severity),
                    (int)$row->contextid,
                    (int)$row->courseid,
                    (string)$row->component,
                    (int)$row->itemid,
                    (string)$row->fieldorarea,
                    0,
                    [
                        'label' => $label,
                        'url' => $ctx ? $ctx->get_url()->out(false) : null,
                        'count' => (int)$row->errorcount,
                        'source' => 'brickfield',
                    ]
                ));
            }
        }
        return count($rows) < $batchsize ? null : (string)$lastid;
    }

    /**
     * Progress is not known in advance.
     *
     * @param string|null $cursor
     * @return float
     */
    public function get_progress(?string $cursor): float {
        return $cursor ? 0.5 : 0;
    }
}
