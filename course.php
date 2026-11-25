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
 * Accessibility results of one course, for its editing teachers.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_auditenhance\local\items;
use local_auditenhance\local\registry;

$courseid = required_param('id', PARAM_INT);
$areaid = 'accessibility';

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/auditenhance:viewcourse', $context);

$url = new moodle_url('/local/auditenhance/course.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('courseaccessibility', 'local_auditenhance'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

$registry = registry::instance();
$area = $registry->get_area($areaid);
if (!$area) {
    throw new moodle_exception('unknownarea', 'local_auditenhance', '', $areaid);
}
$runid = items::get_latest_run_for_area($areaid);

$hook = new \local_auditenhance\hook\extend_screen('teacher', ['courseid' => $course->id, 'area' => $areaid], $runid);
\core\di::get(\core\hook\manager::class)->dispatch($hook);

$output = $PAGE->get_renderer('local_auditenhance');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('courseaccessibility', 'local_auditenhance'));
echo $hook->get_top();
echo $output->render(new \local_auditenhance\output\course_page($course, $area, $runid, 5));
echo $hook->get_bottom();
echo $OUTPUT->footer();
