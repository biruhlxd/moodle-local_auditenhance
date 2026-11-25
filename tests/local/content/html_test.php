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

namespace local_auditenhance\local\content;

/**
 * Tests for the HTML helpers.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(html::class)]
final class html_test extends \basic_testcase {
    /**
     * Parsing keeps UTF-8 text and wraps content in the root element.
     */
    public function test_parse(): void {
        $this->assertNull(html::parse('  '));
        $xpath = html::parse('<p>Café <b>bold</b></p>');
        $root = $xpath->query('//div[@id="auditenhance-root"]')->item(0);
        $this->assertSame('Café bold', html::text($root));
        $this->assertStringContainsString('<b>bold</b>', html::snippet($xpath->query('//p')->item(0)));
    }

    /**
     * Colours and contrast.
     */
    public function test_colours(): void {
        $this->assertSame([255, 255, 255], html::parse_colour('#FFF'));
        $this->assertSame([18, 52, 86], html::parse_colour('#123456'));
        $this->assertSame([1, 2, 3], html::parse_colour('rgb(1, 2, 3)'));
        $this->assertSame([255, 0, 0], html::parse_colour('red'));
        $this->assertNull(html::parse_colour('rgba(0,0,0,0.5)'));
        $this->assertNull(html::parse_colour('inherit'));
        $this->assertEqualsWithDelta(21.0, html::contrast([0, 0, 0], [255, 255, 255]), 0.01);
        $this->assertEqualsWithDelta(1.0, html::contrast([10, 10, 10], [10, 10, 10]), 0.01);
        $this->assertEqualsWithDelta(4.54, html::contrast([118, 118, 118], [255, 255, 255]), 0.02);
    }

    /**
     * Inline style values.
     */
    public function test_style_value(): void {
        $this->assertSame('#fff', html::style_value('font-weight: bold; COLOR: #FFF !important', 'color'));
        $this->assertNull(html::style_value('font-weight: bold', 'color'));
        $this->assertSame('1.5em', html::style_value('font-size:1.5em', 'font-size'));
    }
}
