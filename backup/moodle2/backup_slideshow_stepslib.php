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
 * Defines the structure of the slideshow activity backup steps.
 *
 * @package     mod_slideshow
 * @copyright   2025 National Corporate Training Pty Ltd (https://nct.net.au/) <believe@nct.net.au>
 * @author      LMSACE Dev Team <lmsace.com>
 */
defined('MOODLE_INTERNAL') || die();

class backup_slideshow_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        $userinfo = $this->get_setting_value('userinfo');

        $slideshow = new backup_nested_element('slideshow', ['id'], [
            'name', 'intro', 'introformat', 'containerheight', 'slideratio',
            'completionallslides',
            'enablevoiceover', 'voicelanguage', 'voicegender', 'voicestyle',
            'requirevoiceover', 'voiceoverstatus',
            'timemodified'
        ]);

        $voiceovers = new backup_nested_element('voiceovers');
        $voiceover = new backup_nested_element('voiceover', ['id'], [
            'slideshowid', 'filename', 'contenthash', 'ocrtext', 'customtext',
            'voicelanguage', 'voicegender', 'voicestyle',
            'duration', 'audiohash', 'status', 'errortext', 'creditscharged',
            'timecreated', 'timemodified',
        ]);

        $slideshow->add_child($voiceovers);
        $voiceovers->add_child($voiceover);

        // Extra slides: video slides (YouTube / direct URL / uploaded file) and poster image slides.
        $extraslides = new backup_nested_element('extra_slides');
        $extraslide = new backup_nested_element('extra_slide', ['id'], [
            'slideshowid', 'sortorder', 'slidetype', 'title',
            'videosource', 'videourl', 'videominwatch',
            'imagefilename', 'timecreated', 'timemodified',
        ]);

        $slideshow->add_child($extraslides);
        $extraslides->add_child($extraslide);

        $slideshowviews = new backup_nested_element('slideshowviews');
        $slideshowviewscompletion = new backup_nested_element('slideshow_completion', ['id'], [
            'instanceid', 'userid', 'slidecount', 'viewed', 'completion',
            'timecreated', 'timemodified',
        ]);

        $slideshow->add_child($slideshowviews);
        $slideshowviews->add_child($slideshowviewscompletion);

        $slideshow->set_source_table('slideshow', ['id' => backup::VAR_ACTIVITYID]);

        $voiceover->set_source_table('slideshow_voiceovers', ['slideshowid' => backup::VAR_PARENTID]);

        $extraslide->set_source_table('slideshow_extra_slides', ['slideshowid' => backup::VAR_PARENTID]);

        if ($userinfo) {
            $sql = 'SELECT co.* FROM {slideshow} cc
            JOIN {slideshow_completion} co ON co.instanceid=cc.id
            WHERE cc.id=:id';

            $slideshowviewscompletion->set_source_sql($sql, ['id' => backup::VAR_PARENTID]);
            $slideshowviewscompletion->annotate_ids('user', 'userid');
        }

        $slideshow->annotate_files('mod_slideshow', 'intro', null);
        $slideshow->annotate_files('mod_slideshow', 'slideimages', null);
        $slideshow->annotate_files('mod_slideshow', 'slidevoiceovers', null);
        // Poster images and uploaded video files are stored per extra_slide row (itemid = extra_slide.id).
        $extraslide->annotate_files('mod_slideshow', 'slideposterimages', 'id');
        $extraslide->annotate_files('mod_slideshow', 'slidevideofiles', 'id');

        return $this->prepare_activity_structure($slideshow);
    }
}
