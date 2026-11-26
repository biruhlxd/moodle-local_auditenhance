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

use local_auditenhance\check\base;
use local_auditenhance\local\content\html;
use local_auditenhance\local\finding;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Contrast of the theme brand colour.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class themecontrast extends base {
    /** @var int[] Dark text colour used by Boost. */
    public const DARK_TEXT = [0x1d, 0x21, 0x25];

    /**
     * Link to the theme settings.
     *
     * @return \moodle_url|null
     */
    public function get_fix_url(): ?\moodle_url {
        global $CFG;
        return new \moodle_url('/admin/settings.php', ['section' => 'themesetting' . $CFG->theme]);
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        global $CFG;
        $brand = trim((string)get_config('theme_' . $CFG->theme, 'brandcolor'));
        if ($brand === '') {
            return finding::pass($this->summary('default'));
        }
        $rgb = html::parse_colour($brand);
        if ($rgb === null) {
            return finding::skip($this->summary('unknown', s($brand)));
        }
        $a = (object)[
            'colour' => $brand,
            'white' => format_float(html::contrast($rgb, [255, 255, 255]), 1),
            'dark' => format_float(html::contrast($rgb, self::DARK_TEXT), 1),
        ];
        if (html::contrast($rgb, [255, 255, 255]) >= 4.5) {
            return finding::pass($this->summary('ok', $a));
        }
        return finding::fail(severity::MEDIUM, $this->summary('fail', $a))->with_link($this->get_fix_url());
    }
}
