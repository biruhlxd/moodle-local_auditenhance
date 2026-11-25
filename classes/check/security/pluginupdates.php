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
use local_auditenhance\local\finding;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Plugins with available updates.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pluginupdates extends base {
    /**
     * Link to the plugins overview with updates only.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/plugins.php', ['updatesonly' => 1]);
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        $checker = \core\update\checker::instance();
        if (!$checker->enabled()) {
            return finding::fail(severity::INFO, $this->str('disabled'))
                ->with_link(new \moodle_url('/admin/settings.php', ['section' => 'updatenotifications']));
        }
        if (!$checker->get_last_timefetched()) {
            return finding::fail(severity::INFO, $this->str('notfetched'))->with_link($this->get_fix_url());
        }
        $sample = [];
        foreach (\core_plugin_manager::instance()->get_plugins() as $plugins) {
            foreach ($plugins as $plugin) {
                if ($plugin->is_standard()) {
                    continue;
                }
                $updates = $plugin->available_updates();
                if (empty($updates)) {
                    continue;
                }
                $latest = end($updates);
                $sample[] = ['label' => $plugin->displayname . ' (' . $plugin->component . '): ' .
                    ($plugin->release ?? $plugin->versiondisk) . ' → ' . ($latest->release ?? $latest->version),
                    'url' => $latest->url ?? null];
            }
        }
        if (!$sample) {
            return finding::pass($this->str('ok'));
        }
        return finding::fail(severity::MEDIUM, $this->str('fail', count($sample)), [], count($sample))
            ->with_sample($sample)
            ->with_link($this->get_fix_url());
    }
}
