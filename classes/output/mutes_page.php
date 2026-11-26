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

use local_auditenhance\local\mutes;
use local_auditenhance\local\registry;

/**
 * Muted checks with their reasons.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mutes_page implements \renderable, \templatable {
    /**
     * Data for the template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $DB;
        $registry = registry::instance();
        $canrun = has_capability('local/auditenhance:run', \context_system::instance());
        $data = ['mutes' => []];
        foreach (mutes::get_all() as $ref => $mute) {
            $check = $registry->get_check($ref);
            $area = $check ? $registry->get_area($check->get_area()) : null;
            $user = $mute->userid ? $DB->get_record('user', ['id' => $mute->userid]) : null;
            $data['mutes'][] = [
                'name' => $check ? $check->get_name() : $ref,
                'area' => $area ? $area->get_name() : '',
                'reason' => format_text($mute->reason, FORMAT_PLAIN),
                'user' => $user ? fullname($user) : '-',
                'time' => userdate($mute->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                'unmuteurl' => $canrun ? (new \moodle_url(
                    '/local/auditenhance/mute.php',
                    ['check' => $ref, 'unmute' => 1, 'sesskey' => sesskey()]
                ))->out(false) : null,
            ];
        }
        $data['hasmutes'] = !empty($data['mutes']);
        $data['itemmutes'] = [];
        foreach (\local_auditenhance\local\item_mutes::get_all() as $mute) {
            $check = $registry->get_check($mute->checkname);
            $user = $mute->userid ? $DB->get_record('user', ['id' => $mute->userid]) : null;
            $data['itemmutes'][] = [
                'check' => $check ? $check->get_name() : $mute->checkname,
                'label' => $mute->itemkey === '' ? get_string('muteditemsof', 'local_auditenhance', $mute->component)
                    : (string)$mute->label,
                'reason' => format_text((string)$mute->reason, FORMAT_PLAIN),
                'user' => $user ? fullname($user) : '-',
                'time' => userdate($mute->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                'unmuteurl' => $canrun ? (new \moodle_url(
                    '/local/auditenhance/muteitem.php',
                    ['unmute' => $mute->id, 'sesskey' => sesskey()]
                ))->out(false) : null,
            ];
        }
        $data['hasitemmutes'] = !empty($data['itemmutes']);
        return $data;
    }
}
