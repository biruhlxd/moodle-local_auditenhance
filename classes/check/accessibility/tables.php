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

namespace local_auditenhance\check\accessibility;

use local_auditenhance\local\content\content_check_base;
use local_auditenhance\local\content\content_item;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * Tables without header cells or captions.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tables extends content_check_base {
    /**
     * Brickfield checks for tables.
     *
     * @return string[]
     */
    public function get_brickfield_checks(): array {
        return ['table_data_should_have_th'];
    }

    /**
     * Analyse content.
     *
     * @param run_context $context
     * @param content_item $item
     * @param \DOMXPath $xpath
     */
    public function analyse(run_context $context, content_item $item, \DOMXPath $xpath): void {
        foreach ($this->query($xpath, '//table') as $table) {
            if (in_array($table->getAttribute('role'), ['presentation', 'none'], true)) {
                continue;
            }
            if (!$this->query($xpath, './/th', $table)) {
                $this->report($context, $item, 'noheaders', severity::MEDIUM, $table);
            } else if (
                !$this->query($xpath, './caption', $table) && !$table->hasAttribute('aria-label')
                    && !$table->hasAttribute('aria-labelledby')
            ) {
                $this->report($context, $item, 'nocaption', severity::LOW, $table);
            }
        }
    }
}
