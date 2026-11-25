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

namespace local_auditenhance;

use local_auditenhance\check\testarea\failing;
use local_auditenhance\check\testarea\number_scanner;
use local_auditenhance\check\testarea\passing;
use local_auditenhance\check\testarea\scanned;
use local_auditenhance\check\testarea\throwing;
use local_auditenhance\hook\register_extensions;
use local_auditenhance\local\area;
use local_auditenhance\local\registry;

/**
 * Helper for tests that need a small, known registry.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait test_registry_helper {
    /**
     * Build a registry with the test area, checks and scanner.
     *
     * @param bool $withthrowing Include the check that throws.
     * @return registry
     */
    protected function make_test_registry(bool $withthrowing = true): registry {
        require_once(__DIR__ . '/test_checks.php');
        $hook = new register_extensions();
        $hook->add_area(new area('testarea', 'local_auditenhance', 10, 1));
        $hook->add_checks([passing::class, failing::class, scanned::class]);
        if ($withthrowing) {
            $hook->add_check(throwing::class);
        }
        $hook->add_scanner(new number_scanner());
        return registry::from_hook($hook);
    }
}
