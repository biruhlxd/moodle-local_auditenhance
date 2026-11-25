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
 * Admin settings for Audit & Enhance.
 *
 * @package    local_auditenhance
 * @copyright  2026 Biruh Alemnew
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('reports', new admin_category('local_auditenhance', new lang_string('pluginname', 'local_auditenhance')));
$ADMIN->add('local_auditenhance', new admin_externalpage(
    'local_auditenhance_overview',
    new lang_string('overview', 'local_auditenhance'),
    new moodle_url('/local/auditenhance/index.php'),
    'local/auditenhance:view'
));

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_auditenhance_settings', new lang_string('settings', 'local_auditenhance'));
    $ADMIN->add('local_auditenhance', $settings);

    if ($ADMIN->fulltree) {
        // Thresholds used by the checks.
        $settings->add(new admin_setting_heading(
            'local_auditenhance/thresholds',
            new lang_string('settingsthresholds', 'local_auditenhance'),
            ''
        ));
        foreach (\local_auditenhance\local\settings::get_thresholds() as $name => $default) {
            $settings->add(new admin_setting_configtext(
                'local_auditenhance/' . $name,
                new lang_string('setting_' . $name, 'local_auditenhance'),
                new lang_string('setting_' . $name . '_desc', 'local_auditenhance'),
                $default,
                PARAM_INT
            ));
        }
        foreach (\local_auditenhance\local\settings::get_extra_settings() as $setting) {
            $settings->add($setting);
        }

        // Area weights, one per registered area.
        $settings->add(new admin_setting_heading(
            'local_auditenhance/weights',
            new lang_string('settingsweights', 'local_auditenhance'),
            new lang_string('settingsweights_desc', 'local_auditenhance')
        ));
        try {
            $areas = \local_auditenhance\local\registry::instance()->get_areas();
        } catch (\Throwable $e) {
            $areas = [];
        }
        foreach ($areas as $area) {
            $settings->add(new admin_setting_configtext(
                'local_auditenhance/weight_' . $area->get_id(),
                new lang_string('settingweight', 'local_auditenhance', $area->get_name()),
                '',
                $area->get_default_weight(),
                PARAM_INT
            ));
        }

        // Penalties per severity.
        $settings->add(new admin_setting_heading(
            'local_auditenhance/penalties',
            new lang_string('settingspenalties', 'local_auditenhance'),
            new lang_string('settingspenalties_desc', 'local_auditenhance')
        ));
        foreach (\local_auditenhance\local\severity::DEFAULT_PENALTIES as $severity => $penalty) {
            $settings->add(new admin_setting_configtext(
                'local_auditenhance/penalty_' . $severity,
                new lang_string(
                    'settingpenalty',
                    'local_auditenhance',
                    \local_auditenhance\local\severity::get_name($severity)
                ),
                '',
                $penalty,
                PARAM_INT
            ));
        }

        // Running audits.
        $settings->add(new admin_setting_heading(
            'local_auditenhance/running',
            new lang_string('settingsrunning', 'local_auditenhance'),
            ''
        ));
        $settings->add(new admin_setting_configtext(
            'local_auditenhance/timebudget',
            new lang_string('settingtimebudget', 'local_auditenhance'),
            new lang_string('settingtimebudget_desc', 'local_auditenhance'),
            120,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            'local_auditenhance/batchsize',
            new lang_string('settingbatchsize', 'local_auditenhance'),
            new lang_string('settingbatchsize_desc', 'local_auditenhance'),
            200,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            'local_auditenhance/maxitems',
            new lang_string('settingmaxitems', 'local_auditenhance'),
            new lang_string('settingmaxitems_desc', 'local_auditenhance'),
            5000,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            'local_auditenhance/keepruns',
            new lang_string('settingkeepruns', 'local_auditenhance'),
            new lang_string('settingkeepruns_desc', 'local_auditenhance'),
            50,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            'local_auditenhance/quickcachettl',
            new lang_string('settingquickcachettl', 'local_auditenhance'),
            new lang_string('settingquickcachettl_desc', 'local_auditenhance'),
            300,
            PARAM_INT
        ));
    }
}
