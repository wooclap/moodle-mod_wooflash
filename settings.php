<?php
// This file is part of Moodle - http://moodle.org/
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
//

/**
 * @package mod_wooflash
 * @copyright  2018 CBlue sprl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $defaultBaseUrl = 'https://api.wooflash.com';
    $settings->add(new admin_setting_heading('wooflash/config', get_string('wooflashsettings', 'wooflash'), ''));
    $settings->add(new admin_setting_configtext_with_maxlength(
        'wooflash/accesskeyid',
        get_string('accesskeyid', 'wooflash'),
        get_string('accesskeyid-description', 'wooflash'),
        '',
        PARAM_RAW_TRIMMED,
        50,
        128
    ));
    $settings->add(new admin_setting_configtext_with_maxlength(
        'wooflash/secretaccesskey',
        get_string('secretaccesskey', 'wooflash'),
        get_string('secretaccesskey-description', 'wooflash'),
        '',
        PARAM_RAW_TRIMMED,
        50,
        128
    ));
    $settings->add(new admin_setting_configtext_with_maxlength(
        'wooflash/baseurl',
        get_string('baseurl', 'wooflash'),
        get_string('baseurl-description', 'wooflash'),
        $defaultBaseUrl,
        PARAM_URL,
        50,
        256
    ));

    if (class_exists('mod_wooflash_test_connection')) {
        $settings->add(new mod_wooflash_test_connection('wooflash/testconnection', get_string('testconnection', 'wooflash'), ''));
    }

}
