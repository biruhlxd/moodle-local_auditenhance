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

namespace local_auditenhance\local\scanner;

use local_auditenhance\local\content\brickfield;
use local_auditenhance\local\items;
use local_auditenhance\local\registry;
use local_auditenhance\local\results;
use local_auditenhance\local\run_context;
use local_auditenhance\local\run_manager;

/**
 * Tests for the content scanner, content sources and Brickfield results.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(content_scanner::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(brickfield_scanner::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_auditenhance\local\content\brickfield::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_auditenhance\local\content\source\course_summary::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_auditenhance\local\content\source\course_section::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_auditenhance\local\content\source\module_field::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_auditenhance\local\content\source\book_chapter::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_auditenhance\local\content\source\question_text::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_auditenhance\local\items::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_auditenhance\output\course_page::class)]
final class content_scanner_test extends \advanced_testcase {
    /** @var string HTML with one image without alt text. */
    private const BAD = '<p>Intro <img src="x.png"></p>';

    /**
     * Reset caches.
     */
    protected function tearDown(): void {
        registry::reset();
        brickfield::reset();
        parent::tearDown();
    }

    /**
     * Create a course with content in every source.
     *
     * @return \stdClass The course.
     */
    private function create_content(): \stdClass {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['summary' => self::BAD, 'summaryformat' => FORMAT_HTML,
            'numsections' => 2]);
        $DB->set_field('course_sections', 'summary', self::BAD, ['course' => $course->id, 'section' => 1]);
        $generator->create_module('page', ['course' => $course->id, 'content' => self::BAD, 'intro' => '<p>Fine</p>']);
        $generator->create_module('label', ['course' => $course->id, 'intro' => self::BAD]);
        $generator->create_module('forum', ['course' => $course->id, 'intro' => self::BAD]);
        $generator->create_module('quiz', ['course' => $course->id, 'intro' => self::BAD]);
        $book = $generator->create_module('book', ['course' => $course->id, 'intro' => '<p>Fine</p>']);
        $generator->get_plugin_generator('mod_book')->create_chapter(['bookid' => $book->id, 'content' => self::BAD]);
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category(['contextid' => \context_course::instance($course->id)->id]);
        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $category->id]);
        $DB->set_field('question', 'questiontext', self::BAD, ['id' => $question->id]);
        return $course;
    }

    /**
     * A full accessibility run finds the image in every source and stores items per course.
     */
    public function test_full_run(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->create_content();
        set_config('batchsize', 2, 'local_auditenhance');

        $run = run_manager::create_run(['accessibility'], 'test');
        $this->assertTrue(run_manager::execute((int)$run->id));
        $findings = results::get_for_run((int)$run->id);
        $imagealt = $findings['local_auditenhance_imagealt'];
        $this->assertTrue($imagealt->is_failure());
        // Course summary, section, page content, label, forum, quiz, book chapter and question.
        $this->assertSame(8, $imagealt->itemcount);

        $counts = items::count_by_check_for_course((int)$run->id, (int)$course->id, 'accessibility');
        $this->assertSame(8, $counts['local_auditenhance_imagealt']);
        $components = array_column(items::get_for_course(
            (int)$run->id,
            (int)$course->id,
            'local_auditenhance_imagealt'
        ), 'component');
        foreach (['core_course', 'mod_page', 'mod_label', 'mod_forum', 'mod_quiz', 'mod_book', 'core_question'] as $component) {
            $this->assertContains($component, $components);
        }
        $this->assertSame((int)$run->id, items::get_latest_run_for_area('accessibility'));
        $this->assertSame(1, items::count_courses((int)$run->id, 'accessibility'));
        $bycourse = items::count_by_course((int)$run->id, 'accessibility');
        $this->assertEquals($course->id, $bycourse[0]->courseid);

        // The teacher page shows the counts and a sample.
        $area = registry::instance()->get_area('accessibility');
        $page = new \local_auditenhance\output\course_page($course, $area, (int)$run->id, 3);
        $data = $page->export_for_template($PAGE->get_renderer('local_auditenhance'));
        $this->assertTrue($data['hasrun']);
        $this->assertSame(8, $data['checks'][0]['count']);
        $this->assertCount(3, $data['checks'][0]['items']);
        $this->assertSame(5, $data['checks'][0]['more']);
    }

    /**
     * Sources skip modules that are not installed.
     */
    public function test_sources_available(): void {
        $sources = registry::instance()->get_content_sources();
        $this->assertArrayHasKey('mod_page', $sources);
        $this->assertArrayHasKey('question', $sources);
        $missing = new \local_auditenhance\local\content\source\module_field('doesnotexist', ['intro']);
        $this->assertFalse($missing->is_available());
    }

    /**
     * When Brickfield has results, mapped checks read them instead of scanning.
     */
    public function test_brickfield_results(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        $scanner = new content_scanner();
        $checks = registry::instance()->get_checks(['accessibility']);
        $contentchecks = array_filter($checks, fn($c) => $c instanceof \local_auditenhance\local\content\content_check);
        $this->assertCount(count($contentchecks), $scanner->filter_checks($contentchecks));

        set_config('enableaccessibilitytools', 1);
        $checkid = $DB->get_field('tool_brickfield_checks', 'id', ['shortname' => 'img_has_alt']);
        if (!$checkid) {
            $checkid = $DB->insert_record('tool_brickfield_checks', (object)['checktype' => 'image',
                'shortname' => 'img_has_alt', 'checkgroup' => 1, 'status' => 1, 'severity' => 1]);
        }
        $areaid = $DB->insert_record('tool_brickfield_areas', (object)['type' => 0, 'contextid' => $coursecontext->id,
            'component' => 'core_course', 'tablename' => 'course', 'fieldorarea' => 'summary', 'itemid' => $course->id,
            'courseid' => $course->id, 'categoryid' => $course->category]);
        $contentid = $DB->insert_record('tool_brickfield_content', (object)['areaid' => $areaid, 'contenthash' => 'x',
            'iscurrent' => 1, 'status' => 1, 'timecreated' => time(), 'timechecked' => time()]);
        $DB->insert_record('tool_brickfield_results', (object)['contentid' => $contentid, 'checkid' => $checkid,
            'errorcount' => 2]);
        brickfield::reset();
        $this->assertTrue(brickfield::in_use());

        // Mapped checks are no longer scanned; media has no Brickfield equivalent and still is.
        $remaining = array_map(fn($c) => $c->get_id(), $scanner->filter_checks($contentchecks));
        $this->assertSame(['media'], array_values($remaining));

        $run = run_manager::create_run(['accessibility'], 'test');
        run_manager::execute((int)$run->id);
        $items = items::get_for_course((int)$run->id, (int)$course->id, 'local_auditenhance_imagealt');
        $this->assertCount(1, $items);
        $this->assertSame('bf_img_has_alt', $items[0]->issue);
        $this->assertSame(2, items::detail($items[0])['count']);
    }

    /**
     * The scanner walks every source until it is done.
     */
    public function test_cursor(): void {
        $this->resetAfterTest();
        $scanner = new content_scanner();
        $this->assertSame(0.0, (float)$scanner->get_progress(null));
        $checks = array_filter(
            registry::instance()->get_checks(['accessibility']),
            fn($c) => $c instanceof \local_auditenhance\local\content\content_check
        );
        $context = run_context::live();
        $cursor = null;
        $batches = 0;
        do {
            $cursor = $scanner->process_batch($context, $checks, $cursor);
            $batches++;
        } while ($cursor !== null && $batches < 100);
        $this->assertNull($cursor);
        $this->assertGreaterThanOrEqual(count(registry::instance()->get_content_sources()), $batches);
    }
}
