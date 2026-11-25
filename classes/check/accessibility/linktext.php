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
use local_auditenhance\local\content\html;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Links whose text does not say where they go.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class linktext extends content_check_base {
    /** @var string[] Link texts that say nothing about the target. */
    public const VAGUE = ['click here', 'here', 'click', 'read more', 'more', 'link', 'this link', 'this', 'more info',
        'more information', 'learn more', 'continue', 'go', 'details', 'click this link', 'see more'];

    /**
     * Brickfield checks for links.
     *
     * @return string[]
     */
    public function get_brickfield_checks(): array {
        return ['a_suspicious_link_text', 'a_must_contain_text'];
    }

    /**
     * Analyse content.
     *
     * @param run_context $context
     * @param content_item $item
     * @param \DOMXPath $xpath
     */
    public function analyse(run_context $context, content_item $item, \DOMXPath $xpath): void {
        foreach ($this->query($xpath, '//a[@href]') as $link) {
            $text = html::text($link);
            if ($text === '') {
                foreach ($this->query($xpath, './/img[@alt]', $link) as $img) {
                    $text .= trim($img->getAttribute('alt'));
                }
            }
            if ($text === '') {
                $text = trim($link->getAttribute('aria-label') . $link->getAttribute('title'));
            }
            if ($text === '') {
                $this->report($context, $item, 'emptylink', severity::MEDIUM, $link);
                continue;
            }
            $normalised = trim(\core_text::strtolower($text), " \t.:!>»→");
            if (in_array($normalised, self::VAGUE, true)) {
                $this->report($context, $item, 'vaguelink', severity::LOW, $link);
            } else if (preg_match('#^(https?://|www\.)\S+$#i', $text)) {
                $this->report($context, $item, 'urllink', severity::LOW, $link);
            }
        }
    }
}
