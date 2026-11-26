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

namespace local_auditenhance\check\accessibility;

use local_auditenhance\local\content\content_check_base;
use local_auditenhance\local\content\content_item;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Embedded frames without a title.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class iframetitles extends content_check_base {
    /**
     * Analyse content.
     *
     * @param run_context $context
     * @param content_item $item
     * @param \DOMXPath $xpath
     */
    public function analyse(run_context $context, content_item $item, \DOMXPath $xpath): void {
        foreach ($this->query($xpath, '//iframe') as $frame) {
            $title = trim($frame->getAttribute('title') . $frame->getAttribute('aria-label'));
            if ($title === '' && $frame->getAttribute('aria-hidden') !== 'true') {
                $this->report($context, $item, 'notitle', severity::MEDIUM, $frame);
            }
        }
    }
}
