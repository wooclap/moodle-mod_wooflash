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
 * User Authentication endpoint
 *
 * @package    mod_wooflash
 * @copyright  2018 Cblue sprl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// No login check is expected here because this script checks the login itself
// @codingStandardsIgnoreLine
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/lib.php';

global $SESSION, $USER;

$courseid = required_param('course', PARAM_INT);
$cmid = required_param('cm', PARAM_INT);
$callback = required_param('callback', PARAM_URL);

if (!wooflash_isValidCallbackUrl($callback)) {
    throw new \moodle_exception('error-invalid-callback-url', 'wooflash');
}

if (isset($USER) && is_object($USER)) {
    $authUser = $USER;
}

if (isset($SESSION)) {
    $SESSION->wooflash_courseid = $courseid;
    $SESSION->wooflash_cmid = $cmid;
    $SESSION->wooflash_callback = $callback;
} else {
    throw new \moodle_exception('error-auth-nosession', 'wooflash');
}

try {
    if (!$authUser || $authUser->id == 0) {
        $data = [
            'course' => $courseid,
            'cm' => $cmid,
            'callback' => $callback,
        ];
        // Cannot use new moodle_url because of http_build_query RFC constant.
        $SESSION->wooflash_wantsurl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?' . wooflash_http_build_query($data);

        redirect($CFG->wwwroot . '/login/index.php');
    } else {
        wooflash_redirect_auth($authUser->id);
    }
} catch (Exception $exc) {
    throw new \moodle_exception('error-couldnotredirect', 'wooflash');
}
