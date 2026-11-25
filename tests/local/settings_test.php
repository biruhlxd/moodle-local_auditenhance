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
 * Tests for the plugin settings.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(settings::class)]
final class settings_test extends \advanced_testcase {
    /**
     * Every threshold and setting has its strings.
     */
    public function test_strings(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $manager = get_string_manager();
        foreach (array_keys(settings::get_thresholds()) as $name) {
            $this->assertTrue($manager->string_exists('setting_' . $name, 'local_auditenhance'), $name);
            $this->assertTrue($manager->string_exists('setting_' . $name . '_desc', 'local_auditenhance'), $name);
        }
        $names = array_map(fn(\admin_setting $s) => $s->name, settings::get_extra_settings());
        foreach (
            ['sitevisibility', 'accessibilitystatementurl', 'includecore', 'excludeplugins', 'scanoninstall',
                'pluginbatchsize'] as $name
        ) {
            $this->assertContains($name, $names);
        }
    }

    /**
     * Thresholds fall back to their defaults.
     */
    public function test_threshold(): void {
        $this->resetAfterTest();
        $this->assertSame(3, settings::threshold('maxadmins'));
        set_config('maxadmins', 7, 'local_auditenhance');
        $this->assertSame(7, settings::threshold('maxadmins'));
        set_config('maxadmins', 'x', 'local_auditenhance');
        $this->assertSame(3, settings::threshold('maxadmins'));
        $this->assertSame(0, settings::threshold('unknown'));
    }
}
