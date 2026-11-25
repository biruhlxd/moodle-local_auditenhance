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

namespace local_auditenhance\output;

use local_auditenhance\local\items;
use local_auditenhance\local\registry;
use local_auditenhance\local\run_manager;
use local_auditenhance\local\severity;

/**
 * Plugin risk by plugin: findings per plugin with severity, and the findings of one plugin.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugins_page implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param string $areaid
     * @param string|null $component Show the findings of this plugin only.
     */
    public function __construct(
        /** @var string Area id. */
        protected string $areaid,
        /** @var string|null Component. */
        protected ?string $component = null,
    ) {
    }

    /**
     * Display name of a component.
     *
     * @param string $component
     * @return string
     */
    public static function component_name(string $component): string {
        $info = \core_plugin_manager::instance()->get_plugin_info($component);
        return $info ? $info->displayname . ' (' . $component . ')' : $component;
    }

    /**
     * Data for the template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $runid = items::get_latest_run_for_area($this->areaid);
        $data = ['hasrun' => $runid !== null, 'plugins' => [], 'detail' => $this->component !== null];
        if (!$runid) {
            return $data;
        }
        $data['rundate'] = userdate(run_manager::get_run($runid)->finished);
        $registry = registry::instance();
        $scope = $this->component === null ? 'area:' . $this->areaid : 'plugin:' . $this->component;
        $exports = [];
        foreach ($registry->get_export_formats($scope) as $format) {
            $exports[] = ['name' => $format->get_name(), 'url' => (new \moodle_url(
                '/local/auditenhance/export.php',
                ['format' => $format->get_id(), 'scope' => $scope, 'runid' => $runid, 'sesskey' => sesskey()]
            ))->out(false)];
        }
        $data['exports'] = $exports;
        $data['hasexports'] = !empty($exports);

        if ($this->component !== null) {
            $data['pluginname'] = self::component_name($this->component);
            $data['backurl'] = (new \moodle_url('/local/auditenhance/plugins.php'))->out(false);
            $data['items'] = [];
            foreach (items::get_for_component($runid, $this->areaid, $this->component) as $item) {
                $check = $registry->get_check($item->checkname);
                $detail = items::detail($item);
                $data['items'][] = [
                    'check' => $check ? $check->get_name() : $item->checkname,
                    'label' => $detail['label'] ?? $item->issue,
                    'severity' => $item->severity,
                    'severityname' => severity::get_name($item->severity),
                ];
            }
            usort($data['items'], fn($a, $b) => severity::rank($b['severity']) <=> severity::rank($a['severity']));
            $data['hasitems'] = !empty($data['items']);
            return $data;
        }

        foreach (items::count_by_component($runid, $this->areaid) as $row) {
            $counts = [];
            foreach (severity::all() as $sev) {
                if (!empty($row->counts[$sev])) {
                    $counts[] = ['severity' => $sev, 'name' => severity::get_name($sev), 'count' => $row->counts[$sev]];
                }
            }
            $data['plugins'][] = [
                'name' => self::component_name($row->component),
                'url' => (new \moodle_url('/local/auditenhance/plugins.php', ['component' => $row->component]))->out(false),
                'severity' => $row->severity,
                'severityname' => severity::get_name($row->severity),
                'items' => $row->items,
                'counts' => $counts,
            ];
        }
        $data['hasplugins'] = !empty($data['plugins']);
        return $data;
    }
}
