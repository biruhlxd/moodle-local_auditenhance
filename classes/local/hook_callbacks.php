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

use local_auditenhance\hook\register_extensions;

/**
 * Registers the plugin's own areas, checks, scanners, screens and actions.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {
    /**
     * Callback for the register_extensions hook.
     *
     * @param register_extensions $hook
     */
    public static function register_extensions(register_extensions $hook): void {
        $hook->add_area(new area('security', 'local_auditenhance', 30, 10, 'security'));
        $hook->add_checks([
            \local_auditenhance\check\security\debugdisplay::class,
            \local_auditenhance\check\security\cronrunning::class,
            \local_auditenhance\check\security\forcehttps::class,
            \local_auditenhance\check\security\passwordpolicy::class,
            \local_auditenhance\check\security\selfregistration::class,
            \local_auditenhance\check\security\guestlogin::class,
            \local_auditenhance\check\security\crawlers::class,
            \local_auditenhance\check\security\siteadmins::class,
            \local_auditenhance\check\security\adminlogin::class,
            \local_auditenhance\check\security\defaultadmin::class,
            \local_auditenhance\check\security\riskycaps::class,
            \local_auditenhance\check\security\coreversion::class,
            \local_auditenhance\check\security\pluginupdates::class,
        ]);

        $hook->add_area(new area('performance', 'local_auditenhance', 15, 20, 'performance'));
        $hook->add_checks([
            \local_auditenhance\check\performance\mucdefaults::class,
            \local_auditenhance\check\performance\opcache::class,
            \local_auditenhance\check\performance\sessionhandler::class,
            \local_auditenhance\check\performance\largecourses::class,
            \local_auditenhance\check\performance\largefiles::class,
            \local_auditenhance\check\performance\orphanedfiles::class,
            \local_auditenhance\check\performance\backupretention::class,
            \local_auditenhance\check\performance\logretention::class,
            \local_auditenhance\check\performance\failedtasks::class,
            \local_auditenhance\check\performance\slowtasks::class,
            \local_auditenhance\check\performance\adhocbacklog::class,
        ]);

        $hook->add_area(new area('hygiene', 'local_auditenhance', 15, 30));
        $hook->add_checks([
            \local_auditenhance\check\hygiene\neverloggedin::class,
            \local_auditenhance\check\hygiene\inactiveusers::class,
            \local_auditenhance\check\hygiene\suspendedenrolled::class,
            \local_auditenhance\check\hygiene\duplicateemails::class,
            \local_auditenhance\check\hygiene\emptycourses::class,
            \local_auditenhance\check\hygiene\noteacher::class,
            \local_auditenhance\check\hygiene\courseinactive::class,
            \local_auditenhance\check\hygiene\endedvisible::class,
            \local_auditenhance\check\hygiene\completionoff::class,
            \local_auditenhance\check\hygiene\unusedplugins::class,
            \local_auditenhance\check\hygiene\disabledplugins::class,
        ]);

        $hook->add_area(new area('accessibility', 'local_auditenhance', 20, 40));
        $hook->add_checks([
            \local_auditenhance\check\accessibility\imagealt::class,
            \local_auditenhance\check\accessibility\headings::class,
            \local_auditenhance\check\accessibility\linktext::class,
            \local_auditenhance\check\accessibility\tables::class,
            \local_auditenhance\check\accessibility\contrast::class,
            \local_auditenhance\check\accessibility\media::class,
            \local_auditenhance\check\accessibility\documents::class,
            \local_auditenhance\check\accessibility\htmllang::class,
            \local_auditenhance\check\accessibility\statement::class,
            \local_auditenhance\check\accessibility\themecontrast::class,
        ]);
        $hook->add_scanner(new \local_auditenhance\local\scanner\brickfield_scanner());
        $hook->add_scanner(new \local_auditenhance\local\scanner\content_scanner());
        $hook->add_content_source(new \local_auditenhance\local\content\source\course_summary());
        $hook->add_content_source(new \local_auditenhance\local\content\source\course_section());
        $hook->add_content_source(new \local_auditenhance\local\content\source\module_field('page', ['intro', 'content']));
        $hook->add_content_source(new \local_auditenhance\local\content\source\module_field('label', ['intro']));
        $hook->add_content_source(new \local_auditenhance\local\content\source\module_field('book', ['intro']));
        $hook->add_content_source(new \local_auditenhance\local\content\source\book_chapter());
        $hook->add_content_source(new \local_auditenhance\local\content\source\module_field('forum', ['intro']));
        $hook->add_content_source(new \local_auditenhance\local\content\source\module_field('quiz', ['intro']));
        $hook->add_content_source(new \local_auditenhance\local\content\source\question_text());
    }
}
