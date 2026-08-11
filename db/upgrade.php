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
 * Upgrade steps for the slideshow module.
 *
 * @package     mod_slideshow
 * @copyright   2025 National Corporate Training Pty Ltd (https://nct.net.au/) <believe@nct.net.au>
 * @author      LMSACE Dev Team <lmsace.com>
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for the slideshow module.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool True if the upgrade was successful, false otherwise.
 */
function xmldb_slideshow_upgrade($oldversion) {
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025062802) {
        $table = new xmldb_table('slideshow');
        $field = new xmldb_field('containerheight', XMLDB_TYPE_INTEGER, '9', null, null, null, null, 'completionallslides');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2025062802, 'slideshow');
    }

    if ($oldversion < 2026021101200) {
        $table = new xmldb_table('slideshow_completion');
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'completion');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('instanceid_userid', XMLDB_INDEX_UNIQUE, ['instanceid', 'userid']);
        if (!$dbman->index_exists($table, $index)) {
            // Remove duplicate (instanceid, userid) rows before creating the unique index.
            // Keep only the row with the highest id for each pair.
            $pairs = $DB->get_records_sql(
                'SELECT MAX(id) AS maxid, instanceid, userid
                   FROM {slideshow_completion}
                  GROUP BY instanceid, userid
                 HAVING COUNT(*) > 1'
            );
            foreach ($pairs as $pair) {
                $DB->delete_records_select(
                    'slideshow_completion',
                    'instanceid = :instanceid AND userid = :userid AND id <> :maxid',
                    ['instanceid' => $pair->instanceid, 'userid' => $pair->userid, 'maxid' => $pair->maxid]
                );
            }
            $dbman->add_index($table, $index);
        }

        upgrade_mod_savepoint(true, 2026021101200, 'slideshow');
    }

    if ($oldversion < 2026021101300) {
        $table = new xmldb_table('slideshow');

        $fields = [
            new xmldb_field('enablevoiceover', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'containerheight'),
            new xmldb_field('voicelanguage', XMLDB_TYPE_CHAR, '10', null, null, null, 'en-AU', 'enablevoiceover'),
            new xmldb_field('voicegender', XMLDB_TYPE_CHAR, '10', null, null, null, 'female', 'voicelanguage'),
            new xmldb_field('voicestyle', XMLDB_TYPE_CHAR, '20', null, null, null, 'Aoede', 'voicegender'),
            new xmldb_field('requirevoiceover', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'voicestyle'),
            new xmldb_field('voiceoverstatus', XMLDB_TYPE_CHAR, '20', null, null, null, 'none', 'requirevoiceover'),
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $votable = new xmldb_table('slideshow_voiceovers');
        if (!$dbman->table_exists($votable)) {
            $votable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $votable->add_field('slideshowid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $votable->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $votable->add_field('contenthash', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $votable->add_field('ocrtext', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $votable->add_field('voicelanguage', XMLDB_TYPE_CHAR, '10', null, null, null, null);
            $votable->add_field('voicegender', XMLDB_TYPE_CHAR, '10', null, null, null, null);
            $votable->add_field('voicestyle', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $votable->add_field('duration', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
            $votable->add_field('audiohash', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $votable->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
            $votable->add_field('errortext', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $votable->add_field('creditscharged', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $votable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $votable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

            $votable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $votable->add_index('slideshowid_filename', XMLDB_INDEX_UNIQUE, ['slideshowid', 'filename']);

            $dbman->create_table($votable);
        }

        upgrade_mod_savepoint(true, 2026021101300, 'slideshow');
    }

    if ($oldversion < 2026021101400) {
        $table = new xmldb_table('slideshow');
        $field = new xmldb_field('slideratio', XMLDB_TYPE_CHAR, '10', null, null, null, '16:9', 'containerheight');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026021101400, 'slideshow');
    }

    if ($oldversion < 2026021101460) {
        $table = new xmldb_table('slideshow_voiceovers');
        $field = new xmldb_field('customtext', XMLDB_TYPE_TEXT, null, null, null, null, null, 'ocrtext');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026021101460, 'slideshow');
    }

    if ($oldversion < 2026021501619) {
        // v1.6.19 — Extracted hardcoded credit cost (5 credits per slide voiceover) into
        // generate_voiceover::CREDITS_PER_VOICEOVER class constant. Referenced from
        // generate_voiceover.php, generate_all_voiceovers.php, and mod_form.php.
        // No DB schema changes. No behaviour change.
        upgrade_mod_savepoint(true, 2026021501619, 'slideshow');
    }

    // v1.6.20: SYNC FIX — version.php was at 2026021501620 without a corresponding
    //   upgrade.php savepoint. Added to bring DB version in sync. No DB schema changes.
    if ($oldversion < 2026021501620) {
        upgrade_mod_savepoint(true, 2026021501620, 'slideshow');
    }

    // v1.6.21: FIX — Removed position:relative override on .navbar in styles.css.
    //   The previous z-index fix was inadvertently setting position:relative on the Moodle
    //   Boost navbar, which stripped its position:sticky behaviour. On scroll, the header
    //   drifted down and the uploaded site logo overlapped the course-index sidebar drawer.
    //   Fix: .navbar now only receives z-index:1030; position is left to the Boost theme.
    //   No DB schema changes.
    if ($oldversion < 2026040201621) {
        upgrade_mod_savepoint(true, 2026040201621, 'slideshow');
    }
    // v1.6.22: AMD ENCODING FIX: All non-ASCII characters (em dashes, arrows, box-drawing chars, ellipsis, bullets, emoji, accented Latin) scrubbed from all AMD JS files (amd/src, amd/build, amd/build/*.min.js). Root cause of Moodle primary/secondary navigation menus disappearing site-wide: non-ASCII bytes in any installed plugin's AMD file cause a SyntaxError inside RequireJS's first.js bundle, throwing "No define call for core/first" and aborting the entire AMD module chain. No PHP, DB schema, or functional changes in this release.
    if ($oldversion < 2026042200022) {
        upgrade_mod_savepoint(true, 2026042200022, 'slideshow');
    }
    // v1.6.23: JS FIX: refreshFileManager() now correctly uses integer draftItemId as
    //   M.form_filemanager.instances key (was using 'f-slideimages' string from a bad
    //   element-ID strip — never matched any instance). File manager now refreshes after
    //   PPTX or image upload. Progress text white-space fixed so all messages are readable.
    //   Success message extended to 20s with "scroll down to check Slideshow Images" guidance.
    //   Fixed 1 slide/N slides grammar. No DB schema changes.
    if ($oldversion < 2026042200023) {
        upgrade_mod_savepoint(true, 2026042200023, 'slideshow');
    }

    // v1.6.24: FEAT: Per-slide voiceover disable toggle. Added voiceover_disabled column to
    //   slideshow_voiceovers table. Teachers can now click a mic toggle button on any slide in
    //   the voiceover panel to skip voiceover generation and hide playback controls for that
    //   slide (e.g. title slides). Generate All also skips disabled slides.
    if ($oldversion < 2026042200024) {
        $table = new \xmldb_table('slideshow_voiceovers');
        $field = new \xmldb_field('voiceover_disabled', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'creditscharged');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026042200024, 'slideshow');
    }

    // v1.6.25: FIX: Added duplicate-row cleanup before the unique index creation on
    //   slideshow_completion(instanceid, userid). Sites where students viewed an activity
    //   more than once could accumulate duplicate rows, causing MySQL to abort the
    //   CREATE UNIQUE INDEX with "Temporary file write failure". The 2026021101200 block
    //   above now deduplicates first, but this savepoint ensures sites that were already
    //   stuck at 2026042200024 also get upgraded cleanly via a version bump.
    if ($oldversion < 2026061700025) {
        upgrade_mod_savepoint(true, 2026061700025, 'slideshow');
    }

    // v1.6.26: FEAT: Video slides and poster (image-only) slides.
    //   New DB table slideshow_extra_slides stores video slides (YouTube URL, direct URL,
    //   or uploaded file) and poster image slides. sortorder is shared with existing image
    //   file sortorders for correct interleaving. New services: mod_slideshow_save_extra_slide
    //   and mod_slideshow_delete_extra_slide. New fileareas: slideposterimages, slidevideofiles.
    //   Video watch gating: 0=none, -1=full video, N=minimum seconds.
    if ($oldversion < 2026061700026) {
        $table = new xmldb_table('slideshow_extra_slides');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',            XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('slideshowid',   XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, null);
            $table->add_field('sortorder',     XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
            $table->add_field('slidetype',     XMLDB_TYPE_CHAR,    '20',  null, XMLDB_NOTNULL, null, 'video');
            $table->add_field('title',         XMLDB_TYPE_CHAR,    '255', null, null,          null, null);
            $table->add_field('videosource',   XMLDB_TYPE_CHAR,    '20',  null, null,          null, null);
            $table->add_field('videourl',      XMLDB_TYPE_TEXT,    null,  null, null,          null, null);
            $table->add_field('videominwatch', XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
            $table->add_field('imagefilename', XMLDB_TYPE_CHAR,    '255', null, null,          null, null);
            $table->add_field('timecreated',   XMLDB_TYPE_INTEGER, '10',  null, null,          null, null);
            $table->add_field('timemodified',  XMLDB_TYPE_INTEGER, '10',  null, null,          null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('slideshowid', XMLDB_INDEX_NOTUNIQUE, ['slideshowid']);
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2026061700026, 'slideshow');
    }

    if ($oldversion < 2026072300231) {
        // FIX-API-DOMAIN: Updated all API endpoint URLs from lms-labs.com to lms-labs.com.
        // lms-labs.com has no DNS resolution from Moodle server side; lms-labs.com is the
        // correct working domain. All ajax.php, api_client, unlock_verifier, lib.php calls updated.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_mod_savepoint(true, 2026072300231, 'slideshow');
    }

    if ($oldversion < 2026072300232) {
        // FIX-API-DOMAIN: Reverted API endpoint to lms-labs.com (correct domain).
        // essaygraderai.app was the original single-plugin domain; lms-labs.com is correct.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_mod_savepoint(true, 2026072300232, 'slideshow');
    }

    if ($oldversion < 2026072300233) {
        // Domain update: lms-labs.com → lms-labs.com
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'lib.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_mod_savepoint(true, 2026072300233, 'slideshow');
    }

    return true;
}