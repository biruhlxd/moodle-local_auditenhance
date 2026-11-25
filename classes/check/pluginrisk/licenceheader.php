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

namespace local_auditenhance\check\pluginrisk;

use local_auditenhance\local\plugins\plugin_check_base;
use local_auditenhance\local\plugins\scanned_plugin;
use local_auditenhance\local\run_context;
use local_auditenhance\local\severity;

/**
 * PHP files without a licence header.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class licenceheader extends plugin_check_base {
    /** @var string Pattern of a licence statement. */
    public const PATTERN = '/GNU General Public License|\bGPL\b|SPDX-License-Identifier|@license/i';

    /**
     * Analyse a plugin: one item per plugin with the number of files.
     *
     * @param run_context $context
     * @param scanned_plugin $plugin
     */
    public function analyse_plugin(run_context $context, scanned_plugin $plugin): void {
        $missing = [];
        foreach ($plugin->get_php_files() as $file) {
            $head = implode("\n", array_slice(explode("\n", $plugin->read_file($file)), 0, 40));
            if ($head !== '' && !preg_match(self::PATTERN, $head)) {
                $missing[] = $file;
            }
        }
        if ($missing) {
            $this->report(
                $context,
                $plugin,
                'nolicence',
                severity::LOW,
                $missing[0],
                1,
                get_string('filescount', 'local_auditenhance', count($missing))
            );
        }
    }
}
