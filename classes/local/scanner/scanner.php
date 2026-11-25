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

namespace local_auditenhance\local\scanner;

use local_auditenhance\local\run_context;

/**
 * A scanner walks a large set of things (content, plugin code) in batches during a run and feeds
 * each piece to the checks that implement {@see get_check_interface()}.
 *
 * Scanners run before checks are evaluated. A run may be split over several task executions, so
 * a scanner must be able to resume from the cursor it returned.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface scanner {
    /**
     * Unique id of the scanner.
     *
     * @return string
     */
    public function get_id(): string;

    /**
     * Interface a check implements to be fed by this scanner.
     *
     * @return string
     */
    public function get_check_interface(): string;

    /**
     * Should this scanner run at all for these checks?
     *
     * @param \local_auditenhance\check\base[] $checks Checks implementing the interface, in the run's areas.
     * @return bool
     */
    public function is_needed(array $checks): bool;

    /**
     * Process one batch.
     *
     * @param run_context $context
     * @param \local_auditenhance\check\base[] $checks Checks implementing the interface, in the run's areas.
     * @param string|null $cursor Null on the first call.
     * @return string|null Cursor for the next batch, null when done.
     */
    public function process_batch(run_context $context, array $checks, ?string $cursor): ?string;

    /**
     * Estimated progress for a cursor, between 0 and 1.
     *
     * @param string|null $cursor
     * @return float
     */
    public function get_progress(?string $cursor): float;
}
