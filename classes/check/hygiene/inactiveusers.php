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

namespace local_auditenhance\check\hygiene;

use local_auditenhance\check\base;
use local_auditenhance\local\finding;
use local_auditenhance\local\run_context;
use local_auditenhance\local\settings;
use local_auditenhance\local\severity;

/**
 * Accounts not used for a long time.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class inactiveusers extends base {
    /**
     * Link to the user list.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/user.php');
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        global $DB, $CFG;
        $months = settings::threshold('inactivemonths');
        $select = 'deleted = 0 AND suspended = 0 AND lastaccess > 0 AND lastaccess < :cutoff AND id <> :guestid';
        $params = ['cutoff' => strtotime("-{$months} months"), 'guestid' => $CFG->siteguest];
        $count = $DB->count_records_select('user', $select, $params);
        if (!$count) {
            return finding::pass($this->str('ok', $months));
        }
        $sample = [];
        foreach ($DB->get_records_select('user', $select, $params, 'lastaccess', '*', 0, 10) as $user) {
            $date = userdate($user->lastaccess, get_string('strftimedate', 'langconfig'));
            $sample[] = ['label' => fullname($user) . ' (' . $date . ')',
                'url' => new \moodle_url('/user/profile.php', ['id' => $user->id])];
        }
        $a = (object)['count' => $count, 'months' => $months];
        return finding::fail(severity::LOW, $this->str('fail', $a), [], $count)
            ->with_sample($sample)
            ->with_link($this->get_fix_url());
    }
}
