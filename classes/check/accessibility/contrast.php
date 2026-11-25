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
 * Low contrast text from inline colour styles.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contrast extends content_check_base {
    /** @var float WCAG AA contrast for normal text. */
    public const MIN_RATIO = 4.5;

    /** @var float Contrast below this is reported as medium. */
    public const POOR_RATIO = 3.0;

    /**
     * Brickfield checks for contrast.
     *
     * @return string[]
     */
    public function get_brickfield_checks(): array {
        return ['css_text_has_contrast'];
    }

    /**
     * Analyse content.
     *
     * @param run_context $context
     * @param content_item $item
     * @param \DOMXPath $xpath
     */
    public function analyse(run_context $context, content_item $item, \DOMXPath $xpath): void {
        foreach ($this->query($xpath, '//*[@style]') as $element) {
            $colour = html::style_value($element->getAttribute('style'), 'color');
            $foreground = $colour === null ? null : html::parse_colour($colour);
            if ($foreground === null || html::text($element) === '') {
                continue;
            }
            $ratio = html::contrast($foreground, $this->background($element));
            if ($ratio < self::POOR_RATIO) {
                $this->report($context, $item, 'lowcontrast', severity::MEDIUM, $element);
            } else if ($ratio < self::MIN_RATIO) {
                $this->report($context, $item, 'lowcontrast', severity::LOW, $element);
            }
        }
    }

    /**
     * Background colour of an element from its own or its ancestors' inline styles, white by default.
     *
     * @param \DOMElement $element
     * @return int[]
     */
    public function background(\DOMElement $element): array {
        for ($node = $element; $node instanceof \DOMElement; $node = $node->parentNode) {
            $style = $node->getAttribute('style');
            foreach (['background-color', 'background'] as $property) {
                $value = html::style_value($style, $property);
                if ($value === null) {
                    continue;
                }
                foreach (preg_split('/\s+(?![^(]*\))/', $value) as $token) {
                    if ($rgb = html::parse_colour($token)) {
                        return $rgb;
                    }
                }
            }
        }
        return [255, 255, 255];
    }
}
