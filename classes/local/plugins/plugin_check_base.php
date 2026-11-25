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

namespace local_auditenhance\local\plugins;

use local_auditenhance\check\base;
use local_auditenhance\local\finding;
use local_auditenhance\local\item;
use local_auditenhance\local\run_context;

/**
 * Base class of checks that look at the code and definitions of plugins.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class plugin_check_base extends base implements plugin_check {
    /**
     * Plugin checks only run in the audit task.
     *
     * @return bool
     */
    public function is_quick(): bool {
        return false;
    }

    /**
     * Build the finding from the items found by the scan.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        return $this->finding_from_items($context);
    }

    /**
     * Link to the plugins overview.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/local/auditenhance/plugins.php');
    }

    /**
     * Record a problem in a plugin.
     *
     * @param run_context $context
     * @param scanned_plugin $plugin
     * @param string $issue Issue code; its description is the string issue_<code>.
     * @param string $severity
     * @param string $file File relative to the plugin root, '' if none.
     * @param int $line Line in the file, 0 if none.
     * @param string|null $extra Extra text added to the label, for example a table or function name.
     */
    protected function report(
        run_context $context,
        scanned_plugin $plugin,
        string $issue,
        string $severity,
        string $file = '',
        int $line = 0,
        ?string $extra = null
    ): void {
        $description = $this->optional_string('issue_' . $issue) ?: $issue;
        $label = $plugin->get_label() . ': ' . $description . ($extra !== null && $extra !== '' ? ' (' . $extra . ')' : '');
        $context->add_item($this, new item(
            $issue,
            $severity,
            \context_system::instance()->id,
            0,
            $plugin->get_component(),
            0,
            $file,
            0,
            [
                'label' => $label,
                'url' => (new \moodle_url(
                    '/local/auditenhance/plugins.php',
                    ['component' => $plugin->get_component()]
                ))->out(false),
                'file' => $file,
                'line' => $line,
                'extra' => $extra,
            ]
        ));
    }
}
