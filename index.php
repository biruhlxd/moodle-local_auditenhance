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
 * Audit overview.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_auditenhance\local\run_manager;

admin_externalpage_setup('local_auditenhance_overview');
$context = context_system::instance();

if (optional_param('run', 0, PARAM_BOOL) && confirm_sesskey()) {
    require_capability('local/auditenhance:run', $context);
    run_manager::queue(null, 'web');
    redirect($PAGE->url);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('overview', 'local_auditenhance'));

$latest = run_manager::get_latest_complete_run();
if ($latest) {
    echo html_writer::tag('p', get_string('overallscore', 'local_auditenhance') . ': ' .
        ($latest->score === null ? '-' : (int)$latest->score));
}
if (has_capability('local/auditenhance:run', $context)) {
    echo $OUTPUT->single_button(new moodle_url($PAGE->url, ['run' => 1]), get_string('runaudit', 'local_auditenhance'));
}
echo $OUTPUT->footer();
