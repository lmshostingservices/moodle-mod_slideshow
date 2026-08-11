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
 * Defines the structure of the slideshow activity restore steps.
 *
 * @package     mod_slideshow
 * @copyright   2025 National Corporate Training Pty Ltd (https://nct.net.au/) <believe@nct.net.au>
 * @author      LMSACE Dev Team <lmsace.com>
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

class restore_slideshow_activity_structure_step extends restore_activity_structure_step {
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('slideshow', '/activity/slideshow');
        $paths[] = new restore_path_element('slideshow_voiceover', '/activity/slideshow/voiceovers/voiceover');
        $paths[] = new restore_path_element('slideshow_extra_slide', '/activity/slideshow/extra_slides/extra_slide');

        if ($userinfo) {
            $paths[] = new restore_path_element('slideshow_completion',
                '/activity/slideshow/slideshowviews/slideshow_completion');
        }

        return $this->prepare_activity_structure($paths);
    }

    public function process_slideshow($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('slideshow', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_slideshow_voiceover($data) {
        global $DB;

        $data = (object)$data;
        $data->slideshowid = $this->get_new_parentid('slideshow');
        $data->timecreated = $data->timecreated ?? time();
        $data->timemodified = $data->timemodified ?? time();

        $DB->insert_record('slideshow_voiceovers', $data);
    }

    protected function process_slideshow_extra_slide($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->slideshowid = $this->get_new_parentid('slideshow');
        $data->timecreated = $data->timecreated ?? time();
        $data->timemodified = $data->timemodified ?? time();

        $newitemid = $DB->insert_record('slideshow_extra_slides', $data);
        // Pass true so Moodle maps the old itemid -> new itemid for file restore
        // (slideposterimages and slidevideofiles use extra_slide.id as itemid).
        $this->set_mapping('slideshow_extra_slide', $oldid, $newitemid, true);
    }

    protected function process_slideshow_completion($data) {
        global $DB;

        $data = (object)$data;
        $data->instanceid = $this->get_new_parentid('slideshow');
        $data->userid = $this->get_mappingid('user', $data->userid);

        if ($data->userid) {
            $DB->insert_record('slideshow_completion', $data);
        }
    }

    protected function after_execute() {
        $this->add_related_files('mod_slideshow', 'intro', null);
        $this->add_related_files('mod_slideshow', 'slideimages', 'slideshow');
        $this->add_related_files('mod_slideshow', 'slidevoiceovers', 'slideshow');
        // Restore poster images and uploaded video files; itemid maps via slideshow_extra_slide mapping.
        $this->add_related_files('mod_slideshow', 'slideposterimages', 'slideshow_extra_slide');
        $this->add_related_files('mod_slideshow', 'slidevideofiles', 'slideshow_extra_slide');
    }
}
