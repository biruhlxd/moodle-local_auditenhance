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
use local_auditenhance\local\site_info;

/**
 * Caching (MUC) uses only the default stores.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mucdefaults extends base {
    /** @var string[] Stores that exist on every site. */
    public const DEFAULT_STORES = ['default_application', 'default_session', 'default_request'];

    /**
     * Link to the cache administration.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/cache/admin.php');
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        $stores = array_keys(\core_cache\config::instance()->get_all_stores());
        $custom = array_diff($stores, self::DEFAULT_STORES);
        if ($custom) {
            return finding::pass($this->summary('ok', count($custom)));
        }
        $severity = site_info::is_large() ? severity::MEDIUM : severity::LOW;
        return finding::fail($severity, $this->summary('fail'))->with_link($this->get_fix_url());
    }
}
