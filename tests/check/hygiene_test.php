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

namespace local_auditenhance\check\hygiene;

use local_auditenhance\local\finding;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Tests for the site hygiene checks.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(neverloggedin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(inactiveusers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(suspendedenrolled::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(duplicateemails::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(emptycourses::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(noteacher::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(courseinactive::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(endedvisible::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(completionoff::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(unusedplugins::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(disabledplugins::class)]
final class hygiene_test extends \advanced_testcase {
    /**
     * Evaluate a check in a live context.
     *
     * @param \local_auditenhance\check\base $check
     * @return finding
     */
    private function run_check(\local_auditenhance\check\base $check): finding {
        return $check->evaluate(run_context::live());
    }

    /**
     * Every check has its strings and belongs to the hygiene area.
     */
    public function test_strings_and_area(): void {
        $checks = [new neverloggedin(), new inactiveusers(), new suspendedenrolled(), new duplicateemails(),
            new emptycourses(), new noteacher(), new courseinactive(), new endedvisible(), new completionoff(),
            new unusedplugins(), new disabledplugins()];
        foreach ($checks as $check) {
            $this->assertSame('hygiene', $check->get_area());
            $this->assertNotEmpty($check->get_explanation(), $check->get_id());
            $this->assertNotEmpty($check->get_fix(), $check->get_id());
        }
    }

    /**
     * Users that never logged in.
     */
    public function test_neverloggedin(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->set_field('user', 'lastaccess', time(), ['id' => get_admin()->id]);
        $this->getDataGenerator()->create_user(['lastaccess' => 0, 'timecreated' => time()]);
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new neverloggedin())->status);
        $this->getDataGenerator()->create_user(['lastaccess' => 0, 'timecreated' => time() - 60 * DAYSECS]);
        $finding = $this->run_check(new neverloggedin());
        $this->assertSame(severity::LOW, $finding->severity);
        $this->assertSame(1, $finding->itemcount);
    }

    /**
     * Inactive users.
     */
    public function test_inactiveusers(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_user(['lastaccess' => time()]);
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new inactiveusers())->status);
        $this->getDataGenerator()->create_user(['lastaccess' => time() - 400 * DAYSECS]);
        $this->assertSame(1, $this->run_check(new inactiveusers())->itemcount);
    }

    /**
     * Suspended but enrolled users.
     */
    public function test_suspendedenrolled(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user(['suspended' => 1]);
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new suspendedenrolled())->status);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $finding = $this->run_check(new suspendedenrolled());
        $this->assertSame(1, $finding->itemcount);
        $this->assertCount(1, $finding->details['sample']);
    }

    /**
     * Duplicate emails, more severe when login by email is allowed.
     */
    public function test_duplicateemails(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_user(['email' => 'same@example.com']);
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new duplicateemails())->status);
        $this->getDataGenerator()->create_user(['email' => 'SAME@example.com']);
        set_config('authloginviaemail', 0);
        $this->assertSame(severity::LOW, $this->run_check(new duplicateemails())->severity);
        set_config('authloginviaemail', 1);
        $this->assertSame(severity::MEDIUM, $this->run_check(new duplicateemails())->severity);
    }

    /**
     * Empty courses.
     */
    public function test_emptycourses(): void {
        global $DB;
        $this->resetAfterTest();
        $old = time() - 100 * DAYSECS;
        $empty = $this->getDataGenerator()->create_course();
        $full = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'timecreated', $old, ['id' => $empty->id]);
        $DB->set_field('course', 'timecreated', $old, ['id' => $full->id]);
        $this->getDataGenerator()->create_module('page', ['course' => $full->id]);
        $this->getDataGenerator()->create_module('forum', ['course' => $empty->id, 'type' => 'news']);
        $finding = $this->run_check(new emptycourses());
        $this->assertSame(1, $finding->itemcount);
        $this->assertStringContainsString((string)$empty->id, $finding->details['sample'][0]['url']);
    }

    /**
     * Courses without a teacher.
     */
    public function test_noteacher(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->assertSame(1, $this->run_check(new noteacher())->itemcount);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new noteacher())->status);
    }

    /**
     * Courses nobody visited.
     */
    public function test_courseinactive(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'timecreated', time() - 500 * DAYSECS, ['id' => $course->id]);
        $this->assertSame(1, $this->run_check(new courseinactive())->itemcount);
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('user_lastaccess', (object)['userid' => $user->id, 'courseid' => $course->id, 'timeaccess' => time()]);
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new courseinactive())->status);
    }

    /**
     * Ended but visible courses.
     */
    public function test_endedvisible(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_course(['startdate' => time() - 400 * DAYSECS, 'enddate' => time() + DAYSECS]);
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new endedvisible())->status);
        $this->getDataGenerator()->create_course(['startdate' => time() - 400 * DAYSECS, 'enddate' => time() - 100 * DAYSECS]);
        $this->assertSame(1, $this->run_check(new endedvisible())->itemcount);
        $this->getDataGenerator()->create_course(['startdate' => time() - 400 * DAYSECS, 'enddate' => time() - 100 * DAYSECS,
            'visible' => 0]);
        $this->assertSame(1, $this->run_check(new endedvisible())->itemcount);
    }

    /**
     * Completion tracking.
     */
    public function test_completionoff(): void {
        $this->resetAfterTest();
        set_config('enablecompletion', 0);
        $this->assertSame(severity::LOW, $this->run_check(new completionoff())->severity);
        set_config('enablecompletion', 1);
        $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new completionoff())->status);
        $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $finding = $this->run_check(new completionoff());
        $this->assertSame(severity::INFO, $finding->severity);
        $this->assertSame(1, $finding->itemcount);
    }

    /**
     * Usage counts per plugin and unused add-ons.
     */
    public function test_unusedplugins(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['format' => 'topics']);
        $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $usage = unusedplugins::get_usage();
        $this->assertSame(1, $usage['mod']['page']);
        $this->assertArrayHasKey('topics', $usage['format']);
        // A test site only has standard plugins.
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new unusedplugins())->status);
    }

    /**
     * Disabled add-ons: a test site only has standard plugins.
     */
    public function test_disabledplugins(): void {
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new disabledplugins())->status);
    }
}
