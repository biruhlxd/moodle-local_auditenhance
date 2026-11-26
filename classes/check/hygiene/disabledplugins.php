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

namespace local_auditenhance\check\hygiene;

use local_auditenhance\check\base;
use local_auditenhance\local\finding;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Add-on plugins that are installed but disabled.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class disabledplugins extends base {
    /**
     * Link to the plugins overview.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/plugins.php', ['contribonly' => 1]);
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        $sample = [];
        foreach (\core_plugin_manager::instance()->get_plugins() as $plugins) {
            foreach ($plugins as $plugin) {
                if ($plugin->is_standard() || $plugin->is_enabled() !== false) {
                    continue;
                }
                $sample[] = ['label' => $plugin->displayname . ' (' . $plugin->component . ')',
                    'url' => $plugin->get_settings_url() ?? $this->get_fix_url()];
            }
        }
        if (!$sample) {
            return finding::pass($this->summary('ok'));
        }
        return finding::fail(severity::LOW, $this->summary('fail', count($sample)), [], count($sample))
            ->with_sample($sample)
            ->with_link($this->get_fix_url());
    }
}
