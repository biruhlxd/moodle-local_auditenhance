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
 * Plugin risk by plugin: findings per plugin with severity.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login(null, false);

use local_auditenhance\output\page;

$component = optional_param('component', '', PARAM_COMPONENT);
$url = new moodle_url('/local/auditenhance/plugins.php', $component ? ['component' => $component] : []);
$title = get_string('screen_plugins', 'local_auditenhance');
$output = page::setup('plugins', $url, $title);

echo page::start('plugins', $title, ['area' => 'pluginrisk', 'component' => $component]);
echo $output->render(new \local_auditenhance\output\plugins_page('pluginrisk', $component ?: null));
echo page::end();
