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

use local_auditenhance\hook\extend_screen;
use local_auditenhance\local\registry;

/**
 * Shared set-up and frame of the audit screens: admin page, tabs and the extend_screen hook.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page {
    /** @var extend_screen|null Hook dispatched for the current screen. */
    protected static ?extend_screen $hook = null;

    /**
     * Set up an admin screen.
     *
     * @param string $screenid Tab id: 'overview', 'area-<id>' or a registered screen id.
     * @param \moodle_url $url
     * @param string $title
     * @param string $capability Capability needed in the system context.
     * @return renderer
     */
    public static function setup(
        string $screenid,
        \moodle_url $url,
        string $title,
        string $capability = 'local/auditenhance:view'
    ): renderer {
        global $CFG, $PAGE;
        require_once($CFG->libdir . '/adminlib.php');
        admin_externalpage_setup('local_auditenhance_overview', '', null, $url, ['pagelayout' => 'report']);
        // Every screen belongs to the overview's admin page, so the admin menu and breadcrumb stay on it.
        \navigation_node::override_active_url(new \moodle_url('/local/auditenhance/index.php'));
        require_capability($capability, \context_system::instance());
        $PAGE->set_url($url);
        $PAGE->set_title($title);
        $PAGE->set_heading(get_string('pluginname', 'local_auditenhance'));
        if ($screenid !== 'overview') {
            $PAGE->navbar->add($title, $url);
        }
        return $PAGE->get_renderer('local_auditenhance');
    }

    /**
     * Header, heading, tabs and top content of other plugins.
     *
     * @param string $screenid
     * @param string $title
     * @param array $params Screen parameters for the hook.
     * @param int|null $runid Run shown on the screen.
     * @return string
     */
    public static function start(string $screenid, string $title, array $params = [], ?int $runid = null): string {
        global $OUTPUT;
        self::$hook = new extend_screen(self::hook_screen_id($screenid), $params, $runid);
        \core\di::get(\core\hook\manager::class)->dispatch(self::$hook);
        return $OUTPUT->header() . $OUTPUT->heading($title) . $OUTPUT->render(self::tabs($screenid)) . self::$hook->get_top();
    }

    /**
     * Bottom content of other plugins and the footer.
     *
     * @return string
     */
    public static function end(): string {
        global $OUTPUT;
        $bottom = self::$hook ? self::$hook->get_bottom() : '';
        return $bottom . $OUTPUT->footer();
    }

    /**
     * Screen id given to the extend_screen hook.
     *
     * @param string $screenid
     * @return string
     */
    protected static function hook_screen_id(string $screenid): string {
        return str_starts_with($screenid, 'area-') ? 'area' : $screenid;
    }

    /**
     * Tabs: overview, one per area and the registered screens.
     *
     * @param string $current
     * @return \tabtree
     */
    public static function tabs(string $current): \tabtree {
        $registry = registry::instance();
        $tabs = [new \tabobject(
            'overview',
            new \moodle_url('/local/auditenhance/index.php'),
            get_string('overview', 'local_auditenhance')
        )];
        foreach ($registry->get_areas() as $area) {
            $tabs[] = new \tabobject('area-' . $area->get_id(), $area->get_url(), $area->get_name());
        }
        foreach ($registry->get_screens() as $screen) {
            if ($screen->is_available()) {
                $tabs[] = new \tabobject($screen->get_id(), $screen->get_url(), $screen->get_name());
            }
        }
        return new \tabtree($tabs, $current);
    }
}
