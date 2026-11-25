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

namespace local_auditenhance;

/**
 * Plugin information for the fixture plugins in tests/fixtures/plugins.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fixture_plugininfo extends \core\plugininfo\local {
    /**
     * Plugin information of a fixture plugin.
     *
     * @param string $name Folder name in tests/fixtures/plugins, for example 'riskyfixture'.
     * @return \core\plugininfo\base
     */
    public static function make(string $name): \core\plugininfo\base {
        return self::make_plugin_instance(
            'local',
            __DIR__ . '/plugins',
            $name,
            __DIR__ . '/plugins/' . $name,
            self::class,
            \core_plugin_manager::instance()
        );
    }

    /**
     * Read version.php from the fixture folder, which the plugin manager does not know.
     */
    public function load_disk_version() {
        $this->versiondisk = null;
        $this->versionrequires = null;
        $this->pluginsupported = null;
        $this->pluginincompatible = null;
        $this->dependencies = [];
        $file = $this->rootdir . '/version.php';
        if (!file_exists($file)) {
            return;
        }
        $plugin = new \stdClass();
        include($file);
        $this->versiondisk = $plugin->version ?? null;
        $this->versionrequires = $plugin->requires ?? null;
        $this->pluginsupported = $plugin->supported ?? null;
        $this->pluginincompatible = $plugin->incompatible ?? null;
        $this->dependencies = $plugin->dependencies ?? [];
    }
}
