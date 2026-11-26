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

namespace local_auditenhance\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Tests for the privacy provider.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends provider_testcase {
    /** @var \stdClass User with data. */
    private \stdClass $user;

    /** @var \stdClass Another user with data. */
    private \stdClass $other;

    /** @var \context_course Course context with items. */
    private \context_course $coursecontext;

    /**
     * Create data for two users.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->user = $this->getDataGenerator()->create_user();
        $this->other = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->coursecontext = \context_course::instance($course->id);
        foreach ([$this->user, $this->other] as $user) {
            $DB->insert_record('local_auditenhance_mute', (object)['checkname' => 'x_' . $user->id, 'reason' => 'r',
                'userid' => $user->id, 'timecreated' => time()]);
            $runid = $DB->insert_record('local_auditenhance_run', (object)['userid' => $user->id, 'status' => 'complete',
                'timecreated' => time()]);
            $DB->insert_record('local_auditenhance_item', (object)['runid' => $runid, 'checkname' => 'c', 'area' => 'a',
                'contextid' => $this->coursecontext->id, 'courseid' => $course->id, 'issue' => 'i',
                'userid' => $user->id, 'timecreated' => time()]);
        }
    }

    /**
     * Metadata lists the tables.
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('local_auditenhance'));
        $this->assertCount(4, $collection->get_collection());
    }

    /**
     * Contexts of a user.
     */
    public function test_get_contexts_for_userid(): void {
        $contexts = provider::get_contexts_for_userid($this->user->id)->get_contextids();
        sort($contexts);
        $expected = [\context_system::instance()->id, $this->coursecontext->id];
        sort($expected);
        $this->assertEquals($expected, $contexts);
    }

    /**
     * Users in a context.
     */
    public function test_get_users_in_context(): void {
        $userlist = new userlist(\context_system::instance(), 'local_auditenhance');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->user->id, $this->other->id], $userlist->get_userids());

        $userlist = new userlist($this->coursecontext, 'local_auditenhance');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->user->id, $this->other->id], $userlist->get_userids());
    }

    /**
     * Export.
     */
    public function test_export_user_data(): void {
        $contextlist = provider::get_contexts_for_userid($this->user->id);
        $approved = new approved_contextlist($this->user, 'local_auditenhance', $contextlist->get_contextids());
        provider::export_user_data($approved);
        $subcontext = [get_string('pluginname', 'local_auditenhance')];
        $data = writer::with_context(\context_system::instance())->get_data($subcontext);
        $this->assertCount(1, $data->mutes);
        $this->assertCount(1, $data->runs);
        $items = writer::with_context($this->coursecontext)->get_related_data($subcontext, 'items');
        $this->assertCount(1, $items->items);
    }

    /**
     * Delete for one user.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $approved = new approved_contextlist(
            $this->user,
            'local_auditenhance',
            [\context_system::instance()->id, $this->coursecontext->id]
        );
        provider::delete_data_for_user($approved);
        $this->assertFalse($DB->record_exists('local_auditenhance_mute', ['userid' => $this->user->id]));
        $this->assertFalse($DB->record_exists('local_auditenhance_run', ['userid' => $this->user->id]));
        $this->assertFalse($DB->record_exists('local_auditenhance_item', ['userid' => $this->user->id]));
        $this->assertTrue($DB->record_exists('local_auditenhance_item', ['userid' => $this->other->id]));
        // Mutes stay, without the user.
        $this->assertEquals(2, $DB->count_records('local_auditenhance_mute'));
    }

    /**
     * Delete for several users.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        provider::delete_data_for_users(new approved_userlist($this->coursecontext, 'local_auditenhance', [$this->user->id]));
        $this->assertFalse($DB->record_exists('local_auditenhance_item', ['userid' => $this->user->id]));
        $this->assertTrue($DB->record_exists('local_auditenhance_mute', ['userid' => $this->user->id]));
        provider::delete_data_for_users(new approved_userlist(
            \context_system::instance(),
            'local_auditenhance',
            [$this->user->id, $this->other->id]
        ));
        $this->assertEquals(0, $DB->count_records_select('local_auditenhance_mute', 'userid > 0'));
    }

    /**
     * Delete for all users in a context.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context($this->coursecontext);
        $this->assertEquals(0, $DB->count_records('local_auditenhance_item'));
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertEquals(0, $DB->count_records_select('local_auditenhance_run', 'userid > 0'));
    }
}
