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

namespace local_auditenhance\task;

use local_auditenhance\local\registry;
use local_auditenhance\local\run_manager;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/test_registry_helper.php');

/**
 * Tests for the ad hoc audit task.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(run_audit::class)]
final class run_audit_test extends \advanced_testcase {
    use \local_auditenhance\test_registry_helper;

    /**
     * Reset the registry after each test.
     */
    protected function tearDown(): void {
        registry::reset();
        parent::tearDown();
    }

    /**
     * The queued task completes the run.
     */
    public function test_task_completes_run(): void {
        $this->resetAfterTest();
        $this->make_test_registry();
        $runid = run_manager::queue(null, 'web');
        ob_start();
        $this->runAdhocTasks(run_audit::class);
        $output = ob_get_clean();
        $this->assertStringContainsString('%', $output);
        $this->assertSame(run_manager::STATUS_COMPLETE, run_manager::get_run($runid)->status);
        $this->assertNull(run_manager::get_active_run());
    }
}
