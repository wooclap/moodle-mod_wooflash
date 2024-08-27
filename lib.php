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
 * This file contains a library of functions and constants for the wooflash module
 *
 * @package mod_wooflash
 * @copyright  2018 CBlue sprl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 *
 * @see https://github.com/moodle/moodle/blob/master/mod/lesson/lib.php#L1693
 * @see https://github.com/moodle/moodle/blob/master/mod/resource/lib.php#L207
 * @see https://docs.moodle.org/dev/Module_visibility_and_display for more info.
 */

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once $CFG->dirroot . '/mod/wooflash/classes/wooflash_curl.php';
require_once $CFG->dirroot . '/question/editlib.php';
require_once $CFG->dirroot . '/mod/wooflash/format.php';

/**
 * @param $feature
 * @return bool|null
 */
function wooflash_supports($feature) {
    switch ($feature) {
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GROUPINGS:
        case FEATURE_GROUPS:
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_GRADE_OUTCOMES:
            return false;

        default:
            return null;
    }
}

/**
 * @param int $id
 * @return bool
 * @throws dml_exception
 */
function wooflash_delete_instance($id) {
    global $DB;

    if (!$wooflash = $DB->get_record('wooflash', ['id' => $id])) {
        return false;
    }

    // Note: all context files are deleted automatically.

    $DB->delete_records('wooflash', ['id' => $id]);
    $DB->delete_records('wooflash_completion', ['wooflashid' => $id]);

    grade_update('mod/wooflash', $wooflash->course, 'mod', 'wooflash', $id, 0, null, ['deleted' => 1]);

    return true;
}

/**
 * @param $data
 * @return bool
 * @throws dml_exception
 */
function wooflash_update_instance($data) {
    global $DB;

    if (!isset($data->update)) {
        return false;
    }

    $cm = $DB->get_record('course_modules', ['id' => $data->update]);

    $wooflash = $DB->get_record('wooflash', ['id' => $cm->instance]);

    $activity = new StdClass;
    $activity->id = $wooflash->id;
    $activity->course = $data->course;
    $activity->name = $data->name;
    $activity->intro = $data->intro;
    $activity->introformat = $data->introformat;
    $activity->editurl = $wooflash->editurl;
    $activity->quiz = $data->quiz;
    $activity->timecreated = $wooflash->timecreated;
    $activity->timemodified = time();
    $activity->wooflasheventid = $data->wooflasheventid;
    $DB->update_record('wooflash', $activity);

    wooflash_grade_item_update($wooflash);

    return true;
}

/**
 * @param $data
 * @return bool|int
 * @throws dml_exception
 */
function wooflash_add_instance($data) {
    global $DB, $USER;

    $activity = new StdClass;
    $activity->course = $data->course;
    $activity->name = $data->name;
    $activity->intro = $data->intro;
    $activity->introformat = $data->introformat;
    // Fill editurl later with curl response from observer::course_module_created.
    $activity->editurl = '';
    $activity->quiz = $data->quiz;
    $activity->authorid = $USER->id;
    $activity->timecreated = time();
    $activity->timemodified = $activity->timecreated;
    $activity->wooflasheventid = $data->wooflasheventid;
    $activity->id = $DB->insert_record('wooflash', $activity);

    return $activity->id;
}

/**
 * @param int $id
 * @return mixed
 * @throws Exception
 */
function wooflash_get_instance($id) {
    global $DB;
    try {
        return $DB->get_record('wooflash', ['id' => $id], '*', MUST_EXIST);
    } catch (Exception $e) {
        throw new Exception('This wooflash instance does not exist!');
    }
}

/**
 * @return string
 */
function wooflash_get_create_url() {
    $baseurl = get_config('wooflash', 'baseurl');
    $hastrailingslash = substr($baseurl, -1) === '/';
    return $baseurl . ($hastrailingslash ? '' : '/') . 'api/moodle/v3/events';
}

/**
 * @return string
 */
function wooflash_get_events_list_url() {
    $baseurl = get_config('wooflash', 'baseurl');
    $hastrailingslash = substr($baseurl, -1) === '/';
    return $baseurl . ($hastrailingslash ? '' : '/') . 'api/moodle/v3/events_list';
}

/**
 * @return string
 */
function wooflash_get_ping_url() {
    $baseurl = get_config('wooflash', 'baseurl');
    $hastrailingslash = substr($baseurl, -1) === '/';
    return $baseurl . ($hastrailingslash ? '' : '/') . 'api/moodle/v3/ping';
}

/**
 * @param $data
 * @return string
 * @throws Exception
 */
function wooflash_generate_token($data) {
    return hash_hmac('sha256', $data, get_config('wooflash', 'secretaccesskey'));
}

/**
 * @param $data
 * @return string
 */
function wooflash_http_build_query($data) {
    return http_build_query($data, '', '&', PHP_QUERY_RFC3986);
}

/**
 * @param int $courseid
 * @param int $cmid
 * @param int $userid
 * @return bool
 * @throws moodle_exception
 */
function wooflash_check_activity_user_access($courseid, $cmid, $userid) {
    try {
        $modinfo = get_fast_modinfo($courseid, $userid);
        $cm = $modinfo->get_cm($cmid);
    } catch (Exception $e) {
        throw new moodle_exception($e->getMessage());
    }
    if (isset($cm) && $cm->uservisible == true) {
        return true;
    }

    return false;
}

/**
 * @param $userid
 * @throws moodle_exception
 */
function wooflash_redirect_auth($userid) {
    global $DB, $SESSION;

    wooflash_ask_consent_if_not_given();

    if (!wooflash_isValidCallbackUrl($SESSION->wooflash_callback)) {
        print_error('error-invalid-callback-url', 'wooflash');
    }

    if (!isset($SESSION->wooflash_courseid) || !isset($SESSION->wooflash_cmid) || !isset($SESSION->wooflash_callback)) {
        print_error('error-missingparameters', 'wooflash');
        header("HTTP/1.0 401");
    }

    try {
        $cm = get_coursemodule_from_id('wooflash', $SESSION->wooflash_cmid);
        $course_context = context_course::instance($cm->course);
        $userdb = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $activity = $DB->get_record('wooflash', ['id' => $cm->instance], '*', MUST_EXIST);
        $accesskeyid = get_config('wooflash', 'accesskeyid');
    } catch (Exception $e) {
        print_error('error-couldnotauth', 'wooflash');
    }

    $role = wooflash_get_role($course_context);
    $ts = wooflash_get_isotime();
    $hasAccess = wooflash_check_activity_user_access($SESSION->wooflash_courseid, $SESSION->wooflash_cmid, $userid);

    $data_token = [
        'accessKeyId' => $accesskeyid,
        'hasAccess' => $hasAccess,
        'moodleUsername' => $userdb->username,
        'role' => $role,
        'ts' => $ts,
        'version' => get_config('mod_wooflash')->version,
        'wooflashEventSlug' => $activity->linkedwooflasheventslug,
    ];

    $data = [
        'moodleUsername' => $userdb->username,
        'displayName' => $userdb->firstname . ' ' . $userdb->lastname,
        'firstName' => $userdb->firstname,
        'lastName' => $userdb->lastname,
        // Only add the email if the user has consented
        'email' => $SESSION->hasConsented ? $userdb->email : '',
        'role' => $role,
        'hasAccess' => $hasAccess,
        'accessKeyId' => $accesskeyid,
        'ts' => $ts,
        'token' => wooflash_generate_token('AUTHv3?' . wooflash_http_build_query($data_token)),
        'version' => get_config('mod_wooflash')->version,
        'wooflashEventSlug' => $activity->linkedwooflasheventslug,
    ];

    $callback_url = wooflash_validate_callback_url($SESSION->wooflash_callback);

    redirect($callback_url . '?' . wooflash_http_build_query($data));
}

/**
 * @throws moodle_exception
 */
function wooflash_ask_consent_if_not_given($redirectUrl = null, $role = null) {
    global $CFG, $DB, $SESSION;

    $showConsentScreen = get_config('wooflash', 'showconsentscreen');

    // Consider that consent has been obtained otherwise if the consent screen
    // is not shown or if it's a teacher.
    if (!$showConsentScreen || $role == 'teacher') {
        $SESSION->hasConsented = true;
    }

    // If the user has not consented yet, redirect them to the consent screen.
    if (!isset($SESSION->hasConsented)) {
        redirect(
            new moodle_url(
                '/mod/wooflash/wooflash_consent_screen.php',
                ['redirectUrl' => $redirectUrl]
            )
        );
    }
}

/**
 * Perform a PING with the Wooflash server based on the plugin settings
 * @throws moodle_exception
 * @return bool true if the current settings are accepted by Wooflash
 */
function wooflash_get_ping_status() {
    // Generate a token based on necessary parameters.

    $ts = wooflash_get_isotime();

    try {
        $accesskeyid = get_config('wooflash', 'accesskeyid');
    } catch (Exception $e) {
        // Could not get access key id => ping can be considered failed.
        return false;
    }

    $data_token = [
        'accessKeyId' => $accesskeyid,
        'ts' => $ts,
        'version' => get_config('mod_wooflash')->version,
    ];
    $data = [
        'accessKeyId' => $accesskeyid,
        'ts' => $ts,
        'token' => wooflash_generate_token('PING?' . wooflash_http_build_query($data_token)),
        'version' => get_config('mod_wooflash')->version,
    ];

    $ping_url = wooflash_get_ping_url();

    // Use curl to make a call and check the result.

    $curl = new wooflash_curl();
    $headers = [];
    $headers[0] = "Content-Type: application/json";
    $headers[1] = "X-Wooflash-PluginVersion: " . get_config('mod_wooflash')->version;
    $curl->setHeader($headers);
    $response = $curl->get(
        $ping_url . '?' . wooflash_http_build_query($data)
    );
    $curlinfo = $curl->info;

    if (!$response || !is_array($curlinfo) || $curlinfo['http_code'] != 200) {
        return false;
    }

    $response_data = json_decode($response);
    return $response_data->keysAreValid;
}

/**
 * @param $course_context
 * @return string
 */
function wooflash_get_role($course_context) {
    if ($course_context and has_capability('moodle/course:update', $course_context)) {
        $role = 'teacher';
    } else {
        $role = 'student';
    }
    return $role;
}

/**
 * @param string $callback_url
 * @return string
 */
function wooflash_validate_callback_url($callback_url) {
    if (strpos($callback_url, 'https://') === false) {
        $callback_url = 'https://' . $callback_url;
    }
    if (!filter_var($callback_url, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
        print_error('error-callback-is-not-url', 'wooflash');
    }
    return $callback_url;
}

/**
 * @param $wooflashinstance
 * @param $userid
 * @param $gradeval
 * @param $completionstatus
 * @return bool
 * @throws dml_exception
 */
function wooflash_update_grade($wooflashinstance, $userid, $gradeval, $completionstatus) {
    global $CFG, $DB;
    require_once $CFG->libdir . '/gradelib.php';

    $grade = new stdClass();
    $grade->userid = $userid;

    // Depending on the maximum grade value, we should adapt the grade
    // Wooflash grades are based on 100.

    // 1 - trying to fetch the max grade from the course itself
    $params = [
        'courseid' => $wooflashinstance->course,
        'itemtype' => 'mod',
        'itemmodule' => 'wooflash',
        'iteminstance' => $wooflashinstance->id,
        'itemnumber' => 0
    ];
    if ($grade_item = grade_item::fetch($params)) {
        $maxgrade = $grade_item->grademax;
    }

    // 2 - if nothing defined, trying from the global configuration
    if (!$maxgrade) {
        $maxgrade = (int)get_config('core', 'gradepointdefault');
    }

    // 3 - else hardcode to 100
    if(!$maxgrade) {
        $maxgrade = 100;
    }

    $grade->rawgrade = ($gradeval * $maxgrade) / 100;

    $status = grade_update(
        'mod/wooflash',
        $wooflashinstance->course,
        'mod',
        'wooflash',
        $wooflashinstance->id,
        0,
        $grade,
        ['itemname' => $wooflashinstance->name]
    );

    $record = $DB->get_record('wooflash_completion', ['wooflashid' => $wooflashinstance->id, 'userid' => $userid], 'id');
    if ($record) {
        $id = $record->id;
    } else {
        $id = null;
    }
    $time = time();

    if (!empty($id)) {
        $DB->update_record('wooflash_completion', [
            'id' => $id,
            'timemodified' => $time,
            'grade' => $gradeval,
            'completionstatus' => $completionstatus,
        ]);
    } else {
        $DB->insert_record('wooflash_completion', [
            'wooflashid' => $wooflashinstance->id,
            'userid' => $userid,
            'timecreated' => $time,
            'timemodified' => $time,
            'grade' => $gradeval,
            'completionstatus' => $completionstatus,
        ]);
    }

    return $status == GRADE_UPDATE_OK;
}

/**
 * @return string
 */
function wooflash_get_isotime() {
    $date = new \DateTime("now", new \DateTimeZone("UTC"));
    return $date->format('Y-m-d\TH:i:s\Z');
}

/**
 * @param string $src The Wooflash link will be shown inside the iframe block
 * @param bool $noHtmlBlock If true, it will show the content without the HTML
 * block. Only the iframe. This value is usually defined as true by the
 * observer.php:course_module_created method.
 *
 * We have noticed that in some Moodle instances (e.g our Bitnami staging
 * environment), the teacher is not redirected to the activity URL when clicking
 * on "Save and display". Instead, they stay on "/course/modedit.php" and the
 * iframe is injected onto that page.
 *
 * To avoid having multiple <html /> element on the page, we have to add this
 * parameter.
 */
function wooflash_frame_view($src, $noHtmlBlock=false) {

    $iframe = '<script>window.location.href = "' . $src . '";</script><a href="' . $src . '">'.get_string('wooflashredirect', 'wooflash').'</a>';

    if ($noHtmlBlock) {
        echo $iframe;
    } else {
        echo '<html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Wooflash</title>
</head>
<body>
' . $iframe . '
  </body>
  </html>';
    }
}

/**
 * @param $course
 * @param $cm
 * @param $userid
 * @param $type
 * @return bool
 * @throws dml_exception
 */
function wooflash_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    $wooflash = $DB->get_record('wooflash', array('id' => $cm->instance), '*',
        MUST_EXIST);

    $result = $type; // Default return value.
    // If completion option is enabled, evaluate it and return true/false.
    if ($wooflash->customcompletion) {
        $value = $DB->record_exists('wooflash_completion', array(
            'wooflashid' => $wooflash->id, 'userid' => $userid, 'completionstatus' => 2));
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    return $result;
}

/**
 * Create/update grade item for given wooflash activity
 *
 * @param $wooflashinstance
 * @param null $grades
 * @return int
 */
function wooflash_grade_item_update($wooflashinstance, $grades = null) {
    global $CFG;
    if (!function_exists('grade_update')) {
        // We use a workaround for buggy PHP versions.
        require_once $CFG->libdir . '/gradelib.php';
    }
    return grade_update(
        'mod/wooflash',
        $wooflashinstance->course,
        'mod',
        'wooflash',
        $wooflashinstance->id,
        0,
        $grades,
        ['itemname' => $wooflashinstance->name]
    );
}

/**
 * Add a get_coursemodule_info function in case to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 *
 * @see https://github.com/wooflash/moodle-mod_wooflash/issues/1#issuecomment-957577514
 */
function wooflash_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat';
    if (!$wooflash = $DB->get_record('wooflash', $dbparams, $fields)) {
        return false;
    }

    $info = new cached_cm_info();
    $info->name = $wooflash->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $info->content = format_module_intro('wooflash', $wooflash, $coursemodule->id, false);
    }

    $url = new moodle_url('/mod/wooflash/view.php', ['id' => $coursemodule->id, 'redirect' => 1]);
    $fullurl = $url->out();
    $info->onclick = "window.open('$fullurl'); return false;";

    return $info;
}

/**
 * Get questions from database shema before the Moodle V4 version
 * Warning: From V4 version, questions table structure has changed
 *
 * @param $quiz_id Quiz Id
 *
 * @return array List of questions from a quiz
 */
function wooflash_load_questions_before_v4($quiz_id){
    global $DB;

    // Fetch the quiz slots.
    $quiz_slots = $DB->get_records('quiz_slots', ['quizid' => $quiz_id]);
    // Create an array with all the question ids.
    $question_ids = array_map(
        function ($elem) {
            return $elem->questionid;
        },
        $quiz_slots
    );
    // Get the list of questions for the quiz.
    $questions = $DB->get_records_list('question', 'id', $question_ids);

    return $questions;
}

/**
 * Get questions from database shema after the Moodle V4 version
 * Warning: From V4 version, questions table structure has changed
 *
 * @param $quiz_id Quiz Id
 *
 * @return array List of questions from a quiz
 */
function wooflash_load_questions_for_v4($quiz_id){
    global $DB;

    $questions = $DB->get_records_sql('
                        SELECT
                            q.*,
                            qbe.questioncategoryid AS category
                        FROM
                            {quiz_slots} qs
                                INNER JOIN {question_references} qr
                                    ON  qs.id = qr.itemid
                                    AND qr.component = :component
                                    AND qr.questionarea = :questionarea
                                INNER JOIN {question_bank_entries} qbe
                                    ON qr.questionbankentryid = qbe.id
                                INNER JOIN {question_versions} qv
                                    ON qbe.id = qv.questionbankentryid
                                    AND qv.version  = (
                                                        SELECT MAX(version)
                                                        FROM {question_versions}
                                                        WHERE  questionbankentryid = qv.questionbankentryid
                                                    )
                                INNER JOIN {question} q
                                    ON qv.questionid = q.id
                        WHERE
                            qs.quizid = :quizid',
                        [
                            'component' => 'mod_quiz',
                            'questionarea' => 'slot',
                            'quizid' => $quiz_id
                        ]
                    );
    return $questions;
}

/**
 * Function to read all questions for quiz into big array
 *
 * @param int $quiz quiz id
 */
function wooflash_get_questions_quiz($quiz, $export = true) {

    $branch = get_config('moodle', 'branch');
    $questions = null;

    // Get the list of questions for the quiz.
    // When Moodle version is < v4
    if(strnatcmp($branch, '400') == -1) {
        $questions = wooflash_load_questions_before_v4($quiz);
    }
    // When Moodle version is >= v4
    else {
        $questions = wooflash_load_questions_for_v4($quiz);
    }

    // Iterate through questions, getting stuff we need.
    $qresults = array();

    foreach ($questions as $key => $question) {
        $question->export_process = $export;
        $qtype = question_bank::get_qtype($question->qtype, false);
        if ($export && $qtype->name() == 'missingtype') {
            // Unrecognised question type. Skip this question when exporting.
            continue;
        }
        $qtype->get_question_options($question);
        $qresults[] = $question;
    }

    return $qresults;
}

/**
 * Check if the callback url is safe and known
 * @param string $callbackUrl
 * @return bool
 */
function wooflash_isValidCallbackUrl($callbackUrl)
{
    $baseurl = trim(get_config('wooflash', 'baseurl'), '/');
    return $callbackUrl != null && strpos($callbackUrl, $baseurl) === 0;
}
