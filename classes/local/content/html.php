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
 * HTML helpers: parsing, text extraction and colour contrast.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class html {
    /** @var array<string, string> Basic CSS colour names. */
    public const NAMED_COLOURS = [
        'black' => '000000', 'white' => 'ffffff', 'red' => 'ff0000', 'green' => '008000', 'blue' => '0000ff',
        'yellow' => 'ffff00', 'orange' => 'ffa500', 'purple' => '800080', 'gray' => '808080', 'grey' => '808080',
        'silver' => 'c0c0c0', 'maroon' => '800000', 'olive' => '808000', 'lime' => '00ff00', 'aqua' => '00ffff',
        'cyan' => '00ffff', 'teal' => '008080', 'navy' => '000080', 'fuchsia' => 'ff00ff', 'magenta' => 'ff00ff',
        'pink' => 'ffc0cb', 'lightgray' => 'd3d3d3', 'lightgrey' => 'd3d3d3', 'darkgray' => 'a9a9a9',
        'darkgrey' => 'a9a9a9', 'lightyellow' => 'ffffe0', 'lightblue' => 'add8e6', 'lightgreen' => '90ee90',
        'gold' => 'ffd700', 'beige' => 'f5f5dc', 'brown' => 'a52a2a',
    ];

    /**
     * Parse HTML into an XPath object.
     *
     * @param string $html
     * @return \DOMXPath|null Null when the HTML cannot be parsed.
     */
    public static function parse(string $html): ?\DOMXPath {
        if (trim($html) === '') {
            return null;
        }
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML(
            '<?xml encoding="utf-8" ?><div id="auditenhance-root">' . $html . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $loaded ? new \DOMXPath($doc) : null;
    }

    /**
     * Visible text of a node, with white space collapsed.
     *
     * @param \DOMNode $node
     * @return string
     */
    public static function text(\DOMNode $node): string {
        return trim(preg_replace('/\s+/u', ' ', $node->textContent));
    }

    /**
     * Short HTML extract of a node for display.
     *
     * @param \DOMNode $node
     * @param int $length
     * @return string
     */
    public static function snippet(\DOMNode $node, int $length = 200): string {
        $html = $node->ownerDocument->saveHTML($node);
        $html = preg_replace('/\s+/u', ' ', (string)$html);
        return \core_text::strlen($html) > $length ? \core_text::substr($html, 0, $length) . '…' : $html;
    }

    /**
     * Value of a CSS property in an inline style attribute.
     *
     * @param string $style
     * @param string $property
     * @return string|null
     */
    public static function style_value(string $style, string $property): ?string {
        foreach (explode(';', $style) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) === 2 && strtolower(trim($parts[0])) === $property) {
                return strtolower(trim(str_ireplace('!important', '', $parts[1])));
            }
        }
        return null;
    }

    /**
     * Parse a CSS colour.
     *
     * @param string $value For example #fff, #a1b2c3, rgb(1, 2, 3) or red.
     * @return int[]|null [r, g, b] or null when not understood or transparent.
     */
    public static function parse_colour(string $value): ?array {
        $value = strtolower(trim($value));
        if (isset(self::NAMED_COLOURS[$value])) {
            $value = '#' . self::NAMED_COLOURS[$value];
        }
        if (preg_match('/^#([0-9a-f]{3})$/', $value, $m)) {
            $value = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
        }
        if (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/', $value, $m)) {
            return [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])];
        }
        if (preg_match('/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+)\s*)?\)$/', $value, $m)) {
            if (isset($m[4]) && $m[4] !== '' && (float)$m[4] < 1) {
                return null;
            }
            return [min(255, (int)$m[1]), min(255, (int)$m[2]), min(255, (int)$m[3])];
        }
        return null;
    }

    /**
     * Relative luminance (WCAG 2).
     *
     * @param int[] $rgb
     * @return float
     */
    public static function luminance(array $rgb): float {
        $channels = array_map(function (int $c): float {
            $c = $c / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);
        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * Contrast ratio between two colours (WCAG 2), from 1 to 21.
     *
     * @param int[] $a
     * @param int[] $b
     * @return float
     */
    public static function contrast(array $a, array $b): float {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }
}
