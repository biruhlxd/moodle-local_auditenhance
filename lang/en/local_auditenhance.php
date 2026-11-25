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
 * English strings for Audit & Enhance.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['auditenhance:run'] = 'Run site audits and mute checks';
$string['auditenhance:view'] = 'View site audit results';
$string['auditenhance:viewcourse'] = 'View accessibility audit results of the course';
$string['cachedef_quickresults'] = 'Results of quick audit checks';
$string['clihelp'] = 'Run a site audit.

Options:
-a, --area=AREAS   Comma separated area ids to audit (default: all areas)
-l, --list         List areas and checks
-q, --queue        Queue the audit for the ad hoc task instead of running it now
-h, --help         Print this help

Example:
$ php local/auditenhance/cli/run_audit.php --area=security';
$string['cliqueued'] = 'Audit run {$a} queued.';
$string['clistarted'] = 'Audit run {$a} started.';
$string['cliunknownarea'] = 'Unknown area: {$a}. Use --list to see the areas.';
$string['event_audit_completed'] = 'Audit completed';
$string['event_audit_started'] = 'Audit started';
$string['event_check_muted'] = 'Audit check muted';
$string['event_check_unmuted'] = 'Audit check unmuted';
$string['fixlink'] = 'Fix this';
$string['muted'] = 'Muted';
$string['notscannedyet'] = 'Not scanned yet. Run an audit to see this result.';
$string['overallscore'] = 'Overall score';
$string['overview'] = 'Site audit';
$string['pluginname'] = 'Audit & Enhance';
$string['privacy:metadata:item'] = 'Content items with an audit finding, for example an image without alt text.';
$string['privacy:metadata:item:checkname'] = 'The check that found the item.';
$string['privacy:metadata:item:detail'] = 'Details of the item, such as a short extract of the content.';
$string['privacy:metadata:item:issue'] = 'The issue found.';
$string['privacy:metadata:item:timecreated'] = 'When the item was found.';
$string['privacy:metadata:item:userid'] = 'The user who owns the content.';
$string['privacy:metadata:mute'] = 'Checks muted by an administrator.';
$string['privacy:metadata:mute:checkname'] = 'The muted check.';
$string['privacy:metadata:mute:reason'] = 'Why the check was muted.';
$string['privacy:metadata:mute:timecreated'] = 'When the check was muted.';
$string['privacy:metadata:mute:userid'] = 'The user who muted the check.';
$string['privacy:metadata:run'] = 'Audit runs.';
$string['privacy:metadata:run:timecreated'] = 'When the audit was started.';
$string['privacy:metadata:run:userid'] = 'The user who started the audit.';
$string['progressscanning'] = 'Scanning';
$string['runaudit'] = 'Run audit now';
$string['scannerfailed'] = 'The scan for this check failed: {$a}';
$string['settingbatchsize'] = 'Batch size';
$string['settingbatchsize_desc'] = 'Number of records or plugins read in one batch of a content or code scan.';
$string['settingkeepruns'] = 'Runs to keep';
$string['settingkeepruns_desc'] = 'Number of finished audit runs kept with their results. Older runs are deleted. 0 keeps all runs.';
$string['settingmaxitems'] = 'Items stored per check';
$string['settingmaxitems_desc'] = 'Maximum number of individual items stored per check and run. Counts stay exact above this limit. 0 for no limit.';
$string['settingpenalty'] = 'Penalty for a {$a} finding';
$string['settingquickcachettl'] = 'Quick check cache (seconds)';
$string['settingquickcachettl_desc'] = 'Quick checks run when the overview is opened. Their results are reused for this many seconds.';
$string['settings'] = 'Settings';
$string['settingspenalties'] = 'Score penalties';
$string['settingspenalties_desc'] = 'Points taken from an area score of 100 for each failed check, by severity. An area score never goes below 0.';
$string['settingsrunning'] = 'Running audits';
$string['settingsthresholds'] = 'Thresholds';
$string['settingsweights'] = 'Area weights';
$string['settingsweights_desc'] = 'The overall score is the weighted average of the area scores. An area with weight 0 is left out.';
$string['settingtimebudget'] = 'Time per task run (seconds)';
$string['settingtimebudget_desc'] = 'An audit runs as a background task. After this many seconds it saves its progress and continues in a new task, so large sites do not time out.';
$string['settingweight'] = 'Weight of {$a}';
$string['severity_critical'] = 'Critical';
$string['severity_high'] = 'High';
$string['severity_info'] = 'Info';
$string['severity_low'] = 'Low';
$string['severity_medium'] = 'Medium';
$string['taskrunaudit'] = 'Run a site audit';
