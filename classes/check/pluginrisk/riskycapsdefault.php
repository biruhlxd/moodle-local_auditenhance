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

use local_auditenhance\local\capability_helper;
use local_auditenhance\local\plugins\plugin_check_base;
use local_auditenhance\local\plugins\scanned_plugin;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Risky capabilities that plugins give to students, guests or all users by default.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class riskycapsdefault extends plugin_check_base {
    /** @var string[] Archetypes every learner, guest or user has. */
    public const OPEN_ARCHETYPES = ['student', 'guest', 'user', 'frontpage'];

    /**
     * Analyse a plugin.
     *
     * @param run_context $context
     * @param scanned_plugin $plugin
     */
    public function analyse_plugin(run_context $context, scanned_plugin $plugin): void {
        foreach ($plugin->get_capabilities() as $name => $definition) {
            $risk = (int)($definition['riskbitmask'] ?? 0);
            $severity = capability_helper::risk_severity($risk);
            if ($severity === null) {
                continue;
            }
            $open = [];
            foreach ((array)($definition['archetypes'] ?? []) as $archetype => $permission) {
                if ($permission == CAP_ALLOW && in_array($archetype, self::OPEN_ARCHETYPES, true)) {
                    $open[] = $archetype;
                }
            }
            if ($open) {
                $this->report(
                    $context,
                    $plugin,
                    'riskydefault',
                    $severity,
                    'db/access.php',
                    0,
                    $name . ': ' . implode(', ', $open) . ' (' . capability_helper::risk_names($risk) . ')'
                );
            }
        }
    }
}
