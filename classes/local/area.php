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
 * An audit area, for example security or accessibility.
 *
 * Areas are registered through {@see \local_auditenhance\hook\register_extensions}. Strings come
 * from the given component: area_<id> (name) and area_<id>_desc (description, optional).
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class area {
    /**
     * Constructor.
     *
     * @param string $id Unique area id, lower case letters and digits.
     * @param string $component Component that holds the strings.
     * @param int $defaultweight Weight in the overall score when not set by the admin.
     * @param int $sortorder Position in lists.
     * @param string|null $corereport Core check report its checks also appear in: security, performance, status or null.
     */
    public function __construct(
        /** @var string Area id. */
        protected string $id,
        /** @var string Component. */
        protected string $component,
        /** @var int Default weight. */
        protected int $defaultweight = 10,
        /** @var int Sort order. */
        protected int $sortorder = 100,
        /** @var string|null Core report. */
        protected ?string $corereport = null,
    ) {
        if (!preg_match('/^[a-z][a-z0-9]*$/', $id)) {
            throw new \coding_exception('Invalid area id: ' . $id);
        }
    }

    /**
     * Area id.
     *
     * @return string
     */
    public function get_id(): string {
        return $this->id;
    }

    /**
     * Component that registered the area.
     *
     * @return string
     */
    public function get_component(): string {
        return $this->component;
    }

    /**
     * Localised name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('area_' . $this->id, $this->component);
    }

    /**
     * Localised description, '' if none.
     *
     * @return string
     */
    public function get_description(): string {
        $identifier = 'area_' . $this->id . '_desc';
        if (get_string_manager()->string_exists($identifier, $this->component)) {
            return get_string($identifier, $this->component);
        }
        return '';
    }

    /**
     * Default weight.
     *
     * @return int
     */
    public function get_default_weight(): int {
        return $this->defaultweight;
    }

    /**
     * Weight in the overall score, as set by the admin.
     *
     * @return int
     */
    public function get_weight(): int {
        $value = get_config('local_auditenhance', 'weight_' . $this->id);
        if ($value === false || $value === '' || !is_numeric($value)) {
            return $this->defaultweight;
        }
        return max(0, (int)$value);
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
     * Core check report for this area's checks.
     *
     * @return string|null
     */
    public function get_core_report(): ?string {
        return $this->corereport;
    }

    /**
     * URL of the area page.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/auditenhance/area.php', ['area' => $this->id]);
    }
}
