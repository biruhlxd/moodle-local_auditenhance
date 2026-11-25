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

/**
 * Test checks and scanner used by the unit tests.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

namespace local_auditenhance\check\testarea;

defined('MOODLE_INTERNAL') || die();

use local_auditenhance\check\base;
use local_auditenhance\local\finding;
use local_auditenhance\local\item;
use local_auditenhance\local\run_context;
use local_auditenhance\local\scanner\scanner;
use local_auditenhance\local\severity;

/**
 * Interface for checks fed by the test scanner.
 */
interface test_scanned {
    /**
     * Receive one number.
     *
     * @param run_context $context
     * @param int $number
     */
    public function receive(run_context $context, int $number): void;
}

/**
 * A check that always passes.
 */
class passing extends base {
    /**
     * Name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'Passing';
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        return finding::pass('All good');
    }
}

/**
 * A check that always fails with high severity.
 */
class failing extends base {
    /**
     * Name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'Failing';
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        return finding::fail(severity::HIGH, 'Broken');
    }
}

/**
 * A check that throws.
 */
class throwing extends base {
    /**
     * Name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'Throwing';
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        throw new \moodle_exception('error');
    }
}

/**
 * A heavy check fed by the test scanner: one item per odd number.
 */
class scanned extends base implements test_scanned {
    /**
     * Name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'Scanned';
    }

    /**
     * Not quick.
     *
     * @return bool
     */
    public function is_quick(): bool {
        return false;
    }

    /**
     * Receive a number.
     *
     * @param run_context $context
     * @param int $number
     */
    public function receive(run_context $context, int $number): void {
        if ($number % 2) {
            $context->add_item($this, new item(
                'odd',
                $number > 15 ? severity::MEDIUM : severity::LOW,
                0,
                0,
                'core',
                $number,
                'number',
                0,
                ['label' => 'Number ' . $number]
            ));
        }
    }

    /**
     * Evaluate.
     *
     * @param run_context $context
     * @return finding
     */
    public function evaluate(run_context $context): finding {
        $count = $context->count_items($this->get_ref());
        if (!$count) {
            return finding::pass('No odd numbers');
        }
        return finding::fail($context->get_max_item_severity($this->get_ref()), "$count odd numbers", [], $count);
    }
}

/**
 * Scanner feeding numbers 1 to 20, five per batch.
 */
class number_scanner implements scanner {
    /**
     * Id.
     *
     * @return string
     */
    public function get_id(): string {
        return 'numbers';
    }

    /**
     * Interface.
     *
     * @return string
     */
    public function get_check_interface(): string {
        return test_scanned::class;
    }

    /**
     * Needed.
     *
     * @param array $checks
     * @return bool
     */
    public function is_needed(array $checks): bool {
        return true;
    }

    /**
     * Process a batch.
     *
     * @param run_context $context
     * @param array $checks
     * @param string|null $cursor
     * @return string|null
     */
    public function process_batch(run_context $context, array $checks, ?string $cursor): ?string {
        $start = (int)$cursor;
        for ($i = $start + 1; $i <= min(20, $start + 5); $i++) {
            foreach ($checks as $check) {
                $check->receive($context, $i);
            }
        }
        return $start + 5 >= 20 ? null : (string)($start + 5);
    }

    /**
     * Progress.
     *
     * @param string|null $cursor
     * @return float
     */
    public function get_progress(?string $cursor): float {
        return (int)$cursor / 20;
    }
}
