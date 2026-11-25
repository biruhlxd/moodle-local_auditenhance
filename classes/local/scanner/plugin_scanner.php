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

namespace local_auditenhance\local\scanner;

use local_auditenhance\local\plugins\plugin_check;
use local_auditenhance\local\plugins\scanned_plugin;
use local_auditenhance\local\run_context;

/**
 * Walks installed plugins in batches and feeds them to plugin checks.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_scanner implements scanner {
    /** @var string[]|null Components to scan, cached for the request. */
    protected ?array $components = null;

    /**
     * Id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'plugins';
    }

    /**
     * Interface of the checks fed.
     *
     * @return string
     */
    public function get_check_interface(): string {
        return plugin_check::class;
    }

    /**
     * Needed when there are plugins to scan.
     *
     * @param array $checks
     * @return bool
     */
    public function is_needed(array $checks): bool {
        return (bool)$this->get_components();
    }

    /**
     * Components to scan: add-on plugins (and standard ones when the setting says so), minus exclusions.
     *
     * @return string[]
     */
    public function get_components(): array {
        if ($this->components !== null) {
            return $this->components;
        }
        $includecore = (bool)get_config('local_auditenhance', 'includecore');
        $excluded = array_filter(array_map('trim', explode(',', (string)get_config('local_auditenhance', 'excludeplugins'))));
        $excluded[] = 'local_auditenhance';
        $components = [];
        foreach (\core_plugin_manager::instance()->get_plugins() as $plugins) {
            foreach ($plugins as $plugin) {
                if (in_array($plugin->component, $excluded, true)) {
                    continue;
                }
                if (!$includecore && $plugin->is_standard()) {
                    continue;
                }
                $components[] = $plugin->component;
            }
        }
        sort($components);
        return $this->components = $components;
    }

    /**
     * Process a batch of plugins.
     *
     * @param run_context $context
     * @param array $checks
     * @param string|null $cursor Index of the next plugin.
     * @return string|null
     */
    public function process_batch(run_context $context, array $checks, ?string $cursor): ?string {
        $components = $this->get_components();
        $start = (int)$cursor;
        $batchsize = max(1, (int)$context->get_config('pluginbatchsize', 10));
        $manager = \core_plugin_manager::instance();
        foreach (array_slice($components, $start, $batchsize) as $component) {
            $info = $manager->get_plugin_info($component);
            if (!$info) {
                continue;
            }
            $plugin = new scanned_plugin($info);
            foreach ($checks as $check) {
                $check->analyse_plugin($context, $plugin);
            }
        }
        $next = $start + $batchsize;
        return $next >= count($components) ? null : (string)$next;
    }

    /**
     * Progress.
     *
     * @param string|null $cursor
     * @return float
     */
    public function get_progress(?string $cursor): float {
        $count = count($this->get_components());
        return $count ? min(1, (int)$cursor / $count) : 1;
    }

    /**
     * Forget the cached list of components.
     */
    public function reset(): void {
        $this->components = null;
    }
}
