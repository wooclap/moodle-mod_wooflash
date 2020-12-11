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
 * This file contains en_utf8 translation of the Wooflash module
 *
 * @package mod_wooflash
 * @copyright  20018 CBlue sprl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['modulename'] = 'Wooflash';
$string['modulenameplural'] = 'Wooflash';
$string['modulename_help'] = 'This module provides a Wooflash interactive platform integration to Moodle';
$string['pluginname'] = 'Wooflash';
$string['pluginadministration'] = 'Wooflash administration';
$string['wooflashname'] = 'Name';
$string['wooflashintro'] = 'Description';
$string['modulenamepluralformatted'] = 'List of Wooflash activities';
$string['quiz'] = 'Import a Moodle quiz';
$string['wooflashcourseid'] = 'Duplicate a Wooflash course';
$string['wooflashsettings'] = 'Settings';
$string['testconnection'] = 'Test Connection';
$string['pingOK'] = 'Connection established with Wooflash';
$string['pingNOTOK'] = 'The connection could not be established with Wooflash. Please check your settings.';
$string['secretaccesskey'] = 'API Key (secretAccessKey)';
$string['secretaccesskey-description'] = 'Secret access key used to communicate with Wooflash. Should start with \'sk.\'.';
$string['accesskeyid'] = 'Platform Id (accessKeyId)';
$string['accesskeyid-description'] = 'Access key id used to communicate with Wooflash. Should start with \'ak.\'.';
$string['baseurl'] = 'Base URL';
$string['baseurl-description'] = 'This is for debugging or testing only. Only change this value at the request of the Wooflash support team.';
$string['nowooflash'] = 'There are no Wooflash instances';
$string['gradeupdateok'] = 'Grade update successful';
$string['gradeupdatefailed'] = 'Grade update failed';
$string['customcompletion'] = 'Completion state updated only by Wooflash';
$string['customcompletiongroup'] = 'Wooflash custom completion';
$string['wooflashredirect'] = 'You will be redirected to Wooflash. If this does not happen automatically, click on this link to continue.';

/* Capabilities */
$string['wooflash:view'] = 'Access a Wooflash activity';
$string['wooflash:addinstance'] = 'Add a Wooflash activity to a course';

$string['privacy:metadata:wooflash_server'] = 'In order to integrate with Wooflash, user data needs to be exchanged.';
$string['privacy:metadata:wooflash_server:userid'] = 'The user id is sent from Moodle to allow you to access your data on Wooflash.';

$string['error-nocourseid'] = 'Could not determine course id';
$string['error-auth-nosession'] = 'Missing session in authentication';
$string['error-callback-is-not-url'] = 'Callback parameter is not a valid URL';
$string['error-couldnotredirect'] = 'Could not redirect';
$string['error-couldnotloadcourses'] = 'Could not load user\'s Wooflash courses';
$string['error-couldnotupdatereport'] = 'Could not update report';
$string['error-couldnotauth'] = 'Could not get user or course during authentication';
$string['error-invalidtoken'] = 'Invalid token';
$string['error-invalidjoinurl'] = 'Invalid join URL';
$string['error-missingparameters'] = 'Missing parameters';
