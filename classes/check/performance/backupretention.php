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
use local_auditenhance\local\settings;
use local_auditenhance\local\severity;

/**
 * Automated backups are kept forever.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backupretention extends base {
    /**
     * Link to the automated backup settings.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/settings.php', ['section' => 'automated']);
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        global $DB;
        $active = (int)get_config('backup', 'backup_auto_active');
        $maxkept = (int)get_config('backup', 'backup_auto_max_kept');
        $deletedays = (int)get_config('backup', 'backup_auto_delete_days');
        $days = settings::threshold('backupagedays');
        $old = $DB->get_record_sql("SELECT COUNT(1) AS files, SUM(filesize) AS bytes
                                      FROM {files}
                                     WHERE component = 'backup' AND filearea = 'automated' AND filename <> '.'
                                           AND timecreated < :cutoff", ['cutoff' => time() - $days * DAYSECS]);
        $oldcount = (int)($old->files ?? 0);
        if ($active && $maxkept === 0 && $deletedays === 0) {
            return finding::fail(severity::MEDIUM, $this->summary('unlimited'), [], $oldcount)->with_link($this->get_fix_url());
        }
        if ($oldcount) {
            $a = (object)['count' => $oldcount, 'days' => $days, 'size' => display_size((int)$old->bytes)];
            return finding::fail(severity::LOW, $this->summary('old', $a), [], $oldcount)->with_link($this->get_fix_url());
        }
        return finding::pass($this->summary($active ? 'ok' : 'inactive'));
    }
}
