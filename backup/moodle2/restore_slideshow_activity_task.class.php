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
 * Defines the restore task for the slideshow activity.
 *
 * @package     mod_slideshow
 * @copyright   2025 National Corporate Training Pty Ltd (https://nct.net.au/) <believe@nct.net.au>
 * @author      LMSACE Dev Team <lmsace.com>
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/slideshow/backup/moodle2/restore_slideshow_stepslib.php');

/**
 * Defines the restore task for the slideshow activity.
 */
class restore_slideshow_activity_task extends restore_activity_task {
    /**
     * No specific settings for this restore task.
     */
    protected function define_my_settings() {
    }

    /**
     * Defines the steps that will be executed by the task.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_slideshow_activity_structure_step('slideshow_structure', 'slideshow.xml'));
    }


    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    public static function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('slideshow', array('intro'), 'slideshow');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    public static function define_decode_rules() {
        return array();
    }
}
