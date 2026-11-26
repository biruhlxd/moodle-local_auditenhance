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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/test_registry_helper.php');

/**
 * Tests for runs, run storage and results.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(run_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(run_context::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(results::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(finding::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(item::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(summary::class)]
final class run_manager_test extends \advanced_testcase {
    use \local_auditenhance\test_registry_helper;

    /**
     * Reset the registry after each test.
     */
    protected function tearDown(): void {
        registry::reset();
        parent::tearDown();
    }

    /**
     * A full run scans, evaluates, stores and scores.
     */
    public function test_full_run(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->make_test_registry();
        $sink = $this->redirectEvents();

        $run = run_manager::create_run(null, 'test');
        $this->assertTrue(run_manager::execute((int)$run->id));

        $run = run_manager::get_run((int)$run->id);
        $this->assertSame(run_manager::STATUS_COMPLETE, $run->status);
        $this->assertEquals(100, $run->progress);
        // Failing (high, 15) + scanned (medium, 7); throwing is an error and does not count.
        $this->assertEquals(100 - 15 - 7, $run->score);
        $this->assertSame(['testarea' => 78], json_decode($run->areascores, true));

        $findings = results::get_for_run((int)$run->id);
        $this->assertCount(4, $findings);
        $this->assertSame(finding::STATUS_PASS, $findings['local_auditenhance_passing']->status);
        $this->assertSame(finding::STATUS_ERROR, $findings['local_auditenhance_throwing']->status);
        $this->assertSame(10, $findings['local_auditenhance_scanned']->itemcount);
        $this->assertSame(severity::MEDIUM, $findings['local_auditenhance_scanned']->severity);
        $this->assertEquals(10, $DB->count_records('local_auditenhance_item', ['runid' => $run->id]));

        $events = array_map(fn($e) => get_class($e), $sink->get_events());
        $this->assertContains(\local_auditenhance\event\audit_started::class, $events);
        $this->assertContains(\local_auditenhance\event\audit_completed::class, $events);
    }

    /**
     * A run with a time budget stops and continues from its saved state.
     */
    public function test_run_resumes(): void {
        global $DB;
        $this->resetAfterTest();
        $this->make_test_registry();
        $run = run_manager::create_run(null, 'test');
        // One step per execution: four scanner batches and four evaluations.
        $executions = 0;
        do {
            $finished = run_manager::execute((int)$run->id, 0, null, 1);
            $executions++;
            if (!$finished) {
                $this->assertSame(run_manager::STATUS_RUNNING, run_manager::get_run((int)$run->id)->status);
            }
        } while (!$finished && $executions < 50);
        $this->assertGreaterThanOrEqual(8, $executions);
        $this->assertTrue($finished);
        $this->assertEquals(10, $DB->count_records('local_auditenhance_item', ['runid' => $run->id]));
        $findings = results::get_for_run((int)$run->id);
        $this->assertSame(10, $findings['local_auditenhance_scanned']->itemcount);
    }

    /**
     * The item limit keeps counts exact but stores fewer items.
     */
    public function test_item_limit(): void {
        global $DB;
        $this->resetAfterTest();
        $this->make_test_registry();
        set_config('maxitems', 3, 'local_auditenhance');
        $run = run_manager::create_run(null, 'test');
        run_manager::execute((int)$run->id);
        $this->assertEquals(3, $DB->count_records('local_auditenhance_item', ['runid' => $run->id]));
        $this->assertSame(10, results::get_for_run((int)$run->id)['local_auditenhance_scanned']->itemcount);
    }

    /**
     * Queueing reuses an active run and creates an ad hoc task.
     */
    public function test_queue(): void {
        $this->resetAfterTest();
        $this->make_test_registry();
        $first = run_manager::queue(null, 'web');
        $second = run_manager::queue(null, 'web');
        $this->assertSame($first, $second);
        $tasks = \core\task\manager::get_adhoc_tasks(\local_auditenhance\task\run_audit::class);
        $this->assertCount(1, $tasks);
        $this->assertNotNull(run_manager::get_active_run());
    }

    /**
     * Unknown areas are refused.
     */
    public function test_unknown_area(): void {
        $this->resetAfterTest();
        $this->make_test_registry();
        $this->expectException(\invalid_parameter_exception::class);
        run_manager::create_run(['nope']);
    }

    /**
     * Area runs only include their area and current results combine runs.
     */
    public function test_current_results(): void {
        $this->resetAfterTest();
        $registry = $this->make_test_registry(false);
        $run = run_manager::create_run(['testarea'], 'test');
        run_manager::execute((int)$run->id);

        $current = results::get_current(true, $registry);
        $this->assertSame('live', $current['local_auditenhance_passing']['source']);
        $this->assertSame('run', $current['local_auditenhance_scanned']['source']);
        $this->assertSame((int)$run->id, $current['local_auditenhance_scanned']['runid']);

        $scores = results::get_current_scores($current, $registry);
        $this->assertSame(78, $scores['overall']);

        $top = results::get_top_failures($current, 10, null, $registry);
        $this->assertSame(['local_auditenhance_failing', 'local_auditenhance_scanned'], array_keys($top));

        // Muting removes the check from the score and from the top list.
        mutes::mute('local_auditenhance_failing', 'Accepted risk');
        $scores = results::get_current_scores(results::get_current(true, $registry), $registry);
        $this->assertSame(93, $scores['overall']);
        $this->assertCount(1, results::get_top_failures($current, 10, null, $registry));
    }

    /**
     * Latest finding of a heavy check is used by the core check API.
     */
    public function test_core_result_from_latest_run(): void {
        $this->resetAfterTest();
        $registry = $this->make_test_registry(false);
        $check = $registry->get_check('local_auditenhance_scanned');
        $this->assertSame(\core\check\result::NA, $check->get_result()->get_status());
        $run = run_manager::create_run(null, 'test');
        run_manager::execute((int)$run->id);
        $this->assertSame(\core\check\result::WARNING, $check->get_result()->get_status());
        $this->assertSame(
            \core\check\result::ERROR,
            $registry->get_check('local_auditenhance_failing')->get_result()->get_status()
        );
        $this->assertSame(
            \core\check\result::OK,
            $registry->get_check('local_auditenhance_passing')->get_result()->get_status()
        );
    }

    /**
     * Old runs are removed beyond the number to keep.
     */
    public function test_cleanup(): void {
        global $DB;
        $this->resetAfterTest();
        $this->make_test_registry(false);
        set_config('keepruns', 2, 'local_auditenhance');
        for ($i = 0; $i < 4; $i++) {
            $run = run_manager::create_run(null, 'test');
            run_manager::execute((int)$run->id);
        }
        $this->assertEquals(2, $DB->count_records('local_auditenhance_run'));
        $this->assertEquals(20, $DB->count_records('local_auditenhance_item'));
    }

    /**
     * Items are kept for the latest runs only; results stay.
     */
    public function test_cleanup_items(): void {
        global $DB;
        $this->resetAfterTest();
        $this->make_test_registry(false);
        set_config('keepitemruns', 1, 'local_auditenhance');
        $runids = [];
        for ($i = 0; $i < 3; $i++) {
            $run = run_manager::create_run(null, 'test');
            run_manager::execute((int)$run->id);
            $runids[] = (int)$run->id;
        }
        $this->assertEquals(10, $DB->count_records('local_auditenhance_item'));
        $this->assertEquals(10, $DB->count_records('local_auditenhance_item', ['runid' => end($runids)]));
        $this->assertEquals(9, $DB->count_records('local_auditenhance_result'));
    }

    /**
     * Summaries given as language string references are translated when read back.
     */
    public function test_summary_reference(): void {
        $summary = new summary('check_imagealt_fail', 'local_auditenhance', 3);
        $finding = finding::fail(severity::MEDIUM, $summary);
        $this->assertSame(get_string('check_imagealt_fail', 'local_auditenhance', 3), $finding->summary);
        $record = (object)['status' => $finding->status, 'severity' => $finding->severity, 'summary' => 'Stored text',
            'details' => $finding->encode_details(), 'itemcount' => 0];
        $copy = finding::from_record($record);
        $this->assertSame($finding->summary, $copy->summary);
        $this->assertSame('check_imagealt_fail', $copy->summaryref->identifier);
        $this->assertArrayNotHasKey('_str', $copy->details);

        // Object parameters survive storage.
        $finding = finding::fail(severity::LOW, new summary(
            'check_largefiles_fail',
            'local_auditenhance',
            (object)['count' => 2, 'size' => '1MB']
        ));
        $record->details = $finding->encode_details();
        $this->assertStringContainsString('1MB', finding::from_record($record)->summary);

        // A string that no longer exists falls back to the stored text.
        $record->details = json_encode(['_str' => ['identifier' => 'nosuchstring', 'component' => 'local_auditenhance']]);
        $this->assertSame('Stored text', finding::from_record($record)->summary);
    }

    /**
     * Live context keeps items in memory.
     */
    public function test_live_context(): void {
        $registry = $this->make_test_registry(false);
        $check = $registry->get_check('local_auditenhance_scanned');
        $context = run_context::live();
        $this->assertTrue($context->is_live());
        $this->assertNull($context->get_runid());
        $context->add_item($check, new item('x', severity::HIGH));
        $this->assertSame(1, $context->count_items($check->get_ref()));
        $this->assertSame(severity::HIGH, $context->get_max_item_severity($check->get_ref()));
        $this->assertCount(1, $context->get_items($check->get_ref()));
    }

    /**
     * Findings survive storage.
     */
    public function test_finding_round_trip(): void {
        $finding = finding::fail(severity::LOW, 'Summary', ['a' => 1], 3)
            ->with_link(new \moodle_url('/admin/index.php'))
            ->with_sample([['label' => 'One', 'url' => new \moodle_url('/user/view.php', ['id' => 2])]]);
        $record = (object)['status' => $finding->status, 'severity' => $finding->severity, 'summary' => $finding->summary,
            'details' => $finding->encode_details(), 'itemcount' => $finding->itemcount];
        $copy = finding::from_record($record);
        $this->assertSame('Summary', $copy->summary);
        $this->assertSame(3, $copy->itemcount);
        $this->assertSame(1, $copy->details['a']);
        $this->assertStringContainsString('/admin/index.php', $copy->link);
        $this->assertSame('One', $copy->details['sample'][0]['label']);
    }
}
