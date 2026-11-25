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
 * Area page: findings of one area grouped by severity.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login(null, false);

use local_auditenhance\local\registry;
use local_auditenhance\local\run_manager;
use local_auditenhance\output\page;

$areaid = required_param('area', PARAM_ALPHANUM);
$area = registry::instance()->get_area($areaid);
if (!$area) {
    throw new moodle_exception('unknownarea', 'local_auditenhance', '', s($areaid));
}
$url = $area->get_url();
$output = page::setup('area-' . $areaid, $url, $area->get_name());

if (optional_param('run', 0, PARAM_BOOL) && confirm_sesskey()) {
    require_capability('local/auditenhance:run', context_system::instance());
    run_manager::queue([$areaid], 'web');
    redirect($url, get_string('auditqueued', 'local_auditenhance'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo page::start('area-' . $areaid, $area->get_name(), ['area' => $areaid]);
echo $output->render(new \local_auditenhance\output\area_page($area));
echo page::end();
