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

namespace local_auditenhance\output;

use local_auditenhance\check\base;
use local_auditenhance\local\finding;
use local_auditenhance\local\registry;
use local_auditenhance\local\severity;

/**
 * Turns a check and its finding into template data.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class finding_exporter {
    /**
     * Template data of one check.
     *
     * @param base $check
     * @param array|null $entry Entry of results::get_current(): finding, source, time, runid.
     * @param \stdClass|null $mute Mute record when muted.
     * @param \renderer_base $output
     * @return array
     */
    public static function export(base $check, ?array $entry, ?\stdClass $mute, \renderer_base $output): array {
        /** @var finding|null $finding */
        $finding = $entry['finding'] ?? null;
        $registry = registry::instance();
        $area = $registry->get_area($check->get_area());
        $status = $finding ? $finding->status : 'none';
        $fixurl = $check->get_fix_url();
        $data = [
            'ref' => $check->get_ref(),
            'domid' => 'local-auditenhance-' . preg_replace('/[^a-z0-9_]/', '', $check->get_ref()),
            'name' => $check->get_name(),
            'areaname' => $area ? $area->get_name() : $check->get_area(),
            'areaurl' => $area ? $area->get_url()->out(false) : null,
            'status' => $status,
            'statusname' => get_string('status_' . $status, 'local_auditenhance'),
            'failed' => $finding && $finding->is_failure(),
            'severity' => $finding && $finding->is_failure() ? $finding->severity : null,
            'severityname' => $finding && $finding->is_failure() ? severity::get_name($finding->severity) : null,
            'summary' => $finding ? $finding->summary : get_string('notscannedyet', 'local_auditenhance'),
            'why' => $check->get_explanation(),
            'fix' => $check->get_fix(),
            'link' => $finding && $finding->link ? $finding->link : ($fixurl ? $fixurl->out(false) : null),
            'sample' => array_values($finding->details['sample'] ?? []),
            'itemcount' => $finding ? $finding->itemcount : 0,
            'muted' => $mute !== null,
            'mutereason' => $mute ? (string)$mute->reason : null,
            'when' => !empty($entry['time']) ? userdate($entry['time'], get_string('strftimedatetimeshort', 'langconfig')) : null,
            'live' => ($entry['source'] ?? '') === 'live',
            'actions' => [],
        ];
        $data['hassample'] = !empty($data['sample']);
        foreach ($registry->get_finding_actions() as $action) {
            foreach ($action->get_actions($check, $finding, $mute !== null) as $link) {
                $data['actions'][] = ['url' => $link->url->out(false), 'text' => $link->text,
                    'primary' => !empty($link->attributes['data-primary'])];
            }
        }
        $data['hasactions'] = !empty($data['actions']);
        return $data;
    }
}
