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

use local_auditenhance\check\base;
use local_auditenhance\local\finding;
use local_auditenhance\local\item;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Plugins where risks combine: personal data, no working privacy provider and a capability open to everyone.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class correlation extends base {
    /** @var string[] Checks that show personal data without a working privacy provider. */
    public const PRIVACY_CHECKS = ['local_auditenhance_privacymissing', 'local_auditenhance_privacynull'];

    /** @var string[] Checks that show capabilities open to everyone. */
    public const OPEN_CHECKS = ['local_auditenhance_riskycapsdefault', 'local_auditenhance_riskyoverrides'];

    /** @var string[] Checks that show unsafe code. */
    public const CODE_CHECKS = ['local_auditenhance_unsafefunctions', 'local_auditenhance_sqlconcat',
        'local_auditenhance_rawinput'];

    /**
     * Runs from stored items of other checks.
     *
     * @return bool
     */
    public function is_quick(): bool {
        return false;
    }

    /**
     * Evaluated after the other checks.
     *
     * @return int
     */
    public function get_evaluation_order(): int {
        return 100;
    }

    /**
     * Severity when failing.
     *
     * @return string
     */
    public function get_default_severity(): string {
        return severity::CRITICAL;
    }

    /**
     * Components with items of some checks.
     *
     * @param run_context $context
     * @param string[] $checkrefs
     * @return array<string, int> Component => number of items.
     */
    protected function components(run_context $context, array $checkrefs): array {
        $result = [];
        foreach ($checkrefs as $ref) {
            foreach ($context->get_items($ref) as $item) {
                if ($item->component !== '') {
                    $result[$item->component] = ($result[$item->component] ?? 0) + 1;
                }
            }
        }
        return $result;
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        if ($context->is_live()) {
            return finding::skip(get_string('notscannedyet', 'local_auditenhance'));
        }
        $privacy = $this->components($context, self::PRIVACY_CHECKS);
        $open = $this->components($context, self::OPEN_CHECKS);
        $code = $this->components($context, self::CODE_CHECKS);
        $manager = \core_plugin_manager::instance();
        foreach (array_keys($privacy) as $component) {
            $risks = [];
            if (isset($open[$component])) {
                $risks[] = get_string('correlation_open', 'local_auditenhance');
            }
            if (isset($code[$component])) {
                $risks[] = get_string('correlation_code', 'local_auditenhance');
            }
            if (!$risks) {
                continue;
            }
            $info = $manager->get_plugin_info($component);
            $name = $info ? $info->displayname . ' (' . $component . ')' : $component;
            $severity = count($risks) > 1 || isset($open[$component]) ? severity::CRITICAL : severity::HIGH;
            $context->add_item($this, new item('combined', $severity, \context_system::instance()->id, 0, $component, 0, '', 0, [
                'label' => $name . ': ' . get_string('correlation_personal', 'local_auditenhance') . ' + ' .
                    implode(' + ', $risks),
                'url' => (new \moodle_url('/admin/plugins.php', ['contribonly' => 1]))->out(false),
            ]));
        }
        return $this->finding_from_items($context);
    }
}
