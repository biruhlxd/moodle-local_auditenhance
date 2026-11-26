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

use local_auditenhance\check\base;

/**
 * Creates, runs and stores audit runs.
 *
 * A run goes through two phases: scanners walk content and code in batches, then every check is
 * evaluated. The state is saved on the run so it can continue in a later task execution.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class run_manager {
    /** @var string Waiting for the task. */
    public const STATUS_QUEUED = 'queued';
    /** @var string Running. */
    public const STATUS_RUNNING = 'running';
    /** @var string Finished. */
    public const STATUS_COMPLETE = 'complete';
    /** @var string Stopped with an error. */
    public const STATUS_FAILED = 'failed';

    /** @var int A queued or running run untouched for this long is treated as stuck. */
    public const STALE_AFTER = DAYSECS;

    /** @var int Share of the progress bar used by scanners. */
    private const SCAN_SHARE = 80;

    /**
     * Create a run record.
     *
     * @param string[]|null $areas Area ids, null or empty for all.
     * @param string $origin Where the run was started.
     * @param int|null $userid Defaults to the current user.
     * @return \stdClass
     */
    public static function create_run(?array $areas = null, string $origin = 'web', ?int $userid = null): \stdClass {
        global $DB, $USER;
        $areas = self::clean_areas($areas);
        $now = time();
        $run = (object)[
            'userid' => $userid ?? (int)($USER->id ?? 0),
            'origin' => \core_text::substr($origin, 0, 20),
            'areas' => $areas ? implode(',', $areas) : '',
            'status' => self::STATUS_QUEUED,
            'progress' => 0,
            'score' => null,
            'areascores' => null,
            'state' => null,
            'timecreated' => $now,
            'started' => 0,
            'finished' => 0,
            'timemodified' => $now,
        ];
        $run->id = $DB->insert_record('local_auditenhance_run', $run);
        return $run;
    }

    /**
     * Queue a run for the ad hoc task, unless one is already waiting or running.
     *
     * @param string[]|null $areas Area ids, null or empty for all.
     * @param string $origin
     * @param int|null $userid
     * @return int Run id (existing or new).
     */
    public static function queue(?array $areas = null, string $origin = 'web', ?int $userid = null): int {
        if ($active = self::get_active_run()) {
            return (int)$active->id;
        }
        $run = self::create_run($areas, $origin, $userid);
        self::queue_task((int)$run->id);
        return (int)$run->id;
    }

    /**
     * Queue the ad hoc task for a run.
     *
     * @param int $runid
     */
    public static function queue_task(int $runid): void {
        $task = new \local_auditenhance\task\run_audit();
        $task->set_custom_data(['runid' => $runid]);
        $task->set_component('local_auditenhance');
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Queued or running run that is not stuck, if any.
     *
     * @return \stdClass|null
     */
    public static function get_active_run(): ?\stdClass {
        global $DB;
        $select = '(status = :queued OR status = :running) AND timemodified > :since';
        $runs = $DB->get_records_select('local_auditenhance_run', $select, [
            'queued' => self::STATUS_QUEUED,
            'running' => self::STATUS_RUNNING,
            'since' => time() - self::STALE_AFTER,
        ], 'id DESC', '*', 0, 1);
        return $runs ? reset($runs) : null;
    }

    /**
     * A run by id.
     *
     * @param int $runid
     * @return \stdClass|null
     */
    public static function get_run(int $runid): ?\stdClass {
        global $DB;
        return $DB->get_record('local_auditenhance_run', ['id' => $runid]) ?: null;
    }

    /**
     * Latest complete run, optionally only full runs (all areas).
     *
     * @param bool $fullonly
     * @return \stdClass|null
     */
    public static function get_latest_complete_run(bool $fullonly = false): ?\stdClass {
        global $DB;
        $select = 'status = :status';
        if ($fullonly) {
            $select .= ' AND ' . $DB->sql_isempty('local_auditenhance_run', 'areas', true, true);
        }
        $runs = $DB->get_records_select(
            'local_auditenhance_run',
            $select,
            ['status' => self::STATUS_COMPLETE],
            'finished DESC, id DESC',
            '*',
            0,
            1
        );
        return $runs ? reset($runs) : null;
    }

    /**
     * Area ids of a run.
     *
     * @param \stdClass $run
     * @return string[] Empty for all areas.
     */
    public static function get_run_areas(\stdClass $run): array {
        return $run->areas === '' || $run->areas === null ? [] : explode(',', $run->areas);
    }

    /**
     * Run (or continue) a run.
     *
     * @param int $runid
     * @param int $timebudget Seconds before stopping to continue later, 0 for no limit.
     * @param callable|null $progress Called with (int $percent, string $message).
     * @param int $maxsteps Stop after this many scanner batches and check evaluations, 0 for no limit.
     * @return bool True when the run is finished.
     */
    public static function execute(int $runid, int $timebudget = 0, ?callable $progress = null, int $maxsteps = 0): bool {
        global $DB;

        $run = self::get_run($runid);
        if (!$run) {
            return true;
        }
        if ($run->status === self::STATUS_COMPLETE || $run->status === self::STATUS_FAILED) {
            return true;
        }

        $state = json_decode($run->state ?? '', true) ?: [];
        if ($run->status === self::STATUS_QUEUED) {
            $run->status = self::STATUS_RUNNING;
            $run->started = time();
            $run->timemodified = time();
            $DB->update_record('local_auditenhance_run', $run);
            \local_auditenhance\event\audit_started::create([
                'context' => \context_system::instance(),
                'objectid' => $run->id,
                'relateduserid' => $run->userid ?: null,
            ])->trigger();
        }

        try {
            return self::process($run, $state, $timebudget, $progress, $maxsteps);
        } catch (\Throwable $e) {
            $run->status = self::STATUS_FAILED;
            $run->finished = time();
            $run->timemodified = time();
            $state['failure'] = $e->getMessage();
            $run->state = json_encode($state);
            $DB->update_record('local_auditenhance_run', $run);
            throw $e;
        }
    }

    /**
     * Do the work of a run.
     *
     * @param \stdClass $run
     * @param array $state
     * @param int $timebudget
     * @param callable|null $progress
     * @param int $maxsteps
     * @return bool
     */
    private static function process(\stdClass $run, array $state, int $timebudget, ?callable $progress, int $maxsteps): bool {
        $deadline = $timebudget > 0 ? time() + $timebudget : 0;
        $steps = 0;
        $stop = function () use ($deadline, $maxsteps, &$steps): bool {
            $steps++;
            return ($deadline && time() >= $deadline) || ($maxsteps && $steps >= $maxsteps);
        };
        $registry = registry::instance();
        $checks = $registry->get_checks(self::get_run_areas($run) ?: null);
        $context = new run_context($run, $state['context'] ?? []);
        $state['phase'] = $state['phase'] ?? 'scan';
        $state['errors'] = $state['errors'] ?? [];

        if ($state['phase'] === 'scan') {
            $scanners = array_values($registry->get_scanners());
            $index = $state['scanner'] ?? 0;
            $cursor = $state['cursor'] ?? null;
            while ($index < count($scanners)) {
                $scanner = $scanners[$index];
                $interface = $scanner->get_check_interface();
                $fed = array_filter($checks, fn(base $c) => $c instanceof $interface);
                if (!$fed || !$scanner->is_needed($fed)) {
                    $index++;
                    $cursor = null;
                    continue;
                }
                try {
                    $cursor = $scanner->process_batch($context, $fed, $cursor);
                } catch (\Throwable $e) {
                    $state['errors'][$scanner->get_id()] = $e->getMessage();
                    $cursor = null;
                }
                $share = self::SCAN_SHARE / count($scanners);
                $percent = (int)floor($index * $share + $share * ($cursor === null ? 1 : $scanner->get_progress($cursor)));
                if ($cursor === null) {
                    $index++;
                }
                self::report_progress($run, $percent, $progress, get_string('progressscanning', 'local_auditenhance'));
                if ($stop()) {
                    $state['scanner'] = $index;
                    $state['cursor'] = $cursor;
                    self::save_state($run, $state, $context);
                    return false;
                }
            }
            $state['phase'] = 'evaluate';
            unset($state['scanner'], $state['cursor']);
        }

        $failedscanners = [];
        foreach ($registry->get_scanners() as $scanner) {
            if (isset($state['errors'][$scanner->get_id()])) {
                $failedscanners[$scanner->get_check_interface()] = $state['errors'][$scanner->get_id()];
            }
        }

        $done = array_flip($state['evaluated'] ?? []);
        $total = max(1, count($checks));
        foreach ($checks as $ref => $check) {
            if (isset($done[$ref])) {
                continue;
            }
            $finding = null;
            foreach ($failedscanners as $interface => $message) {
                if ($check instanceof $interface) {
                    $finding = finding::error(get_string('scannerfailed', 'local_auditenhance', $message));
                }
            }
            if ($finding === null) {
                try {
                    $finding = $check->evaluate($context);
                } catch (\Throwable $e) {
                    $finding = finding::error($e->getMessage());
                }
            }
            self::save_result($run, $check, $finding);
            $context->set_finding($ref, $finding);
            $done[$ref] = true;
            $percent = self::SCAN_SHARE + (int)floor((100 - self::SCAN_SHARE) * count($done) / $total);
            self::report_progress($run, min(99, $percent), $progress, $check->get_name());
            if ($stop()) {
                $state['evaluated'] = array_keys($done);
                self::save_state($run, $state, $context);
                return false;
            }
        }

        $context->flush();
        self::finish($run, $state);
        return true;
    }

    /**
     * Save a check result.
     *
     * @param \stdClass $run
     * @param base $check
     * @param finding $finding
     */
    public static function save_result(\stdClass $run, base $check, finding $finding): void {
        global $DB;
        $DB->delete_records('local_auditenhance_result', ['runid' => $run->id, 'checkname' => $check->get_ref()]);
        $DB->insert_record('local_auditenhance_result', (object)[
            'runid' => $run->id,
            'checkname' => $check->get_ref(),
            'component' => $check->get_component(),
            'area' => $check->get_area(),
            'severity' => $finding->severity,
            'status' => $finding->status,
            'summary' => $finding->summary,
            'details' => $finding->encode_details(),
            'itemcount' => $finding->itemcount,
            'timecreated' => time(),
        ]);
    }

    /**
     * Save the state between batches.
     *
     * @param \stdClass $run
     * @param array $state
     * @param run_context $context
     */
    private static function save_state(\stdClass $run, array $state, run_context $context): void {
        global $DB;
        $state['context'] = $context->export_state();
        $run->state = json_encode($state);
        $run->timemodified = time();
        $DB->update_record('local_auditenhance_run', $run);
    }

    /**
     * Update the progress of a run.
     *
     * @param \stdClass $run
     * @param int $percent
     * @param callable|null $callback
     * @param string $message
     */
    private static function report_progress(\stdClass $run, int $percent, ?callable $callback, string $message): void {
        global $DB;
        if ($percent !== (int)$run->progress) {
            $run->progress = $percent;
            $run->timemodified = time();
            $DB->update_record('local_auditenhance_run', (object)[
                'id' => $run->id,
                'progress' => $percent,
                'timemodified' => $run->timemodified,
            ]);
        }
        if ($callback) {
            $callback($percent, $message);
        }
    }

    /**
     * Compute scores, mark the run complete and tell other plugins.
     *
     * @param \stdClass $run
     * @param array $state
     */
    private static function finish(\stdClass $run, array $state): void {
        global $DB;
        $findings = results::get_for_run((int)$run->id);
        $scores = scoring::compute($findings, mutes::get_muted_refs());
        $runareas = self::get_run_areas($run);
        if ($runareas) {
            $scores['areas'] = array_intersect_key($scores['areas'], array_flip($runareas));
            $weights = [];
            foreach ($runareas as $areaid) {
                $area = registry::instance()->get_area($areaid);
                $weights[$areaid] = $area ? $area->get_weight() : 0;
            }
            $scores['overall'] = scoring::overall_score($scores['areas'], $weights);
        }
        $run->status = self::STATUS_COMPLETE;
        $run->progress = 100;
        $run->score = $scores['overall'];
        $run->areascores = json_encode($scores['areas']);
        $run->state = $state['errors'] ? json_encode(['errors' => $state['errors']]) : null;
        $run->finished = time();
        $run->timemodified = time();
        $DB->update_record('local_auditenhance_run', $run);

        results::purge_cache();
        \local_auditenhance\event\audit_completed::create([
            'context' => \context_system::instance(),
            'objectid' => $run->id,
            'relateduserid' => $run->userid ?: null,
            'other' => ['score' => $run->score, 'areas' => $run->areas],
        ])->trigger();

        self::cleanup();
    }

    /**
     * Delete old runs beyond the number to keep.
     */
    public static function cleanup(): void {
        global $DB;
        // Runs stuck in the queue for a long time are marked failed.
        $DB->set_field_select(
            'local_auditenhance_run',
            'status',
            self::STATUS_FAILED,
            '(status = ? OR status = ?) AND timemodified < ?',
            [self::STATUS_QUEUED, self::STATUS_RUNNING, time() - self::STALE_AFTER]
        );

        $keep = (int)get_config('local_auditenhance', 'keepruns');
        if ($keep > 0) {
            $old = $DB->get_fieldset_select(
                'local_auditenhance_run',
                'id',
                'status = ? OR status = ?',
                [self::STATUS_COMPLETE, self::STATUS_FAILED]
            );
            rsort($old);
            foreach (array_slice($old, $keep) as $runid) {
                self::delete_run((int)$runid);
            }
        }
        self::cleanup_items();
    }

    /**
     * Delete the items of older runs. Results (scores and findings) are kept for history; items are only kept
     * for the latest runs of each area, because they are the bulk of the stored data.
     */
    public static function cleanup_items(): void {
        global $DB;
        $keep = get_config('local_auditenhance', 'keepitemruns');
        $keep = ($keep === false || $keep === '') ? 5 : (int)$keep;
        if ($keep <= 0) {
            return;
        }
        $areas = $DB->get_fieldset_sql('SELECT DISTINCT area FROM {local_auditenhance_item}');
        foreach ($areas as $area) {
            $runids = $DB->get_fieldset_sql(
                'SELECT DISTINCT runid FROM {local_auditenhance_item} WHERE area = :area',
                ['area' => $area]
            );
            rsort($runids);
            $old = array_slice($runids, $keep);
            if (!$old) {
                continue;
            }
            [$insql, $params] = $DB->get_in_or_equal($old, SQL_PARAMS_NAMED);
            $params['area'] = $area;
            $DB->delete_records_select('local_auditenhance_item', "area = :area AND runid $insql", $params);
        }
    }

    /**
     * Delete a run and everything stored for it.
     *
     * @param int $runid
     */
    public static function delete_run(int $runid): void {
        global $DB;
        $DB->delete_records('local_auditenhance_item', ['runid' => $runid]);
        $DB->delete_records('local_auditenhance_result', ['runid' => $runid]);
        $DB->delete_records('local_auditenhance_run', ['id' => $runid]);
    }

    /**
     * Keep only known area ids.
     *
     * @param string[]|null $areas
     * @return string[]
     */
    private static function clean_areas(?array $areas): array {
        if (!$areas) {
            return [];
        }
        $known = registry::instance()->get_areas();
        $clean = [];
        foreach ($areas as $areaid) {
            $areaid = trim((string)$areaid);
            if (!isset($known[$areaid])) {
                throw new \invalid_parameter_exception('Unknown audit area: ' . $areaid);
            }
            $clean[$areaid] = $areaid;
        }
        return array_values($clean);
    }
}
