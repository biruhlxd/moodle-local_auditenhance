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

/**
 * Tests for mutes.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(mutes::class)]
final class mutes_test extends \advanced_testcase {
    /**
     * Mute and unmute a check, with events.
     */
    public function test_mute_unmute(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $sink = $this->redirectEvents();

        $this->assertFalse(mutes::is_muted('local_auditenhance_x'));
        mutes::mute('local_auditenhance_x', 'Not relevant');
        $this->assertTrue(mutes::is_muted('local_auditenhance_x'));
        $this->assertSame(['local_auditenhance_x'], mutes::get_muted_refs());

        // Muting again updates the reason.
        mutes::mute('local_auditenhance_x', 'Still not relevant');
        $all = mutes::get_all();
        $this->assertCount(1, $all);
        $this->assertSame('Still not relevant', $all['local_auditenhance_x']->reason);

        mutes::unmute('local_auditenhance_x');
        $this->assertFalse(mutes::is_muted('local_auditenhance_x'));
        mutes::unmute('local_auditenhance_x');

        $events = $sink->get_events();
        $this->assertCount(3, $events);
        $this->assertInstanceOf(\local_auditenhance\event\check_muted::class, $events[0]);
        $this->assertInstanceOf(\local_auditenhance\event\check_unmuted::class, $events[2]);
        $this->assertStringContainsString('local_auditenhance_x', $events[0]->get_description());
    }
}
