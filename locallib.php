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
// More info: https://docs.moodle.org/dev/Upgrade_API .

defined('MOODLE_INTERNAL') || die;

require_once $CFG->dirroot . '/mod/wooflash/classes/wooflash_curl.php';
require_once $CFG->dirroot . '/mod/wooflash/lib.php';

/**
 * Perform v3 upgrade with wooflash server
 * @throws dml_exception
 * @throws moodle_exception
 * TODO check, if possible, if V3 upgrade already performed on remote Wooflash
 */
function mod_wooflash_v3_upgrade()
{
    global $DB;

    $curl = new wooflash_curl();
    $headers = [];
    $headers[0] = "Content-Type: application/json";
    $headers[1] = sprintf("X-Wooflash-PluginVersion: %s", get_config('mod_wooflash')->version);
    $curl->setHeader($headers);

    $ts = wooflash_get_isotime();

    try {
        $accesskeyid = get_config('wooflash', 'accesskeyid');
        $version = get_config('mod_wooflash')->version;
        $baseurl = get_config('wooflash', 'baseurl');
    } catch (Exception $exc) {
        echo $exc->getMessage();
    }

    $baseurl = trim($baseurl, '/');

    // STEP 1.
    $v3upgradestep1url = sprintf("%s/api/moodle/v3/upgrade-step-1", $baseurl);
    $step1datatoken = [
        'accessKeyId' => $accesskeyid,
        'ts' => $ts,
        'version' => $version,
    ];

    $curl_data_step1 = new StdClass;
    $curl_data_step1->accessKeyId = $accesskeyid;
    $curl_data_step1->ts = $ts;
    $curl_data_step1->token = wooflash_generate_token(
        'V3_UPGRADE_STEP_1?' . wooflash_http_build_query($step1datatoken)
    );
    $curl_data_step1->version = $version;

    $response = $curl->get(
        $v3upgradestep1url . '?' . wooflash_http_build_query($curl_data_step1)
    );
    $curlinfo = $curl->info;

    if ($response && is_array($curlinfo) && $curlinfo['http_code'] == 200) {
        // STEP 2.
        $idsToUsernamesMapping = [];

        foreach (json_decode($response) as $moodleuserid) {
            $user = $DB->get_record(
                'user',
                ['id' => $moodleuserid]
            );
            if ($user) {
              $idsToUsernamesMapping[$moodleuserid] = $user->username;
            }
        }

        $jsonmapping = json_encode($idsToUsernamesMapping);

        $v3upgradestep2url = sprintf("%s/api/moodle/v3/upgrade-step-2", $baseurl);
        $step2datatoken = [
            'accessKeyId' => $accesskeyid,
            'idsToUsernamesMapping' => $jsonmapping,
            'ts' => $ts,
            'version' => $version,
        ];

        $curl_data_step2 = new StdClass;
        $curl_data_step2->accessKeyId = $accesskeyid;
        $curl_data_step2->idsToUsernamesMapping = $jsonmapping;
        $curl_data_step2->ts = $ts;
        $curl_data_step2->token = wooflash_generate_token(
            'V3_UPGRADE_STEP_2?' . wooflash_http_build_query($step2datatoken)
        );
        $curl_data_step2->version = $version;

        $response = $curl->post(
            $v3upgradestep2url, json_encode($curl_data_step2)
        );
        $curlinfo = $curl->info;

        if (!$response || !is_array($curlinfo) || $curlinfo['http_code'] != 200) {
            throw new \moodle_exception('error-couldnotperformv3upgradestep2', 'wooflash');
        }
    } else {
        throw new \moodle_exception('error-couldnotperformv3upgradestep1', 'wooflash');
    }
}
