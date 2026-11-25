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

namespace local_auditenhance\check\pluginrisk;

use local_auditenhance\check\base;
use local_auditenhance\local\capability_helper;
use local_auditenhance\local\finding;
use local_auditenhance\local\item;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Role changes on the site that give risky capabilities to students, guests or all users.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class riskyoverrides extends base {
    /**
     * Heavy: reads all role capabilities.
     *
     * @return bool
     */
    public function is_quick(): bool {
        return false;
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
        [$insql, $params] = $DB->get_in_or_equal(
            \local_auditenhance\check\pluginrisk\riskycapsdefault::OPEN_ARCHETYPES,
            SQL_PARAMS_NAMED
        );
        $sql = "SELECT rc.id, rc.roleid, rc.contextid, rc.capability, c.riskbitmask, c.component, r.archetype,
                       r.shortname, r.name
                  FROM {role_capabilities} rc
                  JOIN {capabilities} c ON c.name = rc.capability
                  JOIN {role} r ON r.id = rc.roleid
                 WHERE rc.permission = :allow AND r.archetype $insql
              ORDER BY rc.contextid, r.sortorder, rc.capability";
        $params['allow'] = CAP_ALLOW;
        $syscontextid = \context_system::instance()->id;
        $defaults = [];
        foreach ($DB->get_recordset_sql($sql, $params) as $record) {
            $severity = capability_helper::risk_severity((int)$record->riskbitmask);
            if ($severity === null) {
                continue;
            }
            if ((int)$record->contextid === (int)$syscontextid) {
                $defaults[$record->archetype] ??= get_default_capabilities($record->archetype);
                if (($defaults[$record->archetype][$record->capability] ?? null) == CAP_ALLOW) {
                    continue;
                }
            }
            $ctx = \context::instance_by_id($record->contextid, IGNORE_MISSING);
            $where = $ctx ? $ctx->get_context_name(true, true) : (string)$record->contextid;
            $context->add_item($this, new item(
                'riskyoverride',
                $severity,
                (int)$record->contextid,
                0,
                (string)$record->component,
                (int)$record->roleid,
                $record->capability,
                0,
                [
                    'label' => role_get_name($record) . ': ' . $record->capability . ' (' . $where . ', ' .
                        capability_helper::risk_names((int)$record->riskbitmask) . ')',
                    'url' => (new \moodle_url('/admin/roles/permissions.php', ['contextid' => $record->contextid]))->out(false),
                ]
            ));
        }
        return $this->finding_from_items($context);
    }
}
