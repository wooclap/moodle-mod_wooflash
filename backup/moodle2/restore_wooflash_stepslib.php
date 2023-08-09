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

/**
 * @package mod_wooflash
 * @copyright  2018 CBlue sprl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once __DIR__ . '/../../../../config.php';

/**
 * Structure step to restore one wooflash activity
 */
class restore_wooflash_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('wooflash', '/activity/wooflash');
        if ($userinfo) {
            $paths[] = new restore_path_element('wooflash_completion', '/activity/wooflash/completions/completion');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function process_wooflash($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        // Insert the wooflash record.
        $newitemid = $DB->insert_record('wooflash', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    protected function process_wooflash_completion($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->wooflashid = $this->get_new_parentid('wooflash');

        $newitemid = $DB->insert_record('wooflash_completion', $data);
        $this->set_mapping('wooflash_completion', $oldid, $newitemid);
    }

    protected function after_execute() {
        // Add choice related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_wooflash', 'intro', null);
    }
}
