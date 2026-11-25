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

namespace local_auditenhance\check\performance;

use local_auditenhance\check\base;
use local_auditenhance\local\finding;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * PHP OPcache is enabled for the web server.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class opcache extends base {
    /**
     * Severity when failing.
     *
     * @return string
     */
    public function get_default_severity(): string {
        return severity::HIGH;
    }

    /**
     * Link to the PHP info page.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/phpinfo.php');
    }

    /**
     * Evaluate.
     *
     * The command line can have its own OPcache setting, so the result seen by the web server is
     * remembered and used when the check runs from the command line or a task.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        if (CLI_SCRIPT) {
            $known = get_config('local_auditenhance', 'webopcache');
            if ($known === false || $known === '') {
                return finding::skip($this->str('cli'));
            }
            $enabled = (bool)$known;
        } else {
            $enabled = self::is_enabled_here();
            if (get_config('local_auditenhance', 'webopcache') !== (string)(int)$enabled) {
                set_config('webopcache', (int)$enabled, 'local_auditenhance');
            }
        }
        if ($enabled) {
            return finding::pass($this->str('ok'));
        }
        return finding::fail(severity::HIGH, $this->str('fail'))->with_link($this->get_fix_url());
    }

    /**
     * Is OPcache enabled in this PHP process?
     *
     * @return bool
     */
    public static function is_enabled_here(): bool {
        return function_exists('opcache_get_status') && !empty(ini_get('opcache.enable'));
    }
}
