<?php
// This file is part of the Secure Video Player plugin for Moodle.
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    // Register the plugin category under Local plugins.
    $ADMIN->add('localplugins', new admin_category(
        'local_securevideo_category',
        get_string('pluginname', 'local_securevideo')
    ));

    // Video Manager page — appears as first item in the category.
    $ADMIN->add('local_securevideo_category', new admin_externalpage(
        'local_securevideo_manage',
        get_string('manage', 'local_securevideo'),
        new moodle_url('/local/securevideo/manage.php'),
        'local/securevideo:manage'
    ));

    // Forensic Analyzer page.
    $ADMIN->add('local_securevideo_category', new admin_externalpage(
        'local_securevideo_forensic',
        'Forensic Analyzer',
        new moodle_url('/local/securevideo/forensic.php'),
        'local/securevideo:manage'
    ));

    // Plugin settings page.
    $settings = new admin_settingpage(
        'local_securevideo_settings',
        get_string('pluginname', 'local_securevideo') . ' — Settings'
    );

    $settings->add(new admin_setting_configtext(
        'local_securevideo/ffmpegpath',
        get_string('ffmpegpath', 'local_securevideo'),
        get_string('ffmpegpath_desc', 'local_securevideo'),
        '/usr/bin/ffmpeg',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_securevideo/tokensecret',
        get_string('tokensecret', 'local_securevideo'),
        get_string('tokensecret_desc', 'local_securevideo'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_securevideo/tokenexpiry',
        get_string('tokenexpiry', 'local_securevideo'),
        get_string('tokenexpiry_desc', 'local_securevideo'),
        '14400',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_securevideo/watermarkopacity',
        get_string('watermarkopacity', 'local_securevideo'),
        get_string('watermarkopacity_desc', 'local_securevideo'),
        '15',
        PARAM_INT
    ));

    $ADMIN->add('local_securevideo_category', $settings);
}
