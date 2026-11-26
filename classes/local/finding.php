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
 * The outcome of one check.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class finding {
    /** @var string The check passed. */
    public const STATUS_PASS = 'pass';
    /** @var string The check found a problem. */
    public const STATUS_FAIL = 'fail';
    /** @var string The check does not apply to this site and is not counted. */
    public const STATUS_SKIP = 'skip';
    /** @var string The check could not run. */
    public const STATUS_ERROR = 'error';

    /** @var int Maximum number of sample entries kept in the details. */
    public const MAX_SAMPLE = 10;

    /** @var string Summary in the current language. */
    public string $summary;

    /** @var summary|null Language string behind the summary, when known. */
    public ?summary $summaryref = null;

    /**
     * Constructor.
     *
     * @param string $status One of the STATUS_ constants.
     * @param string $severity One of the severity constants. Only meaningful for failures.
     * @param string|summary $summary Short plain text summary, or a language string reference (translated when shown).
     * @param array $details Extra data: 'sample' holds a list of ['label' => string, 'url' => string|null].
     * @param string|null $link URL of the setting or content to fix.
     * @param int $itemcount Number of items behind this finding.
     */
    public function __construct(
        /** @var string Status. */
        public string $status,
        /** @var string Severity. */
        public string $severity = severity::INFO,
        string|summary $summary = '',
        /** @var array Details. */
        public array $details = [],
        /** @var string|null Link. */
        public ?string $link = null,
        /** @var int Item count. */
        public int $itemcount = 0,
    ) {
        if (!severity::is_valid($this->severity)) {
            $this->severity = severity::INFO;
        }
        if ($summary instanceof summary) {
            $this->summaryref = $summary;
            $this->summary = $summary->out();
        } else {
            $this->summary = $summary;
        }
    }

    /**
     * A passing finding.
     *
     * @param string|summary $summary
     * @param array $details
     * @return self
     */
    public static function pass(string|summary $summary, array $details = []): self {
        return new self(self::STATUS_PASS, severity::INFO, $summary, $details);
    }

    /**
     * A failing finding.
     *
     * @param string $severity
     * @param string|summary $summary
     * @param array $details
     * @param int $itemcount
     * @return self
     */
    public static function fail(string $severity, string|summary $summary, array $details = [], int $itemcount = 0): self {
        return new self(self::STATUS_FAIL, $severity, $summary, $details, null, $itemcount);
    }

    /**
     * A finding for a check that does not apply.
     *
     * @param string|summary $summary
     * @return self
     */
    public static function skip(string|summary $summary): self {
        return new self(self::STATUS_SKIP, severity::INFO, $summary);
    }

    /**
     * A finding for a check that could not run.
     *
     * @param string|summary $summary
     * @return self
     */
    public static function error(string|summary $summary): self {
        return new self(self::STATUS_ERROR, severity::INFO, $summary);
    }

    /**
     * Set the link to fix this finding.
     *
     * @param \moodle_url|string|null $link
     * @return self
     */
    public function with_link(\moodle_url|string|null $link): self {
        $this->link = $link === null ? null : ($link instanceof \moodle_url ? $link->out(false) : $link);
        return $this;
    }

    /**
     * Add sample entries shown under the finding.
     *
     * @param array $sample List of ['label' => string, 'url' => string|moodle_url|null].
     * @return self
     */
    public function with_sample(array $sample): self {
        $clean = [];
        foreach (array_slice($sample, 0, self::MAX_SAMPLE) as $entry) {
            $url = $entry['url'] ?? null;
            $clean[] = [
                'label' => (string)($entry['label'] ?? ''),
                'url' => $url instanceof \moodle_url ? $url->out(false) : $url,
            ];
        }
        $this->details['sample'] = $clean;
        return $this;
    }

    /**
     * Does this finding count against the score?
     *
     * @return bool
     */
    public function is_failure(): bool {
        return $this->status === self::STATUS_FAIL;
    }

    /**
     * Is this finding counted in the score at all (pass or fail)?
     *
     * @return bool
     */
    public function is_applicable(): bool {
        return $this->status === self::STATUS_PASS || $this->status === self::STATUS_FAIL;
    }

    /**
     * Build a finding from a stored result record.
     *
     * @param \stdClass $record Row of local_auditenhance_result.
     * @return self
     */
    public static function from_record(\stdClass $record): self {
        $details = json_decode($record->details ?? '', true);
        if (!is_array($details)) {
            $details = [];
        }
        $link = $details['_link'] ?? null;
        $ref = summary::from_array($details['_str'] ?? null);
        unset($details['_link'], $details['_str']);
        return new self(
            $record->status,
            $record->severity,
            $ref ?? (string)$record->summary,
            $details,
            $link,
            (int)$record->itemcount
        );
    }

    /**
     * Details encoded for storage.
     *
     * @return string
     */
    public function encode_details(): string {
        $details = $this->details;
        if ($this->link !== null) {
            $details['_link'] = $this->link;
        }
        if ($this->summaryref !== null) {
            $details['_str'] = $this->summaryref->to_array();
        }
        return json_encode($details);
    }
}
