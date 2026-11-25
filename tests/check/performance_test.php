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

namespace local_auditenhance\check\performance;

use local_auditenhance\local\finding;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;
use local_auditenhance\local\site_info;

/**
 * Tests for the performance and storage checks.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(mucdefaults::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(opcache::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(sessionhandler::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(largecourses::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(largefiles::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(orphanedfiles::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(backupretention::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(logretention::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(failedtasks::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(slowtasks::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(adhocbacklog::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_auditenhance\local\site_info::class)]
final class performance_test extends \advanced_testcase {
    /**
     * Evaluate a check in a live context.
     *
     * @param \local_auditenhance\check\base $check
     * @return finding
     */
    private function run_check(\local_auditenhance\check\base $check): finding {
        site_info::reset();
        return $check->evaluate(run_context::live());
    }

    /**
     * Create a file of a given size in a context.
     *
     * @param int $contextid
     * @param int $size Bytes.
     * @param string $component
     * @param string $filearea
     * @param int $timecreated
     * @return \stored_file
     */
    private function create_file(
        int $contextid,
        int $size,
        string $component = 'user',
        string $filearea = 'private',
        int $timecreated = 0
    ): \stored_file {
        static $counter = 0;
        $counter++;
        $fs = get_file_storage();
        $record = ['contextid' => $contextid, 'component' => $component, 'filearea' => $filearea, 'itemid' => 0,
            'filepath' => '/', 'filename' => 'file' . $counter . '.bin'];
        if ($timecreated) {
            $record['timecreated'] = $timecreated;
        }
        return $fs->create_file_from_string($record, str_repeat('x', $size) . $counter);
    }

    /**
     * Every check has its strings and belongs to the performance area.
     */
    public function test_strings_and_area(): void {
        $checks = [new mucdefaults(), new opcache(), new sessionhandler(), new largecourses(), new largefiles(),
            new orphanedfiles(), new backupretention(), new logretention(), new failedtasks(), new slowtasks(),
            new adhocbacklog()];
        foreach ($checks as $check) {
            $this->assertSame('performance', $check->get_area());
            $this->assertNotEmpty($check->get_explanation(), $check->get_id());
            $this->assertNotEmpty($check->get_fix(), $check->get_id());
        }
    }

    /**
     * Cache stores.
     */
    public function test_mucdefaults(): void {
        $finding = $this->run_check(new mucdefaults());
        // Test sites only have the default stores.
        $this->assertTrue($finding->is_failure());
        $this->assertSame(severity::LOW, $finding->severity);
    }

    /**
     * OPcache uses the value seen by the web server when run from the command line.
     */
    public function test_opcache(): void {
        $this->resetAfterTest();
        unset_config('webopcache', 'local_auditenhance');
        $this->assertSame(finding::STATUS_SKIP, $this->run_check(new opcache())->status);
        set_config('webopcache', 0, 'local_auditenhance');
        $this->assertSame(severity::HIGH, $this->run_check(new opcache())->severity);
        set_config('webopcache', 1, 'local_auditenhance');
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new opcache())->status);
    }

    /**
     * Session handler on a large site.
     */
    public function test_sessionhandler(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->session_handler_class = '\core\session\file';
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new sessionhandler())->status);
        $CFG->session_handler_class = '\core\session\database';
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new sessionhandler())->status);
        $this->getDataGenerator()->create_user(['lastaccess' => time()]);
        set_config('largesiteusers', 0, 'local_auditenhance');
        $this->assertSame(severity::MEDIUM, $this->run_check(new sessionhandler())->severity);
    }

    /**
     * Largest courses.
     */
    public function test_largecourses(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->create_file(\context_module::instance($page->cmid)->id, 2 * 1024 * 1024, 'mod_page', 'content');
        $finding = $this->run_check(new largecourses());
        $this->assertSame(finding::STATUS_PASS, $finding->status);
        $this->assertNotEmpty($finding->details['sample']);
        set_config('largecoursemb', 1, 'local_auditenhance');
        $finding = $this->run_check(new largecourses());
        $this->assertSame(severity::LOW, $finding->severity);
        $this->assertSame(1, $finding->itemcount);
    }

    /**
     * Largest files.
     */
    public function test_largefiles(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->create_file(\context_user::instance($user->id)->id, 2 * 1024 * 1024);
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new largefiles())->status);
        set_config('largefilemb', 1, 'local_auditenhance');
        $finding = $this->run_check(new largefiles());
        $this->assertSame(severity::LOW, $finding->severity);
        $this->assertSame(1, $finding->itemcount);
    }

    /**
     * Orphaned files.
     */
    public function test_orphanedfiles(): void {
        global $DB;
        $this->resetAfterTest();
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new orphanedfiles())->status);
        $user = $this->getDataGenerator()->create_user();
        $file = $this->create_file(\context_user::instance($user->id)->id, 10);
        $DB->set_field('files', 'contextid', 999999, ['id' => $file->get_id()]);
        $finding = $this->run_check(new orphanedfiles());
        $this->assertSame(severity::LOW, $finding->severity);
        $this->assertSame(1, $finding->itemcount);
    }

    /**
     * Backup retention.
     */
    public function test_backupretention(): void {
        $this->resetAfterTest();
        set_config('backup_auto_active', 1, 'backup');
        set_config('backup_auto_max_kept', 0, 'backup');
        set_config('backup_auto_delete_days', 0, 'backup');
        $this->assertSame(severity::MEDIUM, $this->run_check(new backupretention())->severity);
        set_config('backup_auto_max_kept', 2, 'backup');
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new backupretention())->status);
        $course = $this->getDataGenerator()->create_course();
        $this->create_file(\context_course::instance($course->id)->id, 10, 'backup', 'automated', time() - 400 * DAYSECS);
        $this->assertSame(severity::LOW, $this->run_check(new backupretention())->severity);
    }

    /**
     * Log retention.
     */
    public function test_logretention(): void {
        $this->resetAfterTest();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('loglifetime', 0, 'logstore_standard');
        $this->assertSame(severity::MEDIUM, $this->run_check(new logretention())->severity);
        set_config('loglifetime', 365, 'logstore_standard');
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new logretention())->status);
        set_config('enabled_stores', '', 'tool_log');
        $this->assertSame(finding::STATUS_SKIP, $this->run_check(new logretention())->status);
    }

    /**
     * Failing scheduled tasks.
     */
    public function test_failedtasks(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->set_field('task_scheduled', 'faildelay', 0);
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new failedtasks())->status);
        $task = $DB->get_record('task_scheduled', ['disabled' => 0], '*', IGNORE_MULTIPLE);
        $DB->set_field('task_scheduled', 'faildelay', 120, ['id' => $task->id]);
        $this->assertSame(severity::MEDIUM, $this->run_check(new failedtasks())->severity);
        $DB->set_field('task_scheduled', 'faildelay', 2 * HOURSECS, ['id' => $task->id]);
        $this->assertSame(severity::HIGH, $this->run_check(new failedtasks())->severity);
    }

    /**
     * Slow scheduled tasks.
     */
    public function test_slowtasks(): void {
        global $DB;
        $this->resetAfterTest();
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new slowtasks())->status);
        $DB->insert_record('task_log', (object)['type' => 0, 'component' => 'core', 'classname' => '\core\task\slow_task',
            'userid' => 0, 'timestart' => time() - HOURSECS, 'timeend' => time(), 'dbreads' => 0, 'dbwrites' => 0,
            'result' => 0, 'output' => '', 'hostname' => 'x', 'pid' => 1]);
        $finding = $this->run_check(new slowtasks());
        $this->assertSame(severity::LOW, $finding->severity);
        $this->assertSame(1, $finding->itemcount);
    }

    /**
     * Ad hoc backlog.
     */
    public function test_adhocbacklog(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->delete_records('task_adhoc');
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new adhocbacklog())->status);
        $record = ['component' => 'core', 'classname' => '\core\task\x', 'nextruntime' => time() - 2 * HOURSECS,
            'faildelay' => 0, 'customdata' => '', 'userid' => null, 'blocking' => 0];
        $DB->insert_record('task_adhoc', (object)$record);
        $DB->insert_record('task_adhoc', (object)$record);
        set_config('adhocbacklog', 1, 'local_auditenhance');
        $this->assertSame(severity::MEDIUM, $this->run_check(new adhocbacklog())->severity);
        set_config('adhocbacklog', 10, 'local_auditenhance');
        $DB->set_field('task_adhoc', 'faildelay', 60);
        $this->assertSame(severity::LOW, $this->run_check(new adhocbacklog())->severity);
    }
}
