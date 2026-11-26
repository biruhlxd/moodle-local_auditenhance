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

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider.
 *
 * User ids are stored on mutes and runs (who did it, system context) and on items (who owns the
 * content, in the content's context). Mutes and runs are kept but made anonymous on deletion,
 * because they describe the site rather than the user; items are deleted.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_auditenhance_mute', [
            'checkname' => 'privacy:metadata:mute:checkname',
            'reason' => 'privacy:metadata:mute:reason',
            'userid' => 'privacy:metadata:mute:userid',
            'timecreated' => 'privacy:metadata:mute:timecreated',
        ], 'privacy:metadata:mute');
        $collection->add_database_table('local_auditenhance_itemmute', [
            'reason' => 'privacy:metadata:itemmute:reason',
            'userid' => 'privacy:metadata:itemmute:userid',
            'timecreated' => 'privacy:metadata:itemmute:timecreated',
        ], 'privacy:metadata:itemmute');
        $collection->add_database_table('local_auditenhance_run', [
            'userid' => 'privacy:metadata:run:userid',
            'timecreated' => 'privacy:metadata:run:timecreated',
        ], 'privacy:metadata:run');
        $collection->add_database_table('local_auditenhance_item', [
            'userid' => 'privacy:metadata:item:userid',
            'checkname' => 'privacy:metadata:item:checkname',
            'issue' => 'privacy:metadata:item:issue',
            'detail' => 'privacy:metadata:item:detail',
            'timecreated' => 'privacy:metadata:item:timecreated',
        ], 'privacy:metadata:item');
        return $collection;
    }

    /**
     * Contexts with data of a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if (
            $DB->record_exists('local_auditenhance_itemmute', ['userid' => $userid])
            || $DB->record_exists('local_auditenhance_mute', ['userid' => $userid])
                || $DB->record_exists('local_auditenhance_run', ['userid' => $userid])
        ) {
            $contextlist->add_system_context();
        }
        $sql = "SELECT DISTINCT ctx.id
                  FROM {local_auditenhance_item} i
                  JOIN {context} ctx ON ctx.id = i.contextid
                 WHERE i.userid = :userid";
        $contextlist->add_from_sql($sql, ['userid' => $userid]);
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof \context_system) {
            $userlist->add_from_sql('userid', "SELECT userid FROM {local_auditenhance_mute} WHERE userid > 0", []);
            $userlist->add_from_sql('userid', "SELECT userid FROM {local_auditenhance_run} WHERE userid > 0", []);
            $userlist->add_from_sql('userid', "SELECT userid FROM {local_auditenhance_itemmute} WHERE userid > 0", []);
        }
        $userlist->add_from_sql(
            'userid',
            "SELECT userid FROM {local_auditenhance_item} WHERE contextid = :contextid AND userid > 0",
            ['contextid' => $context->id]
        );
    }

    /**
     * Export a user's data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        $subcontext = [get_string('pluginname', 'local_auditenhance')];
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                $mutes = [];
                foreach ($DB->get_records('local_auditenhance_mute', ['userid' => $userid], 'id') as $mute) {
                    $mutes[] = [
                        'checkname' => $mute->checkname,
                        'reason' => $mute->reason,
                        'timecreated' => transform::datetime($mute->timecreated),
                    ];
                }
                $runs = [];
                foreach ($DB->get_records('local_auditenhance_run', ['userid' => $userid], 'id') as $run) {
                    $runs[] = [
                        'id' => $run->id,
                        'status' => $run->status,
                        'timecreated' => transform::datetime($run->timecreated),
                    ];
                }
                foreach ($DB->get_records('local_auditenhance_itemmute', ['userid' => $userid], 'id') as $mute) {
                    $mutes[] = [
                        'checkname' => $mute->checkname,
                        'item' => $mute->label,
                        'reason' => $mute->reason,
                        'timecreated' => transform::datetime($mute->timecreated),
                    ];
                }
                if ($mutes || $runs) {
                    writer::with_context($context)->export_data($subcontext, (object)['mutes' => $mutes, 'runs' => $runs]);
                }
            }
            $items = [];
            $records = $DB->get_records('local_auditenhance_item', ['userid' => $userid, 'contextid' => $context->id], 'id');
            foreach ($records as $item) {
                $items[] = [
                    'checkname' => $item->checkname,
                    'issue' => $item->issue,
                    'field' => $item->field,
                    'detail' => $item->detail,
                    'timecreated' => transform::datetime($item->timecreated),
                ];
            }
            if ($items) {
                writer::with_context($context)->export_related_data($subcontext, 'items', (object)['items' => $items]);
            }
        }
    }

    /**
     * Delete data of all users in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context instanceof \context_system) {
            $DB->set_field('local_auditenhance_mute', 'userid', 0);
            $DB->set_field('local_auditenhance_itemmute', 'userid', 0);
            $DB->set_field('local_auditenhance_run', 'userid', 0);
        }
        $DB->delete_records_select(
            'local_auditenhance_item',
            'contextid = :contextid AND userid > 0',
            ['contextid' => $context->id]
        );
    }

    /**
     * Delete data of one user in some contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                $DB->set_field('local_auditenhance_mute', 'userid', 0, ['userid' => $userid]);
                $DB->set_field('local_auditenhance_itemmute', 'userid', 0, ['userid' => $userid]);
                $DB->set_field('local_auditenhance_run', 'userid', 0, ['userid' => $userid]);
            }
            $DB->delete_records('local_auditenhance_item', ['userid' => $userid, 'contextid' => $context->id]);
        }
    }

    /**
     * Delete data of several users in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        if ($context instanceof \context_system) {
            $DB->set_field_select('local_auditenhance_mute', 'userid', 0, "userid $insql", $params);
            $DB->set_field_select('local_auditenhance_itemmute', 'userid', 0, "userid $insql", $params);
            $DB->set_field_select('local_auditenhance_run', 'userid', 0, "userid $insql", $params);
        }
        $params['contextid'] = $context->id;
        $DB->delete_records_select('local_auditenhance_item', "contextid = :contextid AND userid $insql", $params);
    }
}
