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
 * Context passed to checks and scanners while they run.
 *
 * A context either belongs to a stored run (items are written to the database) or is "live"
 * (used when quick checks run on page load; items are only counted in memory).
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_context {
    /** @var int Items buffered before they are written. */
    protected const BUFFER_SIZE = 200;

    /** @var int Items kept in memory per check in live mode. */
    protected const LIVE_LIMIT = 500;

    /** @var array<string, int> Item count per check reference. */
    protected array $counts = [];

    /** @var array<string, string> Highest item severity per check reference. */
    protected array $maxseverity = [];

    /** @var array<string, array> Per check memory for scanners and checks (kept between batches). */
    protected array $data = [];

    /** @var \stdClass[] Item records waiting to be written. */
    protected array $buffer = [];

    /** @var array<string, item[]> Items kept in memory in live mode. */
    protected array $liveitems = [];

    /** @var array<string, finding> Findings produced so far in this run. */
    protected array $findings = [];

    /**
     * Constructor.
     *
     * @param \stdClass|null $run Run record, null for a live context.
     * @param array $state State saved by {@see export_state()}.
     */
    public function __construct(
        /** @var \stdClass|null Run record. */
        protected ?\stdClass $run = null,
        array $state = [],
    ) {
        $this->counts = $state['counts'] ?? [];
        $this->maxseverity = $state['maxseverity'] ?? [];
        $this->data = $state['data'] ?? [];
    }

    /**
     * A live context, not tied to a stored run.
     *
     * @return self
     */
    public static function live(): self {
        return new self(null);
    }

    /**
     * Id of the run, null when live.
     *
     * @return int|null
     */
    public function get_runid(): ?int {
        return $this->run ? (int)$this->run->id : null;
    }

    /**
     * The run record, null when live.
     *
     * @return \stdClass|null
     */
    public function get_run(): ?\stdClass {
        return $this->run;
    }

    /**
     * Is this a live context?
     *
     * @return bool
     */
    public function is_live(): bool {
        return $this->run === null;
    }

    /**
     * Plugin setting with a default.
     *
     * @param string $name
     * @param mixed $default
     * @param string $component
     * @return mixed
     */
    public function get_config(string $name, $default = null, string $component = 'local_auditenhance') {
        $value = get_config($component, $name);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return $value;
    }

    /**
     * Record an item for a check.
     *
     * @param base $check
     * @param item $item
     */
    public function add_item(base $check, item $item): void {
        $ref = $check->get_ref();
        $this->counts[$ref] = ($this->counts[$ref] ?? 0) + 1;
        $current = $this->maxseverity[$ref] ?? severity::INFO;
        $this->maxseverity[$ref] = severity::max($current, $item->severity);

        if ($this->is_live()) {
            if (count($this->liveitems[$ref] ?? []) < self::LIVE_LIMIT) {
                $this->liveitems[$ref][] = $item;
            }
            return;
        }

        $max = (int)$this->get_config('maxitems', 5000);
        if ($max > 0 && $this->counts[$ref] > $max) {
            return;
        }
        $this->buffer[] = $item->to_record((int)$this->run->id, $ref, $check->get_area());
        if (count($this->buffer) >= self::BUFFER_SIZE) {
            $this->flush();
        }
    }

    /**
     * Write buffered items to the database.
     */
    public function flush(): void {
        global $DB;
        if ($this->buffer) {
            $DB->insert_records('local_auditenhance_item', $this->buffer);
            $this->buffer = [];
        }
    }

    /**
     * Number of items recorded for a check, including any not stored because of the limit.
     *
     * @param string $checkref
     * @return int
     */
    public function count_items(string $checkref): int {
        return $this->counts[$checkref] ?? 0;
    }

    /**
     * Highest severity among the items of a check.
     *
     * @param string $checkref
     * @return string|null Null when there are no items.
     */
    public function get_max_item_severity(string $checkref): ?string {
        return $this->maxseverity[$checkref] ?? null;
    }

    /**
     * Items of a check as records of local_auditenhance_item.
     *
     * @param string $checkref
     * @param int $limit 0 for all.
     * @return \stdClass[]
     */
    public function get_items(string $checkref, int $limit = 0): array {
        global $DB;
        if ($this->is_live()) {
            $items = $this->liveitems[$checkref] ?? [];
            if ($limit) {
                $items = array_slice($items, 0, $limit);
            }
            return array_map(fn(item $i) => $i->to_record(0, $checkref, ''), $items);
        }
        $this->flush();
        return array_values($DB->get_records(
            'local_auditenhance_item',
            ['runid' => $this->run->id, 'checkname' => $checkref],
            'id',
            '*',
            0,
            $limit
        ));
    }

    /**
     * Data a check or scanner keeps between batches.
     *
     * @param string $key
     * @return array
     */
    public function get_data(string $key): array {
        return $this->data[$key] ?? [];
    }

    /**
     * Store data between batches. Keep it small: it is saved as JSON on the run.
     *
     * @param string $key
     * @param array $value
     */
    public function set_data(string $key, array $value): void {
        $this->data[$key] = $value;
    }

    /**
     * Remember the finding of a check in this run.
     *
     * @param string $checkref
     * @param finding $finding
     */
    public function set_finding(string $checkref, finding $finding): void {
        $this->findings[$checkref] = $finding;
    }

    /**
     * Finding of a check already evaluated in this run.
     *
     * @param string $checkref
     * @return finding|null
     */
    public function get_finding(string $checkref): ?finding {
        global $DB;
        if (isset($this->findings[$checkref])) {
            return $this->findings[$checkref];
        }
        if (!$this->is_live()) {
            $record = $DB->get_record('local_auditenhance_result', ['runid' => $this->run->id, 'checkname' => $checkref]);
            if ($record) {
                return $this->findings[$checkref] = finding::from_record($record);
            }
        }
        return null;
    }

    /**
     * State to save between batches.
     *
     * @return array
     */
    public function export_state(): array {
        $this->flush();
        return ['counts' => $this->counts, 'maxseverity' => $this->maxseverity, 'data' => $this->data];
    }
}
