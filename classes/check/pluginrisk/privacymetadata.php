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
 * Privacy metadata that does not list a table with personal data.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class privacymetadata extends plugin_check_base {
    /**
     * Analyse a plugin.
     *
     * @param run_context $context
     * @param scanned_plugin $plugin
     */
    public function analyse_plugin(run_context $context, scanned_plugin $plugin): void {
        $provider = $plugin->get_privacy_provider();
        $tables = $plugin->get_personal_tables();
        if (
            !$provider || !$tables || !class_exists($provider)
                || !is_subclass_of($provider, \core_privacy\local\metadata\provider::class)
        ) {
            return;
        }
        $listed = $this->get_listed_tables($plugin, $provider);
        if ($listed === null) {
            return;
        }
        foreach (array_keys($tables) as $table) {
            if (!in_array($table, $listed, true)) {
                $this->report($context, $plugin, 'tablenotlisted', severity::MEDIUM, 'classes/privacy/provider.php', 0, $table);
            }
        }
    }

    /**
     * Tables listed in the provider's metadata.
     *
     * @param scanned_plugin $plugin
     * @param string $provider
     * @return string[]|null Null when the metadata cannot be read.
     */
    public function get_listed_tables(scanned_plugin $plugin, string $provider): ?array {
        try {
            $collection = $provider::get_metadata(new \core_privacy\local\metadata\collection($plugin->get_component()));
        } catch (\Throwable $e) {
            return null;
        }
        $tables = [];
        foreach ($collection->get_collection() as $type) {
            if ($type instanceof \core_privacy\local\metadata\types\database_table) {
                $tables[] = $type->get_name();
            }
        }
        return $tables;
    }
}
