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

namespace local_auditenhance\output;

/**
 * Renderer for the audit screens.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render the results of one course.
     *
     * @param course_page $page
     * @return string
     */
    protected function render_course_page(course_page $page): string {
        return $this->render_from_template('local_auditenhance/course_page', $page->export_for_template($this));
    }

    /**
     * Render the overview.
     *
     * @param overview_page $page
     * @return string
     */
    protected function render_overview_page(overview_page $page): string {
        return $this->render_from_template('local_auditenhance/overview_page', $page->export_for_template($this));
    }

    /**
     * Render an area page.
     *
     * @param area_page $page
     * @return string
     */
    protected function render_area_page(area_page $page): string {
        return $this->render_from_template('local_auditenhance/area_page', $page->export_for_template($this));
    }

    /**
     * Render the accessibility by course page.
     *
     * @param courses_page $page
     * @return string
     */
    protected function render_courses_page(courses_page $page): string {
        return $this->render_from_template('local_auditenhance/courses_page', $page->export_for_template($this));
    }

    /**
     * Render the plugin risk by plugin page.
     *
     * @param plugins_page $page
     * @return string
     */
    protected function render_plugins_page(plugins_page $page): string {
        return $this->render_from_template('local_auditenhance/plugins_page', $page->export_for_template($this));
    }

    /**
     * Render the mutes page.
     *
     * @param mutes_page $page
     * @return string
     */
    protected function render_mutes_page(mutes_page $page): string {
        return $this->render_from_template('local_auditenhance/mutes_page', $page->export_for_template($this));
    }
}
