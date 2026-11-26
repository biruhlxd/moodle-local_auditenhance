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
use local_auditenhance\local\settings;
use local_auditenhance\local\severity;

/**
 * Web service tokens of administrators that never expire, and tokens nobody uses.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webservicetokens extends base {
    /**
     * Link to the token management page.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/webservice/tokens.php');
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        global $CFG, $DB;
        if (empty($CFG->enablewebservices)) {
            return finding::pass($this->summary('off'));
        }
        $admins = array_filter(array_map('intval', explode(',', (string)$CFG->siteadmins)));
        $sample = [];
        $worst = null;
        $tokens = $DB->get_records(
            'external_tokens',
            ['tokentype' => EXTERNAL_TOKEN_PERMANENT],
            'id',
            'id, userid, name, validuntil, lastaccess, timecreated'
        );
        $cutoff = time() - settings::threshold('tokenidledays') * DAYSECS;
        foreach ($tokens as $token) {
            $label = $token->name !== null && $token->name !== '' ? $token->name : '#' . $token->id;
            if (in_array((int)$token->userid, $admins, true) && empty($token->validuntil)) {
                $sample[] = ['label' => $this->str('adminforever', $label), 'url' => $this->get_fix_url()];
                $worst = severity::MEDIUM;
            } else if ((int)max($token->lastaccess, $token->timecreated) < $cutoff) {
                $sample[] = ['label' => $this->str('unused', $label), 'url' => $this->get_fix_url()];
                $worst = $worst ?? severity::LOW;
            }
        }
        if (!$sample) {
            return finding::pass($this->summary('ok'));
        }
        return finding::fail($worst, $this->summary('fail', count($sample)), [], count($sample))
            ->with_sample($sample)
            ->with_link($this->get_fix_url());
    }
}
