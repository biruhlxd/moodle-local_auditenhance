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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/test_registry_helper.php');

/**
 * Tests for the registry and the register_extensions hook.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(registry::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(register_extensions::class)]
final class registry_test extends \advanced_testcase {
    use \local_auditenhance\test_registry_helper;

    /**
     * Reset the registry after each test.
     */
    protected function tearDown(): void {
        registry::reset();
        parent::tearDown();
    }

    /**
     * The test registry holds what was added.
     */
    public function test_registry_contents(): void {
        $registry = $this->make_test_registry();
        $this->assertSame(['testarea'], array_keys($registry->get_areas()));
        $this->assertCount(4, $registry->get_checks());
        $this->assertCount(4, $registry->get_checks(['testarea']));
        $this->assertCount(0, $registry->get_checks(['other']));
        $this->assertNotNull($registry->get_check('local_auditenhance_passing'));
        $this->assertSame('testarea', $registry->get_check('local_auditenhance_failing')->get_area());
        $this->assertArrayHasKey('numbers', $registry->get_scanners());
    }

    /**
     * Checks in an unknown area are dropped with a debugging message.
     */
    public function test_unknown_area(): void {
        require_once(__DIR__ . '/../fixtures/test_checks.php');
        $hook = new register_extensions();
        $hook->add_check(\local_auditenhance\check\testarea\passing::class);
        $registry = registry::from_hook($hook);
        $this->assertDebuggingCalled();
        $this->assertCount(0, $registry->get_checks());
    }

    /**
     * Adding a class that is not a check fails.
     */
    public function test_add_invalid_check(): void {
        $hook = new register_extensions();
        $this->expectException(\coding_exception::class);
        $hook->add_check(\stdClass::class);
    }

    /**
     * Removed checks are left out.
     */
    public function test_remove_check(): void {
        require_once(__DIR__ . '/../fixtures/test_checks.php');
        $hook = new register_extensions();
        $hook->add_area(new area('testarea', 'local_auditenhance'));
        $hook->add_check(\local_auditenhance\check\testarea\passing::class);
        $hook->add_check(\local_auditenhance\check\testarea\failing::class);
        $hook->remove_check('\\local_auditenhance\\check\\testarea\\failing');
        $this->assertCount(1, registry::from_hook($hook)->get_checks());
    }

    /**
     * Invalid area ids are refused.
     */
    public function test_invalid_area_id(): void {
        $this->expectException(\coding_exception::class);
        new area('Bad-Id', 'local_auditenhance');
    }

    /**
     * The real registry is filled through the hook and includes callbacks of other plugins.
     */
    public function test_hook_dispatch(): void {
        require_once(__DIR__ . '/../fixtures/test_checks.php');
        registry::reset();
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(
            register_extensions::class,
            function (register_extensions $hook): void {
                $hook->add_area(new area('testarea', 'local_auditenhance', 3));
                $hook->add_check(\local_auditenhance\check\testarea\passing::class);
            }
        );
        $registry = registry::instance();
        $this->assertNotNull($registry->get_area('testarea'));
        $this->assertSame(3, $registry->get_area('testarea')->get_weight());
        $this->assertNotNull($registry->get_check('local_auditenhance_passing'));
        \core\di::get(\core\hook\manager::class)->phpunit_stop_redirections();
    }

    /**
     * Area weights come from the settings.
     */
    public function test_area_weight_setting(): void {
        $this->resetAfterTest();
        $area = new area('testarea', 'local_auditenhance', 7);
        $this->assertSame(7, $area->get_weight());
        set_config('weight_testarea', 2, 'local_auditenhance');
        $this->assertSame(2, $area->get_weight());
    }

    /**
     * Core report checks follow the area setting.
     */
    public function test_core_report_checks(): void {
        require_once(__DIR__ . '/../fixtures/test_checks.php');
        $hook = new register_extensions();
        $hook->add_area(new area('testarea', 'local_auditenhance', 10, 1, 'security'));
        $hook->add_check(\local_auditenhance\check\testarea\passing::class);
        $registry = registry::from_hook($hook);
        $this->assertCount(1, $registry->get_core_report_checks('security'));
        $this->assertCount(0, $registry->get_core_report_checks('performance'));
    }
}
