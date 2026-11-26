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
 * Tests for muting single items or all items of a component.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(item_mutes::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_auditenhance\event\item_muted::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_auditenhance\event\item_unmuted::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_auditenhance\output\mutes_page::class)]
final class item_mutes_test extends \advanced_testcase {
    use \local_auditenhance\test_registry_helper;

    /**
     * Reset caches.
     */
    protected function tearDown(): void {
        registry::reset();
        item_mutes::reset();
        parent::tearDown();
    }

    /**
     * Run an audit and return the finding of the scanned test check.
     *
     * @return finding
     */
    private function audit(): finding {
        $run = run_manager::create_run(null, 'test');
        run_manager::execute((int)$run->id);
        return results::get_for_run((int)$run->id)['local_auditenhance_scanned'];
    }

    /**
     * Muting one item, a whole component, and unmuting.
     */
    public function test_mute_items(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->make_test_registry(false);
        $sink = $this->redirectEvents();

        $finding = $this->audit();
        $this->assertSame(10, $finding->itemcount);
        $item = $DB->get_record('local_auditenhance_item', ['checkname' => 'local_auditenhance_scanned', 'itemid' => 19]);
        $this->assertSame(40, strlen($item->itemkey));

        $muteid = item_mutes::mute($item, false, 'Known and accepted');
        $this->assertTrue(item_mutes::is_muted('local_auditenhance_scanned', $item->itemkey, 'core'));
        $finding = $this->audit();
        $this->assertSame(9, $finding->itemcount);
        $this->assertSame(1, $finding->details['muteditems']);
        // Item 17 is still medium.
        $this->assertSame(severity::MEDIUM, $finding->severity);

        item_mutes::mute($item, true, 'Core is reviewed elsewhere');
        $finding = $this->audit();
        $this->assertSame(finding::STATUS_PASS, $finding->status);
        $this->assertSame(10, $finding->details['muteditems']);

        $page = new \local_auditenhance\output\mutes_page();
        $data = $page->export_for_template($PAGE->get_renderer('local_auditenhance'));
        $this->assertCount(2, $data['itemmutes']);
        $this->assertSame(get_string('muteditemsof', 'local_auditenhance', 'core'), $data['itemmutes'][0]['label']);

        foreach (item_mutes::get_all() as $mute) {
            item_mutes::unmute((int)$mute->id);
        }
        $this->assertSame(10, $this->audit()->itemcount);
        item_mutes::unmute($muteid);

        $events = array_map(fn($e) => get_class($e), $sink->get_events());
        $this->assertContains(\local_auditenhance\event\item_muted::class, $events);
        $this->assertContains(\local_auditenhance\event\item_unmuted::class, $events);
    }

    /**
     * Item keys are stable across runs and differ per item.
     */
    public function test_item_key(): void {
        $a = new item('missingalt', severity::LOW, 5, 2, 'mod_page', 7, 'content', 0, ['snippet' => '<img src="a.png">']);
        $b = new item('missingalt', severity::LOW, 5, 2, 'mod_page', 7, 'content', 0, ['snippet' => '<img src="b.png">']);
        $this->assertSame($a->get_key('x'), (clone $a)->get_key('x'));
        $this->assertNotSame($a->get_key('x'), $b->get_key('x'));
        $code = new item('eval', severity::HIGH, 1, 0, 'local_x', 0, 'lib.php', 0, ['file' => 'lib.php', 'line' => 3]);
        $moved = new item('eval', severity::HIGH, 1, 0, 'local_x', 0, 'lib.php', 0, ['file' => 'lib.php', 'line' => 9]);
        $this->assertSame($code->get_key('x'), $moved->get_key('x'));
    }
}
