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
 * Self-registration is protected against spam accounts.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class selfregistration extends base {
    /**
     * Severity when failing.
     *
     * @return string
     */
    public function get_default_severity(): string {
        return severity::HIGH;
    }

    /**
     * Link to the authentication settings.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/settings.php', ['section' => 'manageauths']);
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        global $CFG;
        if (empty($CFG->registerauth) || !is_enabled_auth($CFG->registerauth)) {
            return finding::pass($this->summary('off'));
        }
        $problems = [];
        $captcha = !empty($CFG->recaptchapublickey) && !empty($CFG->recaptchaprivatekey)
            && !empty(get_config('auth_' . $CFG->registerauth, 'recaptcha'));
        if (!$captcha) {
            $problems[] = $this->str('nocaptcha');
        }
        if (trim((string)($CFG->allowemailaddresses ?? '')) === '') {
            $problems[] = $this->str('nodomains');
        }
        if (!$problems) {
            return finding::pass($this->summary('ok'));
        }
        $severity = count($problems) > 1 ? severity::HIGH : severity::LOW;
        return finding::fail($severity, $this->summary('fail') . ' ' . implode(' ', $problems))->with_link($this->get_fix_url());
    }
}
