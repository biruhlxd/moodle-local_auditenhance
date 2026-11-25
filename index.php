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
 * Audit overview: overall score, area scores and the top problems.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login(null, false);

use local_auditenhance\local\run_manager;
use local_auditenhance\output\page;

$url = new moodle_url('/local/auditenhance/index.php');
$title = get_string('overview', 'local_auditenhance');
$output = page::setup('overview', $url, $title);

if (optional_param('run', 0, PARAM_BOOL) && confirm_sesskey()) {
    require_capability('local/auditenhance:run', context_system::instance());
    run_manager::queue(null, 'web');
    redirect($url, get_string('auditqueued', 'local_auditenhance'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$active = run_manager::get_active_run();
if ($active) {
    // Reload the page while an audit runs so the progress bar moves.
    $PAGE->requires->js_init_code('setTimeout(function() { window.location.reload(); }, 15000);');
}

echo page::start('overview', $title, [], $active ? (int)$active->id : null);
echo $output->render(new \local_auditenhance\output\overview_page());
echo page::end();
