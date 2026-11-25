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

namespace local_auditenhance\hook;

/**
 * Lets plugins add content to an audit screen.
 *
 * Dispatched while a screen is rendered. Screen ids: 'overview', 'area', 'course', 'plugins',
 * 'mutes', 'teacher' and any screen added by other plugins.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Add content to the top or bottom of an audit screen.')]
#[\core\attribute\tags('local_auditenhance', 'output')]
final class extend_screen {
    /** @var string[] HTML for the top of the screen. */
    private array $top = [];

    /** @var string[] HTML for the bottom of the screen. */
    private array $bottom = [];

    /**
     * Constructor.
     *
     * @param string $screenid Screen being shown.
     * @param array $params Screen parameters, for example ['area' => 'security'] or ['courseid' => 5].
     * @param int|null $runid Run shown on the screen, if any.
     */
    public function __construct(
        /** @var string Screen id. */
        public readonly string $screenid,
        /** @var array Parameters. */
        public readonly array $params = [],
        /** @var int|null Run id. */
        public readonly ?int $runid = null,
    ) {
    }

    /**
     * Add HTML at the top of the screen.
     *
     * @param string $html
     */
    public function add_top(string $html): void {
        $this->top[] = $html;
    }

    /**
     * Add HTML at the bottom of the screen.
     *
     * @param string $html
     */
    public function add_bottom(string $html): void {
        $this->bottom[] = $html;
    }

    /**
     * HTML for the top.
     *
     * @return string
     */
    public function get_top(): string {
        return implode('', $this->top);
    }

    /**
     * HTML for the bottom.
     *
     * @return string
     */
    public function get_bottom(): string {
        return implode('', $this->bottom);
    }
}
