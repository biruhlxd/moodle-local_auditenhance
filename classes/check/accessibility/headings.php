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
 * Headings skipped or out of order, and text styled to look like a heading.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class headings extends content_check_base {
    /**
     * Brickfield checks for headings.
     *
     * @return string[]
     */
    public function get_brickfield_checks(): array {
        return ['header_h3', 'headers_have_text'];
    }

    /**
     * Analyse content.
     *
     * @param run_context $context
     * @param content_item $item
     * @param \DOMXPath $xpath
     */
    public function analyse(run_context $context, content_item $item, \DOMXPath $xpath): void {
        $last = 0;
        foreach ($this->query($xpath, '//h1|//h2|//h3|//h4|//h5|//h6') as $heading) {
            $level = (int)substr($heading->nodeName, 1);
            if (html::text($heading) === '' && !$this->query($xpath, './/img[@alt!=""]', $heading)) {
                $this->report($context, $item, 'emptyheading', severity::LOW, $heading);
            }
            if ($last && $level > $last + 1) {
                $this->report($context, $item, 'skippedheading', severity::LOW, $heading);
            }
            $last = $level;
        }
        foreach ($this->query($xpath, '//p') as $paragraph) {
            if ($this->looks_like_heading($paragraph)) {
                $this->report($context, $item, 'fakeheading', severity::LOW, $paragraph);
            }
        }
    }

    /**
     * Is this paragraph short text made to look like a heading?
     *
     * @param \DOMElement $paragraph
     * @return bool
     */
    public function looks_like_heading(\DOMElement $paragraph): bool {
        $text = html::text($paragraph);
        if ($text === '' || \core_text::strlen($text) > 80 || preg_match('/[.,;:!?]$/u', $text)) {
            return false;
        }
        $children = [];
        foreach ($paragraph->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $children[] = $child;
            } else if (trim($child->textContent) !== '') {
                $children = [];
                break;
            }
        }
        if (
            count($children) === 1 && in_array($children[0]->nodeName, ['strong', 'b'], true)
                && html::text($children[0]) === $text
        ) {
            return true;
        }
        foreach (array_merge([$paragraph], $children) as $element) {
            $size = html::style_value($element->getAttribute('style'), 'font-size');
            if ($size !== null && $this->is_large($size)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is a CSS font size large enough to look like a heading?
     *
     * @param string $size
     * @return bool
     */
    protected function is_large(string $size): bool {
        if (in_array($size, ['large', 'x-large', 'xx-large', 'xxx-large', 'larger'], true)) {
            return true;
        }
        if (!preg_match('/^([\d.]+)\s*(px|pt|em|rem|%)$/', $size, $m)) {
            return false;
        }
        $value = (float)$m[1];
        return match ($m[2]) {
            'px' => $value >= 18,
            'pt' => $value >= 14,
            '%' => $value >= 130,
            default => $value >= 1.3,
        };
    }
}
