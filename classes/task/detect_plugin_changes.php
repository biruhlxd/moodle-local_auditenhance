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

namespace local_auditenhance\task;

use local_auditenhance\local\run_manager;

/**
 * Notices installed or upgraded plugins and, when the setting is on, queues a plugin risk scan.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class detect_plugin_changes extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskdetectpluginchanges', 'local_auditenhance');
    }

    /**
     * Versions of all plugins on disk.
     *
     * @return array<string, int|string>
     */
    public static function get_versions(): array {
        $versions = [];
        foreach (\core_plugin_manager::instance()->get_plugins() as $plugins) {
            foreach ($plugins as $plugin) {
                $versions[$plugin->component] = $plugin->versiondisk ?? 0;
            }
        }
        ksort($versions);
        return $versions;
    }

    /**
     * Compare with the last known versions.
     *
     * @return string[] Components that were added or changed.
     */
    public static function detect_changes(): array {
        $current = self::get_versions();
        $known = json_decode((string)get_config('local_auditenhance', 'pluginversions'), true);
        set_config('pluginversions', json_encode($current), 'local_auditenhance');
        if (!is_array($known)) {
            // First run: remember the versions only.
            return [];
        }
        $changed = [];
        foreach ($current as $component => $version) {
            if (!array_key_exists($component, $known) || (string)$known[$component] !== (string)$version) {
                $changed[] = $component;
            }
        }
        return $changed;
    }

    /**
     * Run the task.
     */
    public function execute() {
        $changed = self::detect_changes();
        if (!$changed) {
            return;
        }
        mtrace('Installed or upgraded plugins: ' . implode(', ', $changed));
        $registry = \local_auditenhance\local\registry::instance();
        if (get_config('local_auditenhance', 'scanoninstall') && $registry->get_area('pluginrisk')) {
            $runid = run_manager::queue(['pluginrisk'], 'install', 0);
            mtrace('Queued plugin risk scan ' . $runid);
        }
    }
}
