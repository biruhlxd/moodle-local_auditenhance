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

/**
 * Mute and unmute actions on findings.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mute_action implements \local_auditenhance\local\finding_action {
    /**
     * Mute or unmute link for users who may run audits.
     *
     * @param base $check
     * @param finding|null $finding
     * @param bool $muted
     * @return \action_link[]
     */
    public function get_actions(base $check, ?finding $finding, bool $muted): array {
        if (!has_capability('local/auditenhance:run', \context_system::instance())) {
            return [];
        }
        if ($muted) {
            $url = new \moodle_url(
                '/local/auditenhance/mute.php',
                ['check' => $check->get_ref(), 'unmute' => 1, 'sesskey' => sesskey()]
            );
            return [new \action_link($url, get_string('unmute', 'local_auditenhance'))];
        }
        if (!$finding || !$finding->is_failure()) {
            return [];
        }
        $url = new \moodle_url('/local/auditenhance/mute.php', ['check' => $check->get_ref()]);
        return [new \action_link($url, get_string('mute', 'local_auditenhance'))];
    }
}
