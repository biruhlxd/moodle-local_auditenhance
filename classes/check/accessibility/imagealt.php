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
 * Images without alternative text or with a file name as alternative text.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class imagealt extends content_check_base {
    /** @var string[] Alternative texts that say nothing. */
    public const PLACEHOLDERS = ['image', 'picture', 'photo', 'img', 'graphic', 'alt', 'spacer', 'icon', 'logo', 'untitled'];

    /**
     * Brickfield checks for images.
     *
     * @return string[]
     */
    public function get_brickfield_checks(): array {
        return ['img_has_alt', 'img_alt_is_different', 'img_alt_not_place_holder', 'img_alt_not_empty_in_anchor',
            'area_has_alt_value'];
    }

    /**
     * Analyse content.
     *
     * @param run_context $context
     * @param content_item $item
     * @param \DOMXPath $xpath
     */
    public function analyse(run_context $context, content_item $item, \DOMXPath $xpath): void {
        foreach ($this->query($xpath, '//img') as $img) {
            if ($img->getAttribute('role') === 'presentation' || $img->getAttribute('aria-hidden') === 'true') {
                continue;
            }
            if (!$img->hasAttribute('alt')) {
                $this->report($context, $item, 'missingalt', severity::MEDIUM, $img);
                continue;
            }
            $alt = trim($img->getAttribute('alt'));
            if ($alt === '') {
                $link = $this->query($xpath, 'ancestor::a', $img);
                if ($link && trim($link[0]->textContent) === '') {
                    $this->report($context, $item, 'emptyaltlink', severity::MEDIUM, $img);
                }
                continue;
            }
            $file = strtolower(basename((string)parse_url($img->getAttribute('src'), PHP_URL_PATH)));
            $lower = strtolower($alt);
            if (
                preg_match('/\.(jpe?g|png|gif|svg|webp|bmp|tiff?)$/i', $alt) || ($file !== '' && $lower === $file)
                    || in_array($lower, self::PLACEHOLDERS, true)
            ) {
                $this->report($context, $item, 'filenamealt', severity::LOW, $img);
            }
        }
    }
}
