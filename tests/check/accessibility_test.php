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

namespace local_auditenhance\check\accessibility;

use local_auditenhance\local\content\content_item;
use local_auditenhance\local\content\html;
use local_auditenhance\local\finding;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Tests for the accessibility checks.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(imagealt::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(headings::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(linktext::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(tables::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(contrast::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(media::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(documents::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(htmllang::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(statement::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(themecontrast::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_auditenhance\local\content\content_check_base::class)]
final class accessibility_test extends \advanced_testcase {
    /**
     * Run a content check on some HTML.
     *
     * @param \local_auditenhance\local\content\content_check_base $check
     * @param string $html
     * @return array{0: run_context, 1: \stdClass[]} The context and the items found.
     */
    private function analyse($check, string $html): array {
        $context = run_context::live();
        $item = new content_item($html, 1, 2, 'mod_page', 3, 'content', 'Page: Test', 'https://example.com/page');
        $check->analyse($context, $item, html::parse($html));
        return [$context, $context->get_items($check->get_ref())];
    }

    /**
     * Issues of the items found.
     *
     * @param \local_auditenhance\local\content\content_check_base $check
     * @param string $html
     * @return string[]
     */
    private function issues($check, string $html): array {
        return array_map(fn($i) => $i->issue, $this->analyse($check, $html)[1]);
    }

    /**
     * Every check has its strings and belongs to the accessibility area.
     */
    public function test_strings_and_area(): void {
        $checks = [new imagealt(), new headings(), new linktext(), new tables(), new contrast(), new media(),
            new documents(), new htmllang(), new statement(), new themecontrast()];
        foreach ($checks as $check) {
            $this->assertSame('accessibility', $check->get_area());
            $this->assertNotEmpty($check->get_explanation(), $check->get_id());
            $this->assertNotEmpty($check->get_fix(), $check->get_id());
        }
    }

    /**
     * Images.
     */
    public function test_imagealt(): void {
        $check = new imagealt();
        $this->assertSame(['missingalt'], $this->issues($check, '<img src="a.png">'));
        $this->assertSame(['filenamealt'], $this->issues($check, '<img src="/x/photo.jpg" alt="photo.jpg">'));
        $this->assertSame(['filenamealt'], $this->issues($check, '<img src="b.png" alt="Image">'));
        $this->assertSame(['emptyaltlink'], $this->issues($check, '<a href="#"><img src="b.png" alt=""></a>'));
        $this->assertSame([], $this->issues($check, '<img src="b.png" alt="">'));
        $this->assertSame([], $this->issues($check, '<img src="b.png" alt="A graph of sales">'));
        $this->assertSame([], $this->issues($check, '<img src="b.png" role="presentation">'));
        [$context, $items] = $this->analyse($check, '<p><img src="a.png"></p>');
        $this->assertSame(severity::MEDIUM, $items[0]->severity);
        $detail = json_decode($items[0]->detail, true);
        $this->assertStringContainsString('Page: Test', $detail['label']);
        $this->assertStringContainsString('<img', $detail['snippet']);
        $this->assertSame(2, (int)$items[0]->courseid);
    }

    /**
     * Headings.
     */
    public function test_headings(): void {
        $check = new headings();
        $this->assertSame([], $this->issues($check, '<h3>A</h3><h4>B</h4><h3>C</h3>'));
        $this->assertSame(['skippedheading'], $this->issues($check, '<h2>A</h2><h4>B</h4>'));
        $this->assertSame(['emptyheading'], $this->issues($check, '<h3> </h3>'));
        $this->assertSame(['fakeheading'], $this->issues($check, '<p><strong>Week 1</strong></p>'));
        $this->assertSame(['fakeheading'], $this->issues($check, '<p style="font-size: 24px">Week 1</p>'));
        $this->assertSame([], $this->issues($check, '<p><strong>Note:</strong> read this first.</p>'));
        $this->assertSame([], $this->issues($check, '<p><strong>This is a whole sentence in bold.</strong></p>'));
    }

    /**
     * Links.
     */
    public function test_linktext(): void {
        $check = new linktext();
        $this->assertSame(['vaguelink'], $this->issues($check, '<a href="/x">Click here</a>'));
        $this->assertSame(['vaguelink'], $this->issues($check, '<a href="/x">Read more...</a>'));
        $this->assertSame(['urllink'], $this->issues($check, '<a href="https://example.com/a">https://example.com/a</a>'));
        $this->assertSame(['emptylink'], $this->issues($check, '<a href="/x"><img src="a.png" alt=""></a>'));
        $this->assertSame([], $this->issues($check, '<a href="/x"><img src="a.png" alt="Home"></a>'));
        $this->assertSame([], $this->issues($check, '<a href="/x">Week 3 reading list</a>'));
        $this->assertSame([], $this->issues($check, '<a name="anchor"></a>'));
    }

    /**
     * Tables.
     */
    public function test_tables(): void {
        $check = new tables();
        $this->assertSame(['noheaders'], $this->issues($check, '<table><tr><td>1</td></tr></table>'));
        $this->assertSame(['nocaption'], $this->issues($check, '<table><tr><th>A</th></tr><tr><td>1</td></tr></table>'));
        $this->assertSame([], $this->issues(
            $check,
            '<table><caption>Marks</caption><tr><th>A</th></tr><tr><td>1</td></tr></table>'
        ));
        $this->assertSame([], $this->issues($check, '<table role="presentation"><tr><td>1</td></tr></table>'));
    }

    /**
     * Contrast.
     */
    public function test_contrast(): void {
        $check = new contrast();
        [, $items] = $this->analyse($check, '<span style="color: #eeeeee">pale</span>');
        $this->assertSame(severity::MEDIUM, $items[0]->severity);
        [, $items] = $this->analyse($check, '<span style="color: #888888">grey</span>');
        $this->assertSame(severity::LOW, $items[0]->severity);
        $this->assertSame([], $this->issues($check, '<span style="color: #000">black</span>'));
        $this->assertSame(['lowcontrast'], $this->issues(
            $check,
            '<div style="background-color: #000"><span style="color: #333">dark on dark</span></div>'
        ));
        $this->assertSame([], $this->issues(
            $check,
            '<div style="background: #000 url(x.png)"><span style="color: #fff">white on black</span></div>'
        ));
        $this->assertSame([], $this->issues($check, '<span style="color: #eee"></span>'));
    }

    /**
     * Video and audio.
     */
    public function test_media(): void {
        $check = new media();
        $this->assertSame(['nocaptions'], $this->issues($check, '<video src="a.mp4"></video>'));
        $this->assertSame([], $this->issues($check, '<video src="a.mp4"><track kind="captions" src="a.vtt"></video>'));
        $this->assertSame(['notranscript'], $this->issues($check, '<iframe src="https://www.youtube.com/embed/x"></iframe>'));
        $this->assertSame([], $this->issues(
            $check,
            '<iframe src="https://www.youtube.com/embed/x"></iframe><a href="t.html">Transcript</a>'
        ));
        $this->assertSame(['notranscript'], $this->issues($check, '<a href="https://example.com/talk.mp4">Talk</a>'));
        $this->assertSame([], $this->issues($check, '<iframe src="https://example.com/map"></iframe>'));
    }

    /**
     * A finding is built from the items.
     */
    public function test_finding_from_items(): void {
        $check = new imagealt();
        [$context] = $this->analyse($check, '<img src="a.png"><img src="b.png" alt="b.png">');
        $finding = $check->evaluate($context);
        $this->assertSame(2, $finding->itemcount);
        $this->assertSame(severity::MEDIUM, $finding->severity);
        $this->assertCount(2, $finding->details['sample']);
        [$context] = $this->analyse($check, '<p>No images</p>');
        $this->assertSame(finding::STATUS_PASS, $check->evaluate($context)->status);
    }

    /**
     * Documents for manual review.
     */
    public function test_documents(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $check = new documents();
        $run = \local_auditenhance\local\run_manager::create_run();
        $context = new run_context($run);
        $this->assertSame(finding::STATUS_PASS, $check->evaluate($context)->status);

        $course = $this->getDataGenerator()->create_course();
        $resource = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $cmcontext = \context_module::instance($resource->cmid);
        get_file_storage()->create_file_from_string(['contextid' => $cmcontext->id, 'component' => 'mod_resource',
            'filearea' => 'content', 'itemid' => 0, 'filepath' => '/', 'filename' => 'notes.pdf',
            'mimetype' => 'application/pdf'], '%PDF-1.4');
        $context = new run_context($run);
        $finding = $check->evaluate($context);
        $this->assertSame(severity::INFO, $finding->severity);
        $this->assertTrue($finding->is_failure());
        $items = $context->get_items($check->get_ref());
        $pdf = array_values(array_filter($items, fn($i) => str_contains($i->detail, 'notes.pdf')));
        $this->assertCount(1, $pdf);
        $this->assertEquals($course->id, $pdf[0]->courseid);
    }

    /**
     * Site language.
     */
    public function test_htmllang(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->assertSame(finding::STATUS_PASS, (new htmllang())->evaluate(run_context::live())->status);
        $CFG->lang = 'xx';
        $this->assertSame(severity::MEDIUM, (new htmllang())->evaluate(run_context::live())->severity);
    }

    /**
     * Accessibility statement.
     */
    public function test_statement(): void {
        $this->resetAfterTest();
        set_config('custommenuitems', '');
        $this->assertSame(severity::LOW, (new statement())->evaluate(run_context::live())->severity);
        set_config('custommenuitems', "Accessibility statement|/local/statement.php");
        $this->assertSame(finding::STATUS_PASS, (new statement())->evaluate(run_context::live())->status);
        set_config('custommenuitems', '');
        set_config('accessibilitystatementurl', 'https://example.com/a11y', 'local_auditenhance');
        $this->assertSame(finding::STATUS_PASS, (new statement())->evaluate(run_context::live())->status);
    }

    /**
     * Theme brand colour.
     */
    public function test_themecontrast(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->theme = 'boost';
        set_config('brandcolor', '', 'theme_boost');
        $this->assertSame(finding::STATUS_PASS, (new themecontrast())->evaluate(run_context::live())->status);
        set_config('brandcolor', '#ffcc00', 'theme_boost');
        $this->assertSame(severity::MEDIUM, (new themecontrast())->evaluate(run_context::live())->severity);
        set_config('brandcolor', '#0f47ad', 'theme_boost');
        $this->assertSame(finding::STATUS_PASS, (new themecontrast())->evaluate(run_context::live())->status);
        set_config('brandcolor', 'nonsense', 'theme_boost');
        $this->assertSame(finding::STATUS_SKIP, (new themecontrast())->evaluate(run_context::live())->status);
    }
}
