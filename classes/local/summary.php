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
 * A finding summary kept as a language string reference, so it is shown in the reader's language.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class summary {
    /**
     * Constructor.
     *
     * @param string $identifier String identifier.
     * @param string $component Component of the string.
     * @param mixed $a Parameters of the string: scalar, array or object of scalars.
     */
    public function __construct(
        /** @var string Identifier. */
        public readonly string $identifier,
        /** @var string Component. */
        public readonly string $component,
        /** @var mixed Parameters. */
        public readonly mixed $a = null,
    ) {
    }

    /**
     * Text in the current language.
     *
     * @return string
     */
    public function out(): string {
        return get_string($this->identifier, $this->component, $this->a);
    }

    /**
     * Text in the current language.
     *
     * @return string
     */
    public function __toString(): string {
        return $this->out();
    }

    /**
     * Data for storage.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'identifier' => $this->identifier,
            'component' => $this->component,
            'a' => is_object($this->a) ? (array)$this->a : $this->a,
            'object' => is_object($this->a),
        ];
    }

    /**
     * Rebuild from stored data, if the string still exists.
     *
     * @param mixed $data Output of {@see to_array()}.
     * @return self|null
     */
    public static function from_array($data): ?self {
        if (!is_array($data) || empty($data['identifier']) || empty($data['component'])) {
            return null;
        }
        if (!get_string_manager()->string_exists($data['identifier'], $data['component'])) {
            return null;
        }
        $a = $data['a'] ?? null;
        if (!empty($data['object']) && is_array($a)) {
            $a = (object)$a;
        }
        return new self($data['identifier'], $data['component'], $a);
    }
}
