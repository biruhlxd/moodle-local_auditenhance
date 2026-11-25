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

namespace local_auditenhance\check\accessibility;

use local_auditenhance\check\base;
use local_auditenhance\local\finding;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * An accessibility statement or accessibility tool is available.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class statement extends base {
    /** @var string Pattern that marks accessibility information. */
    public const PATTERN = '/accessib|barrierefrei|accesibilidad|accessibilit/i';

    /**
     * Link to this plugin's settings.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/settings.php', ['section' => 'local_auditenhance_settings']);
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        if (trim((string)get_config('local_auditenhance', 'accessibilitystatementurl')) !== '') {
            return finding::pass($this->str('configured'));
        }
        if ($this->find_statement()) {
            return finding::pass($this->str('found'));
        }
        return finding::fail(severity::LOW, $this->str('fail'))->with_link($this->get_fix_url());
    }

    /**
     * Look for an accessibility statement or tool on the site.
     *
     * @return bool
     */
    public function find_statement(): bool {
        global $CFG, $DB;
        $texts = [(string)($CFG->custommenuitems ?? ''), (string)($CFG->customusermenuitems ?? '')];
        foreach ((array)get_config('theme_' . $CFG->theme) as $value) {
            if (is_string($value)) {
                $texts[] = $value;
            }
        }
        foreach ($texts as $text) {
            if (preg_match(self::PATTERN, $text)) {
                return true;
            }
        }
        $like = $DB->sql_like('blockname', ':blockname', false);
        if ($DB->record_exists_select('block_instances', $like, ['blockname' => '%accessib%'])) {
            return true;
        }
        if ($DB->get_manager()->table_exists('page')) {
            $like = $DB->sql_like('name', ':name', false);
            if ($DB->record_exists_select('page', "course = :siteid AND $like", ['siteid' => SITEID, 'name' => '%accessib%'])) {
                return true;
            }
        }
        return false;
    }
}
