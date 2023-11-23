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

// More info: https://docs.moodle.org/dev/Upgrade_API .

defined('MOODLE_INTERNAL') || die;

require_once __DIR__ .  '/../locallib.php';

function xmldb_wooflash_upgrade($oldversion)
{
    global $CFG, $DB, $OUTPUT;

    require_once $CFG->libdir . '/db/upgradelib.php';

    $dbman = $DB->get_manager();

    if ($oldversion < 2023080900) {
        // PART 1 of the V3 upgrade.
        // Perform the two V3_UPGRADE_STEPs with Wooflash
        // so that Wooflash can use the username as identifier instead of the ids.
        // - V3_UPGRADE_STEP_1 will return the list of moodle user ids that have a wooflash account.
        // - V3_UPGRADE_STEP_2 will send a mapping from those ids to the moodle usernames to Wooflash.

        try {
          $accesskeyid = get_config('wooflash', 'accesskeyid');
          $secretaccesskey = get_config('wooflash', 'secretaccesskey');
          $configbaseurl = get_config('wooflash', 'baseurl');
        } catch (Exception $exc) {
          echo $exc->getMessage();
        }
        // Check that plugin is configured.
        if (!empty($accesskeyid) && !empty($secretaccesskey) && !empty($accesskeyid)) {
            mod_wooflash_v3_upgrade();
        } else {
            echo $OUTPUT->notification(get_string('warn-missing-config-during-upgrade-to-v3', 'wooflash'), 'notifyproblem');
        }

        // PART 2 of the V3 upgrade.
        // Upgrade existing wooflash activity records
        // linkedwooflasheventslug must be added
        // editUrl of existing activities must be updated -> /v3/.

        $table = new xmldb_table('wooflash');
        $fieldslug = new xmldb_field('linkedwooflasheventslug', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, null, null);

        if (!$dbman->field_exists($table, $fieldslug)) {
            $dbman->add_field($table, $fieldslug);
        }

        // Upgrade existing wooflash activity records.
        $allwooflashrecords = $DB->get_records('wooflash');
        foreach ($allwooflashrecords as $activity) {
            if (!$activity->linkedwooflasheventslug) {
                $regexMatches = '';
                $slugregex = '/^(.+)\/api\/moodle\/courses\/([^\/]+)\/(.*)$/i';
                if (preg_match($slugregex, $activity->editurl, $regexMatches)) {
                    $baseurl = $regexMatches[1];
                    $eventSlug = $regexMatches[2];
                    $activity->editurl = $baseurl . '/api/moodle/v3/' . $eventSlug . '/join';
                    $activity->linkedwooflasheventslug = $eventSlug;
                    $DB->update_record('wooflash', $activity);
                }
            }
        }
    }

    if ($oldversion < 2023112304) {
        $table = new xmldb_table('wooflash');
        $newfieldId = new xmldb_field('wooflasheventid', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, null, null);
        $oldfieldId = new xmldb_field('wooflashcourseid', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, null, null);

        if ($dbman->field_exists($table, $oldfieldId) && !$dbman->field_exists($table, $newfieldId)) {
            $dbman->rename_field($table, $oldfieldId, 'wooflasheventid');
        } elseif (!$dbman->field_exists($table, $newfieldId)) {
            $dbman->add_field($table, $newfieldId);
        }

        upgrade_mod_savepoint(true, 2023112304, 'wooflash');
    }

    return true;
}
