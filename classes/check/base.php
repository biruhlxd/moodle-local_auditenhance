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

namespace local_auditenhance\check;

use core\check\result;
use local_auditenhance\local\finding;
use local_auditenhance\local\results;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Base class of every audit check.
 *
 * A check lives in classes/check/<area>/<name>.php of its plugin. The area is taken from the
 * namespace by default, the component from the first part of the class name and the strings
 * from that component's language file:
 *  - check_<id>: name
 *  - check_<id>_why: why it matters
 *  - check_<id>_fix: how to fix it
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends \core\check\check {
    /**
     * Run the check.
     *
     * @param run_context $context
     * @return finding
     */
    abstract public function evaluate(run_context $context): finding;

    /**
     * The component is always the plugin the class belongs to, even when the core check
     * manager sets the component of the plugin that returned the check.
     *
     * @return string
     */
    public function get_component(): string {
        $parts = explode('\\', static::class);
        return $parts[0];
    }

    /**
     * Area id of this check, from the namespace by default.
     *
     * @return string
     */
    public function get_area(): string {
        $parts = explode('\\', static::class);
        $index = array_search('check', $parts, true);
        if ($index !== false && isset($parts[$index + 1]) && $index + 2 < count($parts)) {
            return $parts[$index + 1];
        }
        throw new \coding_exception('Check ' . static::class . ' must be in classes/check/<area>/ or override get_area()');
    }

    /**
     * Severity used when the check fails, for documentation and as a default.
     *
     * @return string
     */
    public function get_default_severity(): string {
        return severity::MEDIUM;
    }

    /**
     * Can this check run on page load? Heavy checks return false and only run in the audit task.
     *
     * @return bool
     */
    public function is_quick(): bool {
        return true;
    }

    /**
     * Order in which checks are evaluated in a run. Checks that read other findings use a higher value.
     *
     * @return int
     */
    public function get_evaluation_order(): int {
        return 0;
    }

    /**
     * Core report this check also appears in ('security', 'performance', 'status'), or null for none.
     * Null by default, which means the area's report is used.
     *
     * @return string|null
     */
    public function get_core_report(): ?string {
        return null;
    }

    /**
     * Name of the check.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('check_' . $this->get_id(), $this->get_component());
    }

    /**
     * Why this check matters.
     *
     * @return string
     */
    public function get_explanation(): string {
        return $this->optional_string('check_' . $this->get_id() . '_why');
    }

    /**
     * How to fix a failure.
     *
     * @return string
     */
    public function get_fix(): string {
        return $this->optional_string('check_' . $this->get_id() . '_fix');
    }

    /**
     * Link to the setting or page where the problem is fixed.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return null;
    }

    /**
     * Link for the core check reports.
     *
     * @return \action_link|null
     */
    public function get_action_link(): ?\action_link {
        $url = $this->get_fix_url();
        if (!$url) {
            return null;
        }
        return new \action_link($url, get_string('fixlink', 'local_auditenhance'));
    }

    /**
     * Result for the core check API reports.
     *
     * Quick checks run now; heavy checks show the result of the last audit.
     *
     * @return result
     */
    public function get_result(): result {
        if ($this->is_quick()) {
            try {
                $finding = $this->evaluate(run_context::live());
            } catch (\Throwable $e) {
                $finding = finding::error($e->getMessage());
            }
        } else {
            $finding = results::get_latest_finding($this->get_ref())
                ?? finding::skip(get_string('notscannedyet', 'local_auditenhance'));
        }
        return new result(self::core_status($finding), $finding->summary, $this->get_core_details());
    }

    /**
     * Details shown in the core reports.
     *
     * @return string
     */
    protected function get_core_details(): string {
        $parts = [];
        if ($why = $this->get_explanation()) {
            $parts[] = \html_writer::tag('p', $why);
        }
        if ($fix = $this->get_fix()) {
            $parts[] = \html_writer::tag('p', $fix);
        }
        return implode('', $parts);
    }

    /**
     * Map a finding to a core check status.
     *
     * @param finding $finding
     * @return string
     */
    public static function core_status(finding $finding): string {
        return match ($finding->status) {
            finding::STATUS_PASS => result::OK,
            finding::STATUS_SKIP => result::NA,
            finding::STATUS_ERROR => result::UNKNOWN,
            default => severity::to_core_status($finding->severity),
        };
    }

    /**
     * A string of this check's component, or '' when it does not exist.
     *
     * @param string $identifier
     * @param mixed $a
     * @return string
     */
    protected function optional_string(string $identifier, $a = null): string {
        $manager = get_string_manager();
        if ($manager->string_exists($identifier, $this->get_component())) {
            return get_string($identifier, $this->get_component(), $a);
        }
        return '';
    }

    /**
     * String of this check: check_<id>_<suffix>.
     *
     * @param string $suffix
     * @param mixed $a
     * @return string
     */
    protected function str(string $suffix, $a = null): string {
        return get_string('check_' . $this->get_id() . '_' . $suffix, $this->get_component(), $a);
    }

    /**
     * Summary of this check (string check_<id>_<suffix>), kept as a reference so it is translated when shown.
     *
     * @param string $suffix
     * @param mixed $a
     * @return \local_auditenhance\local\summary
     */
    protected function summary(string $suffix, $a = null): \local_auditenhance\local\summary {
        return new \local_auditenhance\local\summary('check_' . $this->get_id() . '_' . $suffix, $this->get_component(), $a);
    }

    /**
     * Finding built from the items recorded for this check.
     *
     * Passes when there are no items, fails with the highest item severity otherwise.
     *
     * @param run_context $context
     * @param int $samplesize
     * @return finding
     */
    protected function finding_from_items(run_context $context, int $samplesize = 5): finding {
        $ref = $this->get_ref();
        $count = $context->count_items($ref);
        if ($count === 0) {
            return finding::pass($this->summary('ok'));
        }
        $severity = $context->get_max_item_severity($ref) ?? $this->get_default_severity();
        $sample = [];
        foreach ($context->get_items($ref, $samplesize) as $record) {
            $detail = json_decode($record->detail ?? '', true) ?: [];
            $sample[] = ['label' => $detail['label'] ?? $record->issue, 'url' => $detail['url'] ?? null];
        }
        return finding::fail($severity, $this->summary('fail', $count), [], $count)
            ->with_sample($sample)
            ->with_link($this->get_fix_url());
    }
}
