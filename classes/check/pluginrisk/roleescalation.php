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
use local_auditenhance\local\finding;
use local_auditenhance\local\item;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Roles that can assign a more powerful role.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class roleescalation extends base {
    /** @var int Risks that make a role powerful. */
    public const POWER_MASK = RISK_CONFIG | RISK_MANAGETRUST;

    /**
     * Heavy: compares the capabilities of every pair of roles.
     *
     * @return bool
     */
    public function is_quick(): bool {
        return false;
    }

    /**
     * Severity when failing.
     *
     * @return string
     */
    public function get_default_severity(): string {
        return severity::HIGH;
    }

    /**
     * Link to the role assignment rules.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/roles/allow.php', ['mode' => 'assign']);
    }

    /**
     * Powerful capabilities allowed to each role in the system context.
     *
     * @return array<int, string[]> Role id => capability names.
     */
    public static function get_role_powers(): array {
        global $DB;
        $sql = "SELECT rc.id, rc.roleid, rc.capability
                  FROM {role_capabilities} rc
                  JOIN {capabilities} c ON c.name = rc.capability
                 WHERE rc.contextid = :contextid AND rc.permission = :allow";
        $powers = [];
        $records = $DB->get_records_sql($sql, ['contextid' => \context_system::instance()->id, 'allow' => CAP_ALLOW]);
        $risks = $DB->get_records_menu('capabilities', null, '', 'name, riskbitmask');
        foreach ($records as $record) {
            if (((int)($risks[$record->capability] ?? 0)) & self::POWER_MASK) {
                $powers[(int)$record->roleid][] = $record->capability;
            }
        }
        return $powers;
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        global $DB;
        $roles = $DB->get_records('role', null, '', 'id, name, shortname, archetype');
        $powers = self::get_role_powers();
        foreach ($DB->get_records('role_allow_assign') as $allow) {
            $from = $roles[$allow->roleid] ?? null;
            $to = $roles[$allow->allowassign] ?? null;
            if (!$from || !$to || $from->archetype === 'manager' || $from->id == $to->id) {
                continue;
            }
            $extra = array_diff($powers[$to->id] ?? [], $powers[$from->id] ?? []);
            if (!$extra) {
                continue;
            }
            $a = (object)['from' => role_get_name($from), 'to' => role_get_name($to), 'count' => count($extra)];
            $context->add_item($this, new item(
                'escalation',
                severity::HIGH,
                \context_system::instance()->id,
                0,
                'core_role',
                (int)$allow->id,
                'role_allow_assign',
                0,
                [
                    'label' => get_string('roleescalationitem', 'local_auditenhance', $a),
                    'url' => $this->get_fix_url()->out(false),
                ]
            ));
        }
        return $this->finding_from_items($context);
    }
}
