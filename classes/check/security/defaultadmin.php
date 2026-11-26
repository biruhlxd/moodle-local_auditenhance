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

namespace local_auditenhance\check\security;

use local_auditenhance\check\base;
use local_auditenhance\local\finding;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * The default "admin" username is still used by an administrator.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class defaultadmin extends base {
    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        global $CFG, $DB;
        $user = $DB->get_record('user', ['username' => 'admin', 'deleted' => 0, 'mnethostid' => $CFG->mnet_localhost_id]);
        if (!$user || !empty($user->suspended) || !is_siteadmin($user)) {
            return finding::pass($this->summary('ok'));
        }
        return finding::fail(severity::MEDIUM, $this->summary('fail'))
            ->with_link(new \moodle_url('/user/editadvanced.php', ['id' => $user->id]));
    }
}
