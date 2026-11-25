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
 * Interface of a source of HTML content for the content scanner.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface content_source {
    /**
     * Unique id of the source.
     *
     * @return string
     */
    public function get_id(): string;

    /**
     * Is this source available on the site (for example, is its module installed)?
     *
     * @return bool
     */
    public function is_available(): bool;

    /**
     * Read the next records.
     *
     * @param int $afterid Only records with a higher id.
     * @param int $limit Maximum number of records.
     * @return array{0: int, 1: content_item[]} Highest record id read (0 when none) and the content items.
     */
    public function fetch(int $afterid, int $limit): array;
}
