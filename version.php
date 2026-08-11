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
 * Slideshow v1.6.10 - Version file
 *
 * v1.6.10: FIX: Course ID detection for new activities now uses 4 fallback strategies:
 *          URL ?course= param, M.cfg.courseId, hidden form input, body class course-XX.
 *          Fixes "No course module ID or course ID available" error on add activity page.
 * v1.6.9: FIX: Fixed missing closing brace in upload_draft_file.php that caused
 *         "Unclosed '{' on line 44" PHP parse error, preventing all PPTX slide uploads.
 *         Also fixed PPTX import dropzone alignment: added !important to override
 *         Bootstrap's d-flex !important rule that was preventing label column from hiding.
 * v1.6.8: CRITICAL FIX: PPTX import file upload completely rewritten. Replaced fragile
 *         repository ID discovery (DOM inspection, YUI filepicker, M.form_filemanager) with
 *         a proper web service (mod_slideshow_upload_draft_file) that writes files directly
 *         to the user's draft area via Moodle's file_storage API, bypassing repository_ajax.php
 *         entirely. Supports both existing activities (cmid) and new activities (courseid).
 *         Eliminated ~60% of pptximport.js code (10.9KB -> 5KB minified).
 * v1.6.7: CRITICAL FIX: Slideshow player was overlaying Moodle primary/secondary navigation
 *         due to z-index stacking context collision. The .slideshow-navigation overlay (position:
 *         absolute with top:0 left:0 right:0 bottom:0) combined with .nctslidesshow-content
 *         (position: relative) created a stacking context that visually covered the navbar.
 *         Fix: Added z-index:1 and overflow:hidden to .nctslidesshow-content to contain the
 *         slideshow player within its stacking layer. Added scoped rules for #page-mod-slideshow-view
 *         to reinforce Moodle nav z-index at 1030 on the slideshow view page.
 * v1.6.6: CRITICAL FIX: Resolved CSS minifier corruption that broke site-wide navigation.
 *         MatthiasMullie\Minify was corrupting CSS output due to three constructs:
 *         1) Unicode escape sequences in content property (content: "\22EE\22EE") — replaced
 *            with actual UTF-8 characters to avoid minifier misinterpreting hex boundaries.
 *         2) Nested var() fallbacks (var(--bs-primary, var(--primary, #0d6efd))) — flattened
 *            to single var() as minifier mangles nested parentheses.
 *         3) Multiple CSS declarations on single lines — expanded to one property per line
 *            as minifier may strip semicolons incorrectly during concatenation.
 *         Also added -webkit- vendor prefixes for appearance and user-select properties.
 *         The corrupted CSS caused JavaScript SyntaxError "Unexpected identifier 'flex'"
 *         which prevented RequireJS from loading core/first, leaving navigation at opacity:0.
 * v1.6.5: CRITICAL FIX: Fixed admin navigation hiding on Site Administration pages.
 *         1) settings.php now uses defensive $hassiteconfig && isset($settings) guard
 *            per Moodle best practice — prevents admin tree corruption if $settings undefined.
 *         2) Scoped .ss-handle CSS class to .slideshow-sorter-wrapper to prevent global leak.
 *         3) Removed overflow-clip-margin:unset (modern CSS property unsafe for PHP minifier).
 * v1.6.4: CRITICAL FIX: Removed :has() CSS pseudo-class that corrupted Moodle's CSS cache,
 *         breaking primary and secondary navigation site-wide. Moodle's PHP CSS minifier
 *         (MatthiasMullie\Minify) does not handle :has() — it corrupts the entire
 *         concatenated CSS output, destroying theme styles that follow. Also removed
 *         all !important declarations. Layout handled via JS DOM manipulation instead.
 * v1.6.3: FIX: PowerPoint import UI alignment - robust CSS and JavaScript for full-width dropzone
 *         across Moodle themes. Fixed upload repository ID detection with multi-strategy async
 *         resolver (DOM, M.form_filemanager, YUI filepicker, AJAX fallback).
 * v1.6.2: FIX: Switched slide output from PNG to JPEG with mozjpeg compression (quality 75) and
 *         reduced render DPI from 200 to 150 to keep response under 32MB Cloud Run proxy limit.
 *         Fixes CORS errors on large presentations (60+ slides) where oversized response was
 *         rejected by proxy before CORS headers reached the browser.
 * v1.6.1: FIX: Added 30MB client-side file size validation before PowerPoint upload to prevent
 *         silent CORS failures from production proxy rejecting oversized requests. Improved error
 *         messaging for large files.
 * v1.6.0: FEAT: PowerPoint Import - teachers can upload .pptx/.ppt files and have each slide
 *         automatically converted to an image. Also supports drag-and-drop of image files.
 *         Conversion uses LibreOffice headless + Ghostscript on the AI Grader server.
 *         New endpoint: /api/moodle/slideshow/convert-pptx with siteId/apiKey auth.
 * v1.5.1: AUDIT: Production-readiness audit - fixed backup losing custom voiceover text (missing
 *         customtext field in backup structure), added missing validate_context in reorder AJAX,
 *         added is_enabled() guard to single-slide completion, removed dead code, cleaned debug comments.
 * v1.5.0: STYLE: Modern player UI redesign - off-white (#f5f6f8) player boundary with rounded corners,
 *         navigation buttons moved to player gutters outside slide image, white slide background with
 *         subtle shadow, cleaner pagination counter, responsive gutter sizing at all breakpoints.
 * v1.4.9: FIX: Removed unsupported pitch parameter from Chirp 3 HD TTS config
 *         causing "13 INTERNAL: Failed to create audio encoder" on some slides.
 *         Fixed voiceover panel text from "1 credit" to "5 credits" per slide.
 * v1.4.8: FIX: Voiceover pricing updated from 1 to 5 credits per slide across all files
 * v1.4.7: FIX: Accurate OGG_OPUS audio duration parsing, generate_all now uses custom text,
 *         audiohash field populated, reload hint after voiceover generation
 * v1.4.6: FEAT: Per-slide voiceover text editor with custom text input, per-slide generate/regenerate buttons,
 *         browser-based sequential generation (fixes timeout), customtext DB field, save_slide_text external function
 * v1.4.5: FIX: Combined OCR+TTS into single API call to prevent 504 gateway timeouts
 * v1.4.4: FIX: OCR now sends base64 image data directly instead of Moodle pluginfile URL
 * which required authentication and couldn't be accessed by the external API server
 *
 * @package     mod_slideshow
 * @copyright   2025 National Corporate Training Pty Ltd (https://nct.net.au/) <believe@nct.net.au>
 * @author      LMSACE Dev Team <lmsace.com>
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_slideshow';
$plugin->version   = 2026072300235;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.6.30'; // FEAT: Video slides (YouTube/URL/upload) + poster image slides with position selection and watch gating.
