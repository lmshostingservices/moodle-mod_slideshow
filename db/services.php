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
 * mod_slideshow file.
 *
 * @package    mod_slideshow
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'mod_slideshow_view_slide' => [
        'classname'   => 'mod_slideshow\external',
        'methodname'  => 'view_slide',
        'description' => 'Records the highest slide number a user has viewed and handles completion.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'mod/slideshow:view',
    ],
    'mod_slideshow_reorder' => [
        'classname'   => 'mod_slideshow\external\reorder',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Reorder slideshow images and extra slides by key (pathnamehash, filename, or extra_NNN)',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'mod/slideshow:manage',
    ],
    'mod_slideshow_generate_voiceover' => [
        'classname'   => 'mod_slideshow\external\generate_voiceover',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Generate AI voiceover for a single slide image using OCR + Chirp 3 HD TTS',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'mod/slideshow:manage',
    ],
    'mod_slideshow_generate_all_voiceovers' => [
        'classname'   => 'mod_slideshow\external\generate_all_voiceovers',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Generate AI voiceovers for all slides in a slideshow',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'mod/slideshow:manage',
    ],
    'mod_slideshow_save_slide_text' => [
        'classname'   => 'mod_slideshow\external\save_slide_text',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Save custom voiceover text for a single slide',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'mod/slideshow:manage',
    ],
    'mod_slideshow_upload_draft_file' => [
        'classname'   => 'mod_slideshow\external\upload_draft_file',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Upload a file directly to user draft area (bypasses repository system)',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'mod/slideshow:manage',
    ],
    'mod_slideshow_toggle_voiceover_disabled' => [
        'classname'   => 'mod_slideshow\external\toggle_voiceover_disabled',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Enable or disable voiceover for a single slide (skips generation and hides playback controls)',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'mod/slideshow:manage',
    ],
    'mod_slideshow_save_extra_slide' => [
        'classname'   => 'mod_slideshow\external\save_extra_slide',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Create or update a video or poster extra slide and insert at the chosen position',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'mod/slideshow:manage',
    ],
    'mod_slideshow_delete_extra_slide' => [
        'classname'   => 'mod_slideshow\external\delete_extra_slide',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Delete a video or poster extra slide and compact sortorders',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'mod/slideshow:manage',
    ],
];
