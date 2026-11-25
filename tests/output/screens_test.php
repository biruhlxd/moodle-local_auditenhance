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

use local_auditenhance\local\mutes;
use local_auditenhance\local\registry;
use local_auditenhance\local\run_manager;

/**
 * Tests for the screens' template data and rendering.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(overview_page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(area_page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(courses_page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(plugins_page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(mutes_page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(finding_exporter::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(mute_action::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(renderer::class)]
final class screens_test extends \advanced_testcase {
    /**
     * Renderer.
     *
     * @return renderer
     */
    private function renderer(): renderer {
        global $PAGE;
        $PAGE->set_url('/local/auditenhance/index.php');
        $PAGE->set_context(\context_system::instance());
        return $PAGE->get_renderer('local_auditenhance');
    }

    /**
     * Create a course with an accessibility problem and run a full audit.
     *
     * @return \stdClass Run record.
     */
    private function run_audit(): \stdClass {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Biology']);
        $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'content' => '<img src="x.png">']);
        $run = run_manager::create_run(null, 'test');
        run_manager::execute((int)$run->id);
        return $run;
    }

    /**
     * Overview before and after an audit.
     */
    public function test_overview(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $output = $this->renderer();
        $data = (new overview_page())->export_for_template($output);
        $this->assertArrayNotHasKey('lastrun', $data);
        $this->assertTrue($data['canrun']);
        $this->assertSame(array_keys(registry::instance()->get_areas()), array_column($data['areas'], 'id'));

        $this->run_audit();
        $data = (new overview_page())->export_for_template($output);
        $this->assertArrayHasKey('lastrun', $data);
        $this->assertTrue($data['hasoverall']);
        $this->assertLessThanOrEqual(overview_page::TOP, count($data['top']));
        $this->assertTrue($data['top'][0]['failed']);
        $html = $output->render(new overview_page());
        $this->assertStringContainsString('local-auditenhance-overview', $html);

        // While a run is queued the overview shows its progress.
        run_manager::queue(null, 'web');
        $data = (new overview_page())->export_for_template($output);
        $this->assertTrue($data['active']['queued']);
    }

    /**
     * Area page groups by severity and lists muted checks separately.
     */
    public function test_area_page(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $output = $this->renderer();
        $this->run_audit();
        $area = registry::instance()->get_area('accessibility');
        $data = (new area_page($area))->export_for_template($output);
        $this->assertTrue($data['hasproblems']);
        $names = array_merge(...array_map(fn($g) => array_column($g['findings'], 'ref'), $data['groups']));
        $this->assertContains('local_auditenhance_imagealt', $names);

        mutes::mute('local_auditenhance_imagealt', 'Decorative images only');
        $data = (new area_page($area))->export_for_template($output);
        $this->assertSame(['local_auditenhance_imagealt'], array_column($data['muted'], 'ref'));
        $this->assertSame('Decorative images only', $data['muted'][0]['mutereason']);
        $this->assertSame('Unmute', $data['muted'][0]['actions'][0]['text']);
        $this->assertStringContainsString('Decorative images only', $output->render(new area_page($area)));
    }

    /**
     * Accessibility by course and plugin risk by plugin.
     */
    public function test_courses_and_plugins(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $output = $this->renderer();
        $data = (new courses_page('accessibility'))->export_for_template($output);
        $this->assertFalse($data['hasrun']);

        $this->run_audit();
        $data = (new courses_page('accessibility'))->export_for_template($output);
        $this->assertTrue($data['hascourses']);
        $this->assertSame('Biology', $data['courses'][0]['name']);
        $this->assertStringContainsString('Biology', $output->render(new courses_page('accessibility')));

        $data = (new plugins_page('pluginrisk'))->export_for_template($output);
        $this->assertTrue($data['hasrun']);
        $this->assertFalse($data['detail']);
        $data = (new plugins_page('pluginrisk', 'local_example'))->export_for_template($output);
        $this->assertTrue($data['detail']);
        $this->assertFalse($data['hasitems']);
        $this->assertSame('local_example', plugins_page::component_name('local_example'));
        $this->assertStringContainsString('(mod_forum)', plugins_page::component_name('mod_forum'));
    }

    /**
     * Mutes page and mute action.
     */
    public function test_mutes(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $output = $this->renderer();
        $this->assertFalse((new mutes_page())->export_for_template($output)['hasmutes']);
        mutes::mute('local_auditenhance_guestlogin', 'Guests are welcome');
        $data = (new mutes_page())->export_for_template($output);
        $this->assertSame('Guest access', $data['mutes'][0]['name']);
        $this->assertNotEmpty($data['mutes'][0]['unmuteurl']);

        $check = registry::instance()->get_check('local_auditenhance_guestlogin');
        $action = new mute_action();
        $fail = \local_auditenhance\local\finding::fail('low', 'x');
        $this->assertCount(1, $action->get_actions($check, $fail, false));
        $this->assertCount(0, $action->get_actions($check, \local_auditenhance\local\finding::pass('ok'), false));
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertCount(0, $action->get_actions($check, $fail, false));
    }

    /**
     * Tabs come from the registry.
     */
    public function test_tabs(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ids = array_map(fn($tab) => $tab->id, page::tabs('overview')->subtree);
        $this->assertSame('overview', $ids[0]);
        foreach (array_keys(registry::instance()->get_areas()) as $areaid) {
            $this->assertContains('area-' . $areaid, $ids);
        }
        foreach (['courses', 'plugins', 'mutes'] as $screen) {
            $this->assertContains($screen, $ids);
        }
    }
}
