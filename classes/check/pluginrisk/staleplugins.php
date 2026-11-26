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
use local_auditenhance\local\settings;
use local_auditenhance\local\severity;

/**
 * Add-on plugins whose version is many years old.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class staleplugins extends plugin_check_base {
    /**
     * Date of a plugin version number (YYYYMMDDXX), or null when it is not a date.
     *
     * @param mixed $version
     * @return int|null
     */
    public static function version_date($version): ?int {
        if (!preg_match('/^(\d{4})(\d{2})(\d{2})/', (string)(int)$version, $m)) {
            return null;
        }
        if ((int)$m[1] < 2000 || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            return null;
        }
        return mktime(0, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
    }

    /**
     * Analyse a plugin.
     *
     * @param run_context $context
     * @param scanned_plugin $plugin
     */
    public function analyse_plugin(run_context $context, scanned_plugin $plugin): void {
        if ($plugin->info->is_standard()) {
            return;
        }
        $date = self::version_date($plugin->info->versiondisk);
        $years = settings::threshold('staleyears');
        if ($date !== null && $date < strtotime("-{$years} years")) {
            $this->report(
                $context,
                $plugin,
                'stale',
                severity::LOW,
                'version.php',
                0,
                userdate($date, get_string('strftimedate', 'langconfig'))
            );
        }
    }
}
