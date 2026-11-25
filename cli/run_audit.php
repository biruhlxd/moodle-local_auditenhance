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
 * Run an audit from the command line.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params([
    'area' => '',
    'list' => false,
    'queue' => false,
    'help' => false,
], [
    'a' => 'area',
    'l' => 'list',
    'q' => 'queue',
    'h' => 'help',
]);

if ($unrecognised) {
    cli_error(get_string('cliunknownoption', 'admin', implode("\n  ", $unrecognised)));
}

if ($options['help']) {
    echo get_string('clihelp', 'local_auditenhance') . "\n";
    exit(0);
}

\core\session\manager::set_user(get_admin());
$registry = \local_auditenhance\local\registry::instance();

if ($options['list']) {
    foreach ($registry->get_areas() as $area) {
        cli_writeln($area->get_id() . ' - ' . $area->get_name());
        foreach ($registry->get_checks([$area->get_id()]) as $check) {
            cli_writeln('    ' . $check->get_ref() . ' - ' . $check->get_name());
        }
    }
    exit(0);
}

$areas = $options['area'] === '' ? [] : array_map('trim', explode(',', $options['area']));
foreach ($areas as $areaid) {
    if (!$registry->get_area($areaid)) {
        cli_error(get_string('cliunknownarea', 'local_auditenhance', $areaid));
    }
}

if ($options['queue']) {
    $runid = \local_auditenhance\local\run_manager::queue($areas, 'cli');
    cli_writeln(get_string('cliqueued', 'local_auditenhance', $runid));
    exit(0);
}

$run = \local_auditenhance\local\run_manager::create_run($areas, 'cli');
cli_writeln(get_string('clistarted', 'local_auditenhance', $run->id));
\local_auditenhance\local\run_manager::execute((int)$run->id, 0, function (int $percent, string $message): void {
    cli_writeln(sprintf('[%3d%%] %s', $percent, $message));
});

$run = \local_auditenhance\local\run_manager::get_run((int)$run->id);
$findings = \local_auditenhance\local\results::get_for_run((int)$run->id);
$muted = \local_auditenhance\local\mutes::get_muted_refs();
cli_separator();
foreach ($findings as $ref => $finding) {
    $check = $registry->get_check($ref);
    if (!$check) {
        continue;
    }
    $label = $finding->is_failure() ? strtoupper($finding->severity) : strtoupper($finding->status);
    if (in_array($ref, $muted, true)) {
        $label .= ' (' . get_string('muted', 'local_auditenhance') . ')';
    }
    cli_writeln(sprintf('%-10s %-14s %s: %s', $check->get_area(), $label, $check->get_name(), $finding->summary));
}
cli_separator();
$areascores = json_decode($run->areascores ?? '', true) ?: [];
foreach ($areascores as $areaid => $score) {
    $area = $registry->get_area($areaid);
    cli_writeln(sprintf('%-30s %s', $area ? $area->get_name() : $areaid, $score === null ? '-' : $score));
}
cli_writeln(get_string('overallscore', 'local_auditenhance') . ': ' . ($run->score === null ? '-' : $run->score));
exit(0);
