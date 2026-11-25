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

/**
 * Library callbacks for Audit & Enhance.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Audit checks shown in the core security report.
 *
 * @return \core\check\check[]
 */
function local_auditenhance_security_checks(): array {
    return \local_auditenhance\local\registry::instance()->get_core_report_checks('security');
}

/**
 * Audit checks shown in the core performance report.
 *
 * @return \core\check\check[]
 */
function local_auditenhance_performance_checks(): array {
    return \local_auditenhance\local\registry::instance()->get_core_report_checks('performance');
}

/**
 * Audit checks shown in the core system status report.
 *
 * @return \core\check\check[]
 */
function local_auditenhance_status_checks(): array {
    return \local_auditenhance\local\registry::instance()->get_core_report_checks('status');
}
