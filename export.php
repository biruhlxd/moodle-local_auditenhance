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
 * Download audit data in an export format added by a plugin.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_auditenhance\local\registry;
use local_auditenhance\local\run_manager;

$formatid = required_param('format', PARAM_ALPHANUMEXT);
$scope = required_param('scope', PARAM_TEXT);
$runid = required_param('runid', PARAM_INT);

require_login(null, false);
require_sesskey();
$context = context_system::instance();
require_capability('local/auditenhance:view', $context);

$format = registry::instance()->get_export_format($formatid);
if (!$format || !$format->supports($scope)) {
    throw new moodle_exception('unknownexport', 'local_auditenhance', '', s($formatid));
}
require_capability($format->get_capability(), $context);
if (!run_manager::get_run($runid)) {
    throw new moodle_exception('invalidrecord', 'error', '', 'local_auditenhance_run');
}
$format->export($runid, $scope);
