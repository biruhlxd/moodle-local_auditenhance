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

use local_auditenhance\check\base;

/**
 * Adds actions (links or buttons) next to each finding.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface finding_action {
    /**
     * Actions for a finding.
     *
     * @param base $check The check.
     * @param finding|null $finding Its current finding, null if it has not run.
     * @param bool $muted Whether the check is muted.
     * @return \action_link[]
     */
    public function get_actions(base $check, ?finding $finding, bool $muted): array;
}
