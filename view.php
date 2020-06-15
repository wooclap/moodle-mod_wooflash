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

require_once __DIR__ . '/../../config.php';
require_once $CFG->libdir . '/completionlib.php';
require_once $CFG->dirroot . '/mod/wooflash/lib.php';

$id = optional_param('id', 0, PARAM_INT); // Course Module ID, or
$wid = optional_param('w', 0, PARAM_INT); // Wooflash ID.

if (isset($wid) && $wid > 0) {
    // Two ways to specify the module.
    $wooflash = $DB->get_record('wooflash', ['id' => $wid], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('wooflash', $wooflash->id, $wooflash->course, false, MUST_EXIST);
} else if (isset($id) && $id > 0) {
    $cm = get_coursemodule_from_id('wooflash', $id, 0, false, MUST_EXIST);
    $wooflash = $DB->get_record('wooflash', ['id' => $cm->instance], '*', MUST_EXIST);
}

if (is_object($cm) && is_object($wooflash)) {
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

    $PAGE->set_cm($cm, $course); // Set's up global $COURSE.
    $context = context_module::instance($cm->id);
    $PAGE->set_context($context);

    require_login($course, false, $cm);
    require_capability('mod/wooflash:view', $context);

    // Add event management here.
    $event = \mod_wooflash\event\course_module_viewed::create(array(
        'objectid' => $wooflash->id,
        'context' => $context,
    ));
    $event->add_record_snapshot('course', $PAGE->course);
    $event->add_record_snapshot($cm->modname, $wooflash);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);

    $url = new moodle_url('/mod/wooflash/view.php', ['id' => $cm->id]);
    $PAGE->set_url($url);

    // View Wooflash edit form in a frame.
    if (isset($USER)) {
        $ts = wooflash_get_isotime();
        try {
            $accesskeyid = get_config('wooflash', 'accesskeyid');
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        $role = wooflash_get_role(context_course::instance($cm->course));
        $canEdit = $role == 'teacher';
        $hasAccess = wooflash_check_activity_user_access($cm->course, $cm->id, $USER->id);
        $wooflashuserid = $USER->id;

        $data_token = [
            'accessKeyId' => $accesskeyid,
            'canEdit' => $canEdit,
            'email' => $USER->email,
            'firstName' => $USER->firstname,
            'hasAccess' => $hasAccess,
            'id' => $wooflash->id,
            'lastName' => $USER->lastname,
            'moodleUserId' => $wooflashuserid,
            'ts' => $ts,
            'version' => get_config('mod_wooflash')->version,
        ];
        $token = wooflash_generate_token('JOIN?' . wooflash_http_build_query($data_token));

        $data_frame = [
            'accessKeyId' => $accesskeyid,
            'canEdit' => $canEdit,
            'email' => $USER->email,
            'firstName' => $USER->firstname,
            'hasAccess' => $hasAccess,
            'id' => $wooflash->id,
            'lastName' => $USER->lastname,
            'moodleUserId' => $wooflashuserid,
            'role' => $role,
            'token' => $token,
            'ts' => $ts,
            'version' => get_config('mod_wooflash')->version,
        ];

        wooflash_frame_view($wooflash->editurl . '?' . wooflash_http_build_query($data_frame));
    }
} else {
    print_error('error-nocourseid', 'wooflash');
}
