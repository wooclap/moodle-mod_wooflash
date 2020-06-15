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

require_once $CFG->dirroot . '/course/moodleform_mod.php';
require_once $CFG->dirroot . '/mod/wooflash/lib.php';

class mod_wooflash_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG, $DB, $COURSE, $USER;

        $mform = &$this->_form;

        // Add Name input.
        $mform->addElement('text', 'name', get_string('name'), ['size' => '48']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule(
            'name', get_string('maximumchars', '', 255),
            'maxlength', 255, 'client'
        );

        // Add Description input.
        $this->standard_intro_elements(get_string('wooflashintro', 'wooflash'));
        $mform->setAdvanced('introeditor');

        // Show Description
        // Display the label to the right of the checkbox so it looks better
        // ...and matches rest of the form.
        if ($mform->elementExists('showdescription')) {
            $coursedesc = $mform->getElement('showdescription');
            if (!empty($coursedesc)) {
                $coursedesc->setText(' ' . $coursedesc->getLabel());
                $coursedesc->setLabel('&nbsp');
            }
        }

        // Add Quiz dropdown.
        $quizid = 0;
        if (isset($this->_cm)) {
            $wooflashid = $this->_cm->instance;
            if ($wooflashid) {
                $wooflash = $DB->get_record('wooflash', ['id' => $wooflashid]);
                $quizid = $wooflash->quiz;
            }
        }
        $quizz_db = $DB->get_records('quiz', ['course' => $COURSE->id]);
        $quizz = [];
        $quizz[0] = get_string('none');
        foreach ($quizz_db as $quiz_db) {
            $quizz[$quiz_db->id] = $quiz_db->name;
        }
        $mform->addElement('select', 'quiz', get_string('quiz', 'wooflash'), $quizz);
        $mform->setType('quiz', PARAM_INT);
        if ($quizid > 0) {
            $mform->setDefault('quiz', $quizid);
        }

        // Fetch a list of the user's Wooflash courses from the Wooflash API
        // ...so that the user can choose to copy an existing course.
        $ts = wooflash_get_isotime();
        try {
            $accesskeyid = get_config('wooflash', 'accesskeyid');
        } catch (Exception $exc) {
            echo $exc->getMessage();
        }
        try {
            $coursesListUrl = wooflash_get_courses_list_url();
        } catch (Exception $exc) {
            echo $exc->getMessage();
        }

        $data_token = [
            'accessKeyId' => $accesskeyid,
            'email' => $USER->email,
            'firstName' => $USER->firstname,
            'lastName' => $USER->lastname,
            'moodleUserId' => intval($USER->id),
            'ts' => $ts,
            'version' => get_config('mod_wooflash')->version,
        ];

        $curl_data = new StdClass;
        $curl_data->accessKeyId = $accesskeyid;
        $curl_data->moodleUserId = intval($USER->id);
        $curl_data->email = $USER->email;
        $curl_data->firstName = $USER->firstname;
        $curl_data->lastName = $USER->lastname;
        $curl_data->ts = $ts;
        $curl_data->token = wooflash_generate_token(
            'COURSES_LIST?' . wooflash_http_build_query($data_token)
        );
        $curl_data->version = get_config('mod_wooflash')->version;

        $curl = new wooflash_curl();
        $headers = [];
        $headers[0] = "Content-Type: application/json";
        $headers[1] = "X-Wooflash-PluginVersion: " . get_config('mod_wooflash')->version;
        $curl->setHeader($headers);
        $response = $curl->get(
            $coursesListUrl . '?' . wooflash_http_build_query($curl_data)
        );
        $curlinfo = $curl->info;

        $wooflash_courses = [];
        $wooflash_courses['none'] = get_string('none');
        if ($response && is_array($curlinfo) && $curlinfo['http_code'] == 200) {
            foreach (json_decode($response) as $w_course) {
                $wooflash_courses[$w_course->_id] = $w_course->name;
            }

        } else {
            print_error('error-couldnotloadcourses', 'wooflash');
        }

        $mform->addElement(
            'select',
            'wooflashcourseid',
            get_string('wooflashcourseid', 'wooflash'),
            $wooflash_courses
        );
        $mform->setType('wooflashcourseid', PARAM_TEXT);
        $mform->setDefault('wooflashcourseid', 'none');

        // Set default options.
        $this->standard_coursemodule_elements();

        $this->apply_admin_defaults();

        $this->add_action_buttons();
    }

    /**
     * Add elements for setting the custom completion rules.
     *
     * @category completion
     * @return array List of added element names, or names of wrapping group elements.
     * @throws coding_exception
     */
    public function add_completion_rules() {

        $mform = $this->_form;

        $group = [
            $mform->createElement(
                'checkbox',
                'customcompletion',
                ' ',
                get_string('customcompletion', 'wooflash')
            ),
        ];
        $mform->setType('customcompletion', PARAM_BOOL);
        $mform->addGroup(
            $group,
            'customcompletiongroup',
            get_string('customcompletiongroup', 'wooflash'),
            [' '],
            false
        );
        $mform->disabledIf('customcompletion', 'completion', 'in', [0, 1]);

        return ['customcompletiongroup'];
    }

    /**
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return ($data['customcompletion'] != 0);
    }

    /**
     * @param array $default_values
     */
    public function data_preprocessing(&$default_values) {
        parent::data_preprocessing($default_values);

        if (empty($default_values['customcompletion'])) {
            $default_values['customcompletion'] = 1;
        }
        $default_values['completion'] = 2;
    }
}
