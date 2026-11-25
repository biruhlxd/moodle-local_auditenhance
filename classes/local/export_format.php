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
 * An export format for audit data, offered as a download button on matching screens.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface export_format {
    /**
     * Unique id of the format.
     *
     * @return string
     */
    public function get_id(): string;

    /**
     * Localised name shown on the button.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Can this format export the given scope?
     *
     * Scopes used by the report screens: 'run' (a whole run), 'area:<areaid>' (one area),
     * 'course:<courseid>' and 'plugin:<component>'. Other plugins may use their own.
     *
     * @param string $scope
     * @return bool
     */
    public function supports(string $scope): bool;

    /**
     * Capability needed in the system context to use this format.
     *
     * @return string
     */
    public function get_capability(): string;

    /**
     * Send the export to the browser (or print it on the CLI) and stop.
     *
     * @param int $runid
     * @param string $scope
     */
    public function export(int $runid, string $scope): void;
}
