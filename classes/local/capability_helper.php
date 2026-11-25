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

/**
 * Helpers for capabilities and their risks.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class capability_helper {
    /** @var int Risks treated as dangerous when given to students, guests or all users. */
    public const RISKY_MASK = RISK_CONFIG | RISK_MANAGETRUST | RISK_XSS | RISK_DATALOSS | RISK_PERSONAL;

    /**
     * Severity of giving a capability with these risks to everyone.
     *
     * @param int $riskbitmask
     * @return string|null Null when none of the risks is dangerous.
     */
    public static function risk_severity(int $riskbitmask): ?string {
        if ($riskbitmask & (RISK_CONFIG | RISK_MANAGETRUST)) {
            return severity::CRITICAL;
        }
        if ($riskbitmask & (RISK_XSS | RISK_DATALOSS)) {
            return severity::HIGH;
        }
        if ($riskbitmask & RISK_PERSONAL) {
            return severity::MEDIUM;
        }
        return null;
    }

    /**
     * Names of the risks in a bitmask.
     *
     * @param int $riskbitmask
     * @return string Comma separated list.
     */
    public static function risk_names(int $riskbitmask): string {
        $names = [];
        foreach (
            ['riskconfig' => RISK_CONFIG, 'riskmanagetrust' => RISK_MANAGETRUST, 'riskxss' => RISK_XSS,
                'riskdataloss' => RISK_DATALOSS, 'riskpersonal' => RISK_PERSONAL, 'riskspam' => RISK_SPAM] as $string => $bit
        ) {
            if ($riskbitmask & $bit) {
                $names[] = get_string($string . 'short', 'admin');
            }
        }
        return implode(', ', $names);
    }

    /**
     * Ids of the roles that every user, guest or visitor of the front page has.
     *
     * @return int[]
     */
    public static function everyone_role_ids(): array {
        global $CFG;
        $roles = [];
        foreach (['defaultuserroleid', 'guestroleid', 'defaultfrontpageroleid'] as $setting) {
            if (!empty($CFG->$setting)) {
                $roles[(int)$CFG->$setting] = (int)$CFG->$setting;
            }
        }
        return array_values($roles);
    }
}
