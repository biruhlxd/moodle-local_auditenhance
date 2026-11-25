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
 * Add-on plugins that are installed but not used anywhere.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unusedplugins extends base {
    /**
     * Heavy: counts instances of every plugin.
     *
     * @return bool
     */
    public function is_quick(): bool {
        return false;
    }

    /**
     * Link to the plugins overview.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/plugins.php', ['contribonly' => 1]);
    }

    /**
     * Number of uses of each plugin of the types that have instances.
     *
     * @return array<string, array<string, int>> Plugin type => plugin name => count.
     */
    public static function get_usage(): array {
        global $DB;
        $usage = ['mod' => [], 'block' => [], 'qtype' => [], 'format' => []];
        $rows = $DB->get_records_sql("SELECT m.name, COUNT(cm.id) AS uses
                                        FROM {modules} m
                                   LEFT JOIN {course_modules} cm ON cm.module = m.id
                                    GROUP BY m.name");
        foreach ($rows as $row) {
            $usage['mod'][$row->name] = (int)$row->uses;
        }
        $rows = $DB->get_records_sql("SELECT blockname, COUNT(1) AS uses FROM {block_instances} GROUP BY blockname");
        foreach ($rows as $row) {
            $usage['block'][$row->blockname] = (int)$row->uses;
        }
        $rows = $DB->get_records_sql("SELECT qtype, COUNT(1) AS uses FROM {question} GROUP BY qtype");
        foreach ($rows as $row) {
            $usage['qtype'][$row->qtype] = (int)$row->uses;
        }
        $rows = $DB->get_records_sql(
            "SELECT format, COUNT(1) AS uses FROM {course} WHERE id <> :siteid GROUP BY format",
            ['siteid' => SITEID]
        );
        foreach ($rows as $row) {
            $usage['format'][$row->format] = (int)$row->uses;
        }
        return $usage;
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        $usage = self::get_usage();
        $manager = \core_plugin_manager::instance();
        $sample = [];
        foreach ($usage as $type => $counts) {
            foreach ($manager->get_plugins_of_type($type) as $plugin) {
                if ($plugin->is_standard() || !empty($counts[$plugin->name])) {
                    continue;
                }
                $sample[] = ['label' => $plugin->displayname . ' (' . $plugin->component . ')',
                    'url' => new \moodle_url('/admin/plugins.php', ['contribonly' => 1])];
            }
        }
        if (!$sample) {
            return finding::pass($this->str('ok'));
        }
        return finding::fail(severity::LOW, $this->str('fail', count($sample)), [], count($sample))
            ->with_sample($sample)
            ->with_link($this->get_fix_url());
    }
}
