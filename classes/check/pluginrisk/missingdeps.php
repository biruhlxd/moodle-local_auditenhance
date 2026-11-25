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

use local_auditenhance\local\plugins\plugin_check_base;
use local_auditenhance\local\plugins\scanned_plugin;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Required plugins that are missing or too old.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class missingdeps extends plugin_check_base {
    /**
     * Severity when failing.
     *
     * @return string
     */
    public function get_default_severity(): string {
        return severity::HIGH;
    }

    /**
     * Analyse a plugin.
     *
     * @param run_context $context
     * @param scanned_plugin $plugin
     */
    public function analyse_plugin(run_context $context, scanned_plugin $plugin): void {
        global $CFG;
        $manager = \core_plugin_manager::instance();
        foreach ((array)$plugin->info->get_other_required_plugins() as $component => $version) {
            $other = $manager->get_plugin_info($component);
            if (!$other || $other->versiondisk === null) {
                $this->report($context, $plugin, 'missingdependency', severity::HIGH, 'version.php', 0, $component);
            } else if ($version != ANY_VERSION && (int)$other->versiondisk < (int)$version) {
                $this->report(
                    $context,
                    $plugin,
                    'olddependency',
                    severity::HIGH,
                    'version.php',
                    0,
                    $component . ' ' . $version
                );
            }
        }
        if (!$plugin->info->is_core_dependency_satisfied($CFG->version)) {
            $this->report(
                $context,
                $plugin,
                'requirescore',
                severity::HIGH,
                'version.php',
                0,
                (string)$plugin->info->versionrequires
            );
        }
    }
}
