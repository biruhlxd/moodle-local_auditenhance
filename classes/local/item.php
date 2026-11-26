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

namespace local_auditenhance\local;

/**
 * One content or code item behind a finding, for example an image without alt text.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item {
    /**
     * Constructor.
     *
     * @param string $issue Short issue code, for example 'missingalt'.
     * @param string $severity Severity of this item.
     * @param int $contextid Context of the content, 0 if none.
     * @param int $courseid Course of the content, 0 if none.
     * @param string $component Component owning the content or the scanned plugin.
     * @param int $itemid Id of the record holding the content.
     * @param string $field Field or file the issue is in.
     * @param int $userid User who owns the content, 0 if unknown.
     * @param array $detail Extra data: 'label', 'url', 'snippet', 'file', 'line'.
     */
    public function __construct(
        /** @var string Issue code. */
        public string $issue,
        /** @var string Severity. */
        public string $severity = severity::LOW,
        /** @var int Context id. */
        public int $contextid = 0,
        /** @var int Course id. */
        public int $courseid = 0,
        /** @var string Component. */
        public string $component = '',
        /** @var int Item id. */
        public int $itemid = 0,
        /** @var string Field. */
        public string $field = '',
        /** @var int User id. */
        public int $userid = 0,
        /** @var array Details. */
        public array $detail = [],
    ) {
        if (!severity::is_valid($this->severity)) {
            $this->severity = severity::LOW;
        }
    }

    /**
     * Key that identifies the same item in later runs, used to mute it.
     *
     * Built from where the problem is, not from values that change between runs (such as line numbers).
     *
     * @param string $checkref
     * @return string
     */
    public function get_key(string $checkref): string {
        return sha1(implode('|', [$checkref, $this->component, $this->issue, $this->contextid, $this->itemid, $this->field,
            $this->detail['file'] ?? '', $this->detail['extra'] ?? '', $this->detail['snippet'] ?? '']));
    }

    /**
     * Record for local_auditenhance_item.
     *
     * @param int $runid
     * @param string $checkname
     * @param string $area
     * @return \stdClass
     */
    public function to_record(int $runid, string $checkname, string $area): \stdClass {
        $detail = $this->detail;
        if (isset($detail['snippet'])) {
            $detail['snippet'] = \core_text::substr((string)$detail['snippet'], 0, 300);
        }
        return (object)[
            'runid' => $runid,
            'checkname' => $checkname,
            'area' => $area,
            'contextid' => $this->contextid,
            'courseid' => $this->courseid,
            'component' => \core_text::substr($this->component, 0, 100),
            'itemid' => $this->itemid,
            'field' => \core_text::substr($this->field, 0, 100),
            'issue' => \core_text::substr($this->issue, 0, 255),
            'severity' => $this->severity,
            'userid' => $this->userid,
            'detail' => json_encode($detail),
            'itemkey' => $this->get_key($checkname),
            'timecreated' => time(),
        ];
    }
}
