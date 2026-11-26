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
 * Multi-factor authentication protects accounts.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mfa extends base {
    /** @var string[] Factors that are a real second factor. */
    public const STRONG_FACTORS = ['totp', 'webauthn', 'email', 'sms'];

    /**
     * Severity when failing.
     *
     * @return string
     */
    public function get_default_severity(): string {
        return severity::HIGH;
    }

    /**
     * Link to the MFA settings.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/settings.php', ['section' => 'managemfa']);
    }

    /**
     * Strong factors that are enabled.
     *
     * @return string[]
     */
    public static function enabled_factors(): array {
        if (!class_exists('\tool_mfa\plugininfo\factor')) {
            return [];
        }
        $names = [];
        foreach (\tool_mfa\plugininfo\factor::get_enabled_factors() as $factor) {
            if (in_array($factor->name, self::STRONG_FACTORS, true)) {
                $names[] = $factor->name;
            }
        }
        return $names;
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        if (!class_exists('\tool_mfa\manager')) {
            return finding::skip($this->summary('notinstalled'));
        }
        if (!get_config('tool_mfa', 'enabled')) {
            return finding::fail(severity::HIGH, $this->summary('off'))->with_link($this->get_fix_url());
        }
        $factors = self::enabled_factors();
        if (!$factors) {
            return finding::fail(severity::MEDIUM, $this->summary('nofactor'))->with_link($this->get_fix_url());
        }
        return finding::pass($this->summary('ok', implode(', ', $factors)));
    }
}
