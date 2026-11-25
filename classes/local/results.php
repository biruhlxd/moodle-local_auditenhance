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
 * Reads stored results and combines them with quick checks run on page load.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class results {
    /** @var string Cache key of the live results. */
    private const CACHE_KEY = 'live';

    /**
     * Forget cached quick results.
     */
    public static function purge_cache(): void {
        \cache::make('local_auditenhance', 'quickresults')->purge();
    }

    /**
     * Findings stored for a run.
     *
     * @param int $runid
     * @return array<string, finding> By check reference.
     */
    public static function get_for_run(int $runid): array {
        global $DB;
        $result = [];
        foreach ($DB->get_records('local_auditenhance_result', ['runid' => $runid], 'id') as $record) {
            $result[$record->checkname] = finding::from_record($record);
        }
        return $result;
    }

    /**
     * Latest stored result record of each check, from complete runs.
     *
     * @return array<string, \stdClass> By check reference. Each record also has runfinished.
     */
    public static function get_latest_records(): array {
        global $DB;
        $sql = "SELECT r.*, run.finished AS runfinished
                  FROM {local_auditenhance_result} r
                  JOIN {local_auditenhance_run} run ON run.id = r.runid
                  JOIN (SELECT r2.checkname, MAX(r2.runid) AS maxrunid
                          FROM {local_auditenhance_result} r2
                          JOIN {local_auditenhance_run} run2 ON run2.id = r2.runid AND run2.status = :status2
                      GROUP BY r2.checkname) latest
                    ON latest.checkname = r.checkname AND latest.maxrunid = r.runid
              ORDER BY r.id";
        $result = [];
        foreach ($DB->get_records_sql($sql, ['status2' => run_manager::STATUS_COMPLETE]) as $record) {
            $result[$record->checkname] = $record;
        }
        return $result;
    }

    /**
     * Latest stored finding of a check.
     *
     * @param string $checkref
     * @return finding|null
     */
    public static function get_latest_finding(string $checkref): ?finding {
        global $DB;
        $sql = "SELECT r.*
                  FROM {local_auditenhance_result} r
                  JOIN {local_auditenhance_run} run ON run.id = r.runid AND run.status = :status
                 WHERE r.checkname = :checkname
              ORDER BY r.runid DESC";
        $records = $DB->get_records_sql($sql, ['status' => run_manager::STATUS_COMPLETE, 'checkname' => $checkref], 0, 1);
        return $records ? finding::from_record(reset($records)) : null;
    }

    /**
     * Run quick checks now, or take them from the cache.
     *
     * @param registry|null $registry
     * @return array{time: int, findings: array<string, finding>}
     */
    public static function get_live(?registry $registry = null): array {
        $registry = $registry ?? registry::instance();
        $cache = \cache::make('local_auditenhance', 'quickresults');
        $ttl = (int)(get_config('local_auditenhance', 'quickcachettl') ?: 300);
        $cached = $cache->get(self::CACHE_KEY);
        if (is_array($cached) && ($cached['time'] ?? 0) > time() - $ttl) {
            $findings = [];
            foreach ($cached['findings'] as $ref => $data) {
                $findings[$ref] = new finding(
                    $data['status'],
                    $data['severity'],
                    $data['summary'],
                    $data['details'],
                    $data['link'],
                    $data['itemcount']
                );
            }
            return ['time' => $cached['time'], 'findings' => $findings];
        }

        $context = run_context::live();
        $findings = [];
        $store = [];
        foreach ($registry->get_checks() as $ref => $check) {
            if (!$check->is_quick()) {
                continue;
            }
            try {
                $finding = $check->evaluate($context);
            } catch (\Throwable $e) {
                $finding = finding::error($e->getMessage());
            }
            $context->set_finding($ref, $finding);
            $findings[$ref] = $finding;
            $store[$ref] = (array)$finding;
        }
        $time = time();
        $cache->set(self::CACHE_KEY, ['time' => $time, 'findings' => $store]);
        return ['time' => $time, 'findings' => $findings];
    }

    /**
     * Current state of every check: quick checks run now, others from the latest run that included them.
     *
     * @param bool $includelive Run quick checks now (false: stored results only).
     * @param registry|null $registry
     * @return array<string, array{finding: finding|null, source: string, time: int, runid: int|null}>
     *         By check reference, for every registered check. Finding is null when the check never ran.
     */
    public static function get_current(bool $includelive = true, ?registry $registry = null): array {
        $registry = $registry ?? registry::instance();
        $stored = self::get_latest_records();
        $live = $includelive ? self::get_live($registry) : ['time' => 0, 'findings' => []];
        $result = [];
        foreach ($registry->get_checks() as $ref => $check) {
            if (isset($live['findings'][$ref])) {
                $result[$ref] = ['finding' => $live['findings'][$ref], 'source' => 'live',
                    'time' => $live['time'], 'runid' => null];
            } else if (isset($stored[$ref])) {
                $result[$ref] = ['finding' => finding::from_record($stored[$ref]), 'source' => 'run',
                    'time' => (int)$stored[$ref]->runfinished, 'runid' => (int)$stored[$ref]->runid];
            } else {
                $result[$ref] = ['finding' => null, 'source' => 'none', 'time' => 0, 'runid' => null];
            }
        }
        return $result;
    }

    /**
     * Scores for the current state.
     *
     * @param array $current Output of {@see get_current()}.
     * @param registry|null $registry
     * @return array{areas: array<string, int|null>, overall: int|null}
     */
    public static function get_current_scores(array $current, ?registry $registry = null): array {
        $findings = [];
        foreach ($current as $ref => $entry) {
            if ($entry['finding']) {
                $findings[$ref] = $entry['finding'];
            }
        }
        return scoring::compute($findings, mutes::get_muted_refs(), $registry);
    }

    /**
     * Failed, non-muted findings ordered by severity, most severe first.
     *
     * @param array $current Output of {@see get_current()}.
     * @param int $limit 0 for all.
     * @param string|null $areaid Only this area.
     * @param registry|null $registry
     * @return array<string, array> Entries of $current, by reference.
     */
    public static function get_top_failures(
        array $current,
        int $limit = 10,
        ?string $areaid = null,
        ?registry $registry = null
    ): array {
        $registry = $registry ?? registry::instance();
        $muted = array_flip(mutes::get_muted_refs());
        $failures = [];
        foreach ($current as $ref => $entry) {
            $check = $registry->get_check($ref);
            if (!$check || isset($muted[$ref]) || !$entry['finding'] || !$entry['finding']->is_failure()) {
                continue;
            }
            if ($areaid !== null && $check->get_area() !== $areaid) {
                continue;
            }
            $failures[$ref] = $entry;
        }
        uasort($failures, function (array $a, array $b): int {
            $bysev = severity::rank($b['finding']->severity) <=> severity::rank($a['finding']->severity);
            return $bysev ?: $b['finding']->itemcount <=> $a['finding']->itemcount;
        });
        return $limit ? array_slice($failures, 0, $limit, true) : $failures;
    }
}
