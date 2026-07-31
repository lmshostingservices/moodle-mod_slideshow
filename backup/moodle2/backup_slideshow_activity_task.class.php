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
 * Defines the backup task for the slideshow activity.
 *
 * @package     mod_slideshow
 * @copyright   2025 National Corporate Training Pty Ltd (https://nct.net.au/) <believe@nct.net.au>
 * @author      LMSACE Dev Team <lmsace.com>
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/slideshow/backup/moodle2/backup_slideshow_stepslib.php');

/**
 * Defines the backup task for the slideshow activity.
 */
class backup_slideshow_activity_task extends backup_activity_task {

    /**
     * No specific settings for this backup task.
     */
    protected function define_my_settings() {
    }

    /**
     * Defines the steps that will be executed by the task.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_slideshow_activity_structure_step('slideshow_structure', 'slideshow.xml'));
    }

    /**
     * This module does not have any special content links to encode.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        return $content;
    }
}
