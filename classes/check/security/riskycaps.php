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
use local_auditenhance\local\capability_helper;
use local_auditenhance\local\finding;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Risky capabilities given to every user, guest or front page visitor.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class riskycaps extends base {
    /**
     * Severity when failing.
     *
     * @return string
     */
    public function get_default_severity(): string {
        return severity::CRITICAL;
    }

    /**
     * Link to the roles page.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/roles/manage.php');
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        global $DB;
        $roleids = capability_helper::everyone_role_ids();
        if (!$roleids) {
            return finding::skip($this->summary('noroles'));
        }
        [$insql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
        $sql = "SELECT rc.id, rc.roleid, rc.capability, c.riskbitmask, r.archetype, r.shortname, r.name
                  FROM {role_capabilities} rc
                  JOIN {capabilities} c ON c.name = rc.capability
                  JOIN {role} r ON r.id = rc.roleid
                 WHERE rc.contextid = :contextid AND rc.permission = :allow AND rc.roleid $insql
              ORDER BY r.sortorder, rc.capability";
        $params += ['contextid' => \context_system::instance()->id, 'allow' => CAP_ALLOW];
        $defaults = [];
        $worst = null;
        $sample = [];
        $count = 0;
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $severity = capability_helper::risk_severity((int)$record->riskbitmask);
            if ($severity === null) {
                continue;
            }
            $archetype = (string)$record->archetype;
            if (!isset($defaults[$archetype])) {
                $defaults[$archetype] = $archetype === '' ? [] : get_default_capabilities($archetype);
            }
            if (($defaults[$archetype][$record->capability] ?? null) == CAP_ALLOW) {
                continue;
            }
            $count++;
            $worst = $worst === null ? $severity : severity::max($worst, $severity);
            $sample[] = [
                'label' => role_get_name($record) . ': ' . $record->capability . ' (' .
                    capability_helper::risk_names((int)$record->riskbitmask) . ')',
                'url' => new \moodle_url('/admin/roles/define.php', ['action' => 'view', 'roleid' => $record->roleid]),
            ];
        }
        if (!$count) {
            return finding::pass($this->summary('ok'));
        }
        return finding::fail($worst, $this->summary('fail', $count), [], $count)
            ->with_sample($sample)
            ->with_link($this->get_fix_url());
    }
}
