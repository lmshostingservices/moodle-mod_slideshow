<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_slideshow_upgrade($oldversion) {
    if ($oldversion < 2026072300) {
        upgrade_mod_savepoint(true, 2026072300, 'slideshow');
    }
    return true;
}
