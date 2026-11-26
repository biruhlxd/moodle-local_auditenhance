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

namespace local_auditenhance\event;

/**
 * Event: an item of a check was muted.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_muted extends \core\event\base {
    /**
     * Init.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_auditenhance_itemmute';
    }

    /**
     * Name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_item_muted', 'local_auditenhance');
    }

    /**
     * Description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id {$this->userid} muted an item of the audit check " . s($this->other['checkname']) . ": " .
            s($this->other['label'] ?? '') . ".";
    }

    /**
     * URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/auditenhance/index.php');
    }

    /**
     * Object id mapping for backup and restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_auditenhance_itemmute', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Other mapping for backup and restore.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
