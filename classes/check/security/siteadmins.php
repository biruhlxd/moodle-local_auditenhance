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
use local_auditenhance\local\settings;
use local_auditenhance\local\severity;

/**
 * Number of site administrators.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class siteadmins extends base {
    /**
     * Link to the site administrators page.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        return new \moodle_url('/admin/roles/admins.php');
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        $admins = get_admins();
        $max = settings::threshold('maxadmins');
        $a = (object)['count' => count($admins), 'max' => $max];
        if (count($admins) <= $max) {
            return finding::pass($this->summary('ok', $a));
        }
        $sample = [];
        foreach ($admins as $admin) {
            $sample[] = ['label' => fullname($admin), 'url' => new \moodle_url('/user/profile.php', ['id' => $admin->id])];
        }
        return finding::fail(severity::MEDIUM, $this->summary('fail', $a), [], count($admins))
            ->with_sample($sample)
            ->with_link($this->get_fix_url());
    }
}
