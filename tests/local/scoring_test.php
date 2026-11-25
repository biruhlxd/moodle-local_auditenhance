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
 * Tests for the scoring engine and severities.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(scoring::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(severity::class)]
final class scoring_test extends \advanced_testcase {
    /**
     * Area score subtracts penalties and ignores skipped checks.
     */
    public function test_area_score(): void {
        $this->resetAfterTest();
        $this->assertNull(scoring::area_score([]));
        $this->assertNull(scoring::area_score([finding::skip('n/a'), finding::error('oops')]));
        $this->assertSame(100, scoring::area_score([finding::pass('ok'), finding::skip('n/a')]));
        $this->assertSame(100 - 30 - 7, scoring::area_score([
            finding::pass('ok'),
            finding::fail(severity::CRITICAL, 'bad'),
            finding::fail(severity::MEDIUM, 'meh'),
            finding::fail(severity::INFO, 'fyi'),
        ]));
        // Floored at 0.
        $many = array_fill(0, 10, finding::fail(severity::CRITICAL, 'bad'));
        $this->assertSame(0, scoring::area_score($many));
    }

    /**
     * Penalties can be changed in the settings.
     */
    public function test_configured_penalty(): void {
        $this->resetAfterTest();
        set_config('penalty_high', 50, 'local_auditenhance');
        $this->assertSame(50, severity::penalty(severity::HIGH));
        $this->assertSame(50, scoring::area_score([finding::fail(severity::HIGH, 'x')]));
    }

    /**
     * Overall score is the weighted average of areas that have a score.
     */
    public function test_overall_score(): void {
        $this->assertNull(scoring::overall_score([], []));
        $this->assertNull(scoring::overall_score(['a' => null], ['a' => 10]));
        $this->assertSame(75, scoring::overall_score(['a' => 100, 'b' => 50], ['a' => 1, 'b' => 1]));
        $this->assertSame(90, scoring::overall_score(['a' => 100, 'b' => 50, 'c' => null], ['a' => 4, 'b' => 1, 'c' => 5]));
        $this->assertNull(scoring::overall_score(['a' => 100], ['a' => 0]));
    }

    /**
     * Severity helpers.
     */
    public function test_severity(): void {
        $this->assertSame(severity::CRITICAL, severity::max(severity::LOW, severity::CRITICAL));
        $this->assertSame(severity::MEDIUM, severity::max(severity::MEDIUM, severity::INFO));
        $this->assertSame(severity::HIGH, severity::raise(severity::MEDIUM));
        $this->assertSame(severity::CRITICAL, severity::raise(severity::HIGH, 5));
        $this->assertTrue(severity::rank(severity::CRITICAL) > severity::rank(severity::HIGH));
        $this->assertFalse(severity::is_valid('nope'));
        $this->assertSame('critical', severity::to_core_status(severity::CRITICAL));
    }

    /**
     * Score bands.
     */
    public function test_band(): void {
        $this->assertSame('none', scoring::band(null));
        $this->assertSame('good', scoring::band(80));
        $this->assertSame('fair', scoring::band(50));
        $this->assertSame('poor', scoring::band(49));
    }
}
