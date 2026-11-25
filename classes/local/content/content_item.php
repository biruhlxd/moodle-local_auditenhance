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

namespace local_auditenhance\local\content;

/**
 * A piece of HTML content read by the content scanner.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_item {
    /**
     * Constructor.
     *
     * @param string $html The HTML.
     * @param int $contextid Context of the content.
     * @param int $courseid Course of the content, 0 if none.
     * @param string $component Component owning the content, for example mod_page.
     * @param int $itemid Id of the record holding the content.
     * @param string $field Field holding the content.
     * @param string $label Human readable name of the content, for example "Page: Week 1".
     * @param string|null $url Where the content can be seen or edited.
     * @param int $userid User who owns the content, 0 if unknown.
     */
    public function __construct(
        /** @var string HTML. */
        public string $html,
        /** @var int Context id. */
        public int $contextid,
        /** @var int Course id. */
        public int $courseid,
        /** @var string Component. */
        public string $component,
        /** @var int Item id. */
        public int $itemid,
        /** @var string Field. */
        public string $field,
        /** @var string Label. */
        public string $label,
        /** @var string|null URL. */
        public ?string $url = null,
        /** @var int User id. */
        public int $userid = 0,
    ) {
    }
}
