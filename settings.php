<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// It under the terms of the GNU General Public License as published by.
// The Free Software Foundation, either version 3 of the License, or.
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,.
// But WITHOUT ANY WARRANTY; without even the implied warranty of.
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// Along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Admin settings registration for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Admin settings registration for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Sebale <info@sebale.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Keep a minimal Moodle settings page so config values are registered.
    $settings = new admin_settingpage(
        'local_aynurasurveys',
        get_string('pluginname', 'local_aynurasurveys')
    );

    $settings->add(new admin_setting_configcheckbox(
        'local_aynurasurveys/enabled',
        get_string('settings_enabled', 'local_aynurasurveys'),
        get_string('settings_enabled_desc', 'local_aynurasurveys'),
        1
    ));
    $settings->add(new admin_setting_configtext(
        'local_aynurasurveys/baseurl',
        get_string('settings_baseurl', 'local_aynurasurveys'),
        get_string('settings_baseurl_desc', 'local_aynurasurveys'),
        '',
        PARAM_URL
    ));
    $settings->add(new admin_setting_configpasswordunmask(
        'local_aynurasurveys/apikey',
        get_string('settings_apikey', 'local_aynurasurveys'),
        get_string('settings_apikey_desc', 'local_aynurasurveys'),
        ''
    ));

    // Redirect notice to custom settings page.
    $customurl = new moodle_url('/local/aynurasurveys/pluginsettings.php');
    $settings->add(new admin_setting_heading(
        'local_aynurasurveys/redirect_notice',
        '',
        html_writer::tag('div',
            '👉 ' . html_writer::link($customurl,
                'Open the Aynura.Surveys Settings page',
                ['class' => 'btn btn-primary']
            ),
            ['style' => 'margin-top:8px;']
        )
    ));

    $ADMIN->add('localplugins', $settings);

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_aynurasurveys_overview',
        'Aynura.Surveys: Overview',
        new moodle_url('/local/aynurasurveys/index.php'),
        'local/aynurasurveys:manage'
    ));
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_aynurasurveys_rules',
        'Aynura.Surveys: Trigger Rules',
        new moodle_url('/local/aynurasurveys/rules.php'),
        'local/aynurasurveys:manage'
    ));
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_aynurasurveys_log',
        'Aynura.Surveys: Delivery Log',
        new moodle_url('/local/aynurasurveys/log.php'),
        'local/aynurasurveys:manage'
    ));
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_aynurasurveys_diagnostics',
        'Aynura.Surveys: API Diagnostics',
        new moodle_url('/local/aynurasurveys/diagnostics.php'),
        'local/aynurasurveys:manage'
    ));
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_aynurasurveys_settings',
        'Aynura.Surveys: Settings',
        new moodle_url('/local/aynurasurveys/pluginsettings.php'),
        'local/aynurasurveys:manage'
    ));
}
