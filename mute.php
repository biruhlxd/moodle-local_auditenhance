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
 * Mute a check with a reason, or unmute it.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login(null, false);

use local_auditenhance\local\mutes;
use local_auditenhance\local\registry;
use local_auditenhance\output\page;

$checkref = required_param('check', PARAM_ALPHANUMEXT);
$unmute = optional_param('unmute', 0, PARAM_BOOL);
$url = new moodle_url('/local/auditenhance/mute.php', ['check' => $checkref]);
$title = get_string('mute', 'local_auditenhance');
$output = page::setup('mutes', $url, $title, 'local/auditenhance:run');
$returnurl = new moodle_url('/local/auditenhance/mutes.php');

$check = registry::instance()->get_check($checkref);
if (!$check) {
    throw new moodle_exception('unknowncheck', 'local_auditenhance', '', s($checkref));
}
$areaurl = registry::instance()->get_area($check->get_area())->get_url();

if ($unmute) {
    require_sesskey();
    mutes::unmute($checkref);
    redirect(
        $areaurl,
        get_string('unmuted', 'local_auditenhance', $check->get_name()),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$form = new \local_auditenhance\form\mute_form($url, ['checkname' => $check->get_name()]);
if ($form->is_cancelled()) {
    redirect($areaurl);
} else if ($data = $form->get_data()) {
    mutes::mute($checkref, $data->reason);
    redirect(
        $areaurl,
        get_string('mutedcheck', 'local_auditenhance', $check->get_name()),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo page::start('mutes', $title);
$form->display();
echo page::end();
