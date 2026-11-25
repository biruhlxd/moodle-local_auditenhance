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
 * A screen shown as a tab in the audit report.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class screen {
    /**
     * Constructor.
     *
     * @param string $id Unique screen id.
     * @param string $component Component holding the name string.
     * @param \moodle_url $url Page URL.
     * @param string $stringid Identifier of the name string.
     * @param int $sortorder Position among the tabs.
     * @param string $capability Capability needed in the system context to see the tab.
     */
    public function __construct(
        /** @var string Id. */
        protected string $id,
        /** @var string Component. */
        protected string $component,
        /** @var \moodle_url URL. */
        protected \moodle_url $url,
        /** @var string Name string identifier. */
        protected string $stringid,
        /** @var int Sort order. */
        protected int $sortorder = 100,
        /** @var string Capability. */
        protected string $capability = 'local/auditenhance:view',
    ) {
    }

    /**
     * Screen id.
     *
     * @return string
     */
    public function get_id(): string {
        return $this->id;
    }

    /**
     * Localised name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string($this->stringid, $this->component);
    }

    /**
     * Page URL.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return $this->url;
    }

    /**
     * Sort order.
     *
     * @return int
     */
    public function get_sortorder(): int {
        return $this->sortorder;
    }

    /**
     * Capability needed.
     *
     * @return string
     */
    public function get_capability(): string {
        return $this->capability;
    }

    /**
     * Can the current user see this screen?
     *
     * @return bool
     */
    public function is_available(): bool {
        return has_capability($this->capability, \context_system::instance());
    }
}
