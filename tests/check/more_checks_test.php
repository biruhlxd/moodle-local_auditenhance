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

namespace local_auditenhance\check;

use local_auditenhance\fixture_plugininfo;
use local_auditenhance\local\content\content_item;
use local_auditenhance\local\content\html;
use local_auditenhance\local\finding;
use local_auditenhance\local\plugins\scanned_plugin;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/fixture_plugininfo.php');

/**
 * Tests for the checks added in 1.2.0.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(security\mfa::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(security\webservicetokens::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(security\phpversion::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(performance\diskspace::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(hygiene\emptycategories::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(hygiene\noenrolments::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(pluginrisk\staleplugins::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(accessibility\iframetitles::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(accessibility\autoplay::class)]
final class more_checks_test extends \advanced_testcase {
    /**
     * Evaluate a check.
     *
     * @param base $check
     * @return finding
     */
    private function run_check(base $check): finding {
        return $check->evaluate(run_context::live());
    }

    /**
     * Issues a content check finds in some HTML.
     *
     * @param \local_auditenhance\local\content\content_check_base $check
     * @param string $html
     * @return string[]
     */
    private function issues($check, string $html): array {
        $context = run_context::live();
        $check->analyse($context, new content_item($html, 1, 2, 'mod_page', 3, 'content', 'Page'), html::parse($html));
        return array_map(fn($i) => $i->issue, $context->get_items($check->get_ref()));
    }

    /**
     * Every new check has its strings and is registered.
     */
    public function test_strings(): void {
        foreach (
            ['security\mfa', 'security\webservicetokens', 'security\phpversion', 'performance\diskspace',
                'hygiene\emptycategories', 'hygiene\noenrolments', 'pluginrisk\staleplugins', 'accessibility\iframetitles',
                'accessibility\autoplay'] as $class
        ) {
            $classname = '\local_auditenhance\check\\' . $class;
            $check = new $classname();
            $this->assertNotEmpty($check->get_explanation(), $class);
            $this->assertNotEmpty($check->get_fix(), $class);
            $this->assertNotNull(\local_auditenhance\local\registry::instance()->get_check($check->get_ref()), $class);
        }
    }

    /**
     * Multi-factor authentication.
     */
    public function test_mfa(): void {
        $this->resetAfterTest();
        set_config('enabled', 0, 'tool_mfa');
        $this->assertSame(severity::HIGH, $this->run_check(new security\mfa())->severity);
        set_config('enabled', 1, 'tool_mfa');
        foreach (security\mfa::STRONG_FACTORS as $factor) {
            set_config('enabled', 0, 'factor_' . $factor);
        }
        $this->assertSame(severity::MEDIUM, $this->run_check(new security\mfa())->severity);
        set_config('enabled', 1, 'factor_totp');
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new security\mfa())->status);
    }

    /**
     * Web service tokens.
     */
    public function test_webservicetokens(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enablewebservices', 0);
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new security\webservicetokens())->status);
        set_config('enablewebservices', 1);
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new security\webservicetokens())->status);
        $token = ['token' => 'a', 'tokentype' => EXTERNAL_TOKEN_PERMANENT, 'userid' => get_admin()->id,
            'externalserviceid' => 1, 'contextid' => 1, 'creatorid' => 2, 'validuntil' => 0, 'timecreated' => time(),
            'lastaccess' => time(), 'name' => 'Integration'];
        $DB->insert_record('external_tokens', (object)$token);
        $finding = $this->run_check(new security\webservicetokens());
        $this->assertSame(severity::MEDIUM, $finding->severity);
        $DB->set_field('external_tokens', 'validuntil', time() + DAYSECS);
        $DB->set_field('external_tokens', 'lastaccess', time() - 800 * DAYSECS);
        $DB->set_field('external_tokens', 'timecreated', time() - 800 * DAYSECS);
        $this->assertSame(severity::LOW, $this->run_check(new security\webservicetokens())->severity);
    }

    /**
     * PHP version support.
     */
    public function test_phpversion(): void {
        $this->assertSame('ended', security\phpversion::get_status('8.1.2', strtotime('2026-06-01')));
        $this->assertSame('ending', security\phpversion::get_status('8.2.10', strtotime('2026-11-01')));
        $this->assertSame('supported', security\phpversion::get_status('8.3.6', strtotime('2026-11-01')));
        $this->assertSame('newer', security\phpversion::get_status('9.0.0', strtotime('2026-11-01')));
        $this->assertSame('ended', security\phpversion::get_status('7.4.33', strtotime('2026-11-01')));
        $this->assertNotSame(finding::STATUS_ERROR, $this->run_check(new security\phpversion())->status);
    }

    /**
     * Disk space.
     */
    public function test_diskspace(): void {
        $this->assertNull(performance\diskspace::severity_for(50, 20));
        $this->assertSame(severity::MEDIUM, performance\diskspace::severity_for(15, 20));
        $this->assertSame(severity::HIGH, performance\diskspace::severity_for(8, 20));
        $this->assertSame(severity::CRITICAL, performance\diskspace::severity_for(3, 20));
        $this->assertNotSame(finding::STATUS_ERROR, $this->run_check(new performance\diskspace())->status);
    }

    /**
     * Empty categories and courses without enrolments.
     */
    public function test_hygiene(): void {
        global $DB;
        $this->resetAfterTest();
        // A course in the default category, so that only the new category is empty.
        $this->getDataGenerator()->create_course();
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new hygiene\emptycategories())->status);
        $this->getDataGenerator()->create_category(['name' => 'Empty one']);
        $finding = $this->run_check(new hygiene\emptycategories());
        $this->assertSame(1, $finding->itemcount);
        $this->assertSame('Empty one', $finding->details['sample'][0]['label']);

        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'timecreated', time() - 100 * DAYSECS, ['id' => $course->id]);
        $this->assertSame(1, $this->run_check(new hygiene\noenrolments())->itemcount);
        $this->getDataGenerator()->enrol_user($this->getDataGenerator()->create_user()->id, $course->id);
        $this->assertSame(finding::STATUS_PASS, $this->run_check(new hygiene\noenrolments())->status);
    }

    /**
     * Old plugin versions.
     */
    public function test_staleplugins(): void {
        $this->resetAfterTest();
        $this->assertSame(mktime(0, 0, 0, 1, 1, 2026), pluginrisk\staleplugins::version_date(2026010100));
        $this->assertNull(pluginrisk\staleplugins::version_date(12345));
        $this->assertNull(pluginrisk\staleplugins::version_date(2026139900));
        $context = run_context::live();
        $plugin = new scanned_plugin(fixture_plugininfo::make('riskyfixture'));
        (new pluginrisk\staleplugins())->analyse_plugin($context, $plugin);
        $this->assertSame(0, $context->count_items('local_auditenhance_staleplugins'));
        set_config('staleyears', 0, 'local_auditenhance');
        (new pluginrisk\staleplugins())->analyse_plugin($context, $plugin);
        $this->assertSame(1, $context->count_items('local_auditenhance_staleplugins'));
    }

    /**
     * Frame titles and autoplay.
     */
    public function test_media_content(): void {
        $this->assertSame(['notitle'], $this->issues(new accessibility\iframetitles(), '<iframe src="x"></iframe>'));
        $this->assertSame([], $this->issues(new accessibility\iframetitles(), '<iframe src="x" title="Map"></iframe>'));
        $this->assertSame(['autoplay'], $this->issues(new accessibility\autoplay(), '<video src="a.mp4" autoplay></video>'));
        $this->assertSame([], $this->issues(new accessibility\autoplay(), '<video src="a.mp4" autoplay muted></video>'));
        $this->assertSame(['autoplay'], $this->issues(new accessibility\autoplay(), '<audio src="a.mp3" autoplay></audio>'));
        $this->assertSame(['autoplay'], $this->issues(
            new accessibility\autoplay(),
            '<iframe src="https://www.youtube.com/embed/x?autoplay=1"></iframe>'
        ));
        $this->assertSame([], $this->issues(
            new accessibility\autoplay(),
            '<iframe src="https://www.youtube.com/embed/x?autoplay=1&mute=1"></iframe>'
        ));
    }
}
