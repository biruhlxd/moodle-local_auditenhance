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
 * Mute one item, or all items of a plugin for a check, with a reason; or remove an item mute.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login(null, false);

use local_auditenhance\local\item_mutes;
use local_auditenhance\local\items;
use local_auditenhance\local\registry;
use local_auditenhance\output\page;

$itemid = optional_param('item', 0, PARAM_INT);
$whole = optional_param('whole', 0, PARAM_BOOL);
$unmute = optional_param('unmute', 0, PARAM_INT);
$returnurl = new moodle_url(optional_param('returnurl', '/local/auditenhance/mutes.php', PARAM_LOCALURL));
$url = new moodle_url('/local/auditenhance/muteitem.php', ['item' => $itemid, 'whole' => (int)$whole,
    'returnurl' => $returnurl->out_as_local_url(false)]);
$title = get_string('muteitem', 'local_auditenhance');
page::setup('mutes', $url, $title, 'local/auditenhance:run');

if ($unmute) {
    require_sesskey();
    item_mutes::unmute($unmute);
    redirect($returnurl, get_string('itemunmuted', 'local_auditenhance'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$item = $DB->get_record('local_auditenhance_item', ['id' => $itemid], '*', MUST_EXIST);
$check = registry::instance()->get_check($item->checkname);
$detail = items::detail($item);
$what = $whole ? get_string('muteitemwhole', 'local_auditenhance', (object)['component' => $item->component,
    'check' => $check ? $check->get_name() : $item->checkname]) : (string)($detail['label'] ?? $item->issue);

$form = new \local_auditenhance\form\mute_form($url, ['checkname' => $what,
    'label' => get_string('muteditem', 'local_auditenhance')]);
if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    item_mutes::mute($item, (bool)$whole, $data->reason);
    redirect($returnurl, get_string('itemmuted', 'local_auditenhance'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo page::start('mutes', $title);
echo $OUTPUT->notification(get_string('muteitemnextrun', 'local_auditenhance'), 'info');
$form->display();
echo page::end();
