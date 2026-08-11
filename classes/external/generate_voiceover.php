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
 * Slideshow - Generate voiceover for a single slide
 * Uses OCR to extract text from slide image, then routes through
 * EssayGraderAI API for Chirp 3 HD TTS with centralized billing.
 *
 * @package    mod_slideshow
 * @copyright  2025 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_slideshow\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/filelib.php');

if (class_exists('\core_external\external_api')) {
    class_alias('\core_external\external_api', '\mod_slideshow\external\vo_external_api');
    class_alias('\core_external\external_function_parameters', '\mod_slideshow\external\vo_external_function_parameters');
    class_alias('\core_external\external_single_structure', '\mod_slideshow\external\vo_external_single_structure');
    class_alias('\core_external\external_value', '\mod_slideshow\external\vo_external_value');
} else {
    class_alias('\external_api', '\mod_slideshow\external\vo_external_api');
    class_alias('\external_function_parameters', '\mod_slideshow\external\vo_external_function_parameters');
    class_alias('\external_single_structure', '\mod_slideshow\external\vo_external_single_structure');
    class_alias('\external_value', '\mod_slideshow\external\vo_external_value');
}

use context_module;

class generate_voiceover extends vo_external_api {
    const CREDITS_PER_VOICEOVER = 5;

    public static function execute_parameters(): vo_external_function_parameters {
        return new vo_external_function_parameters([
            'cmid' => new vo_external_value(PARAM_INT, 'Course module ID'),
            'filename' => new vo_external_value(PARAM_RAW, 'Slide image filename'),
            'customtext' => new vo_external_value(PARAM_RAW, 'Custom voiceover text (optional, skips OCR if provided)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $cmid, string $filename, string $customtext = ''): array {
        global $CFG, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'filename' => $filename,
            'customtext' => $customtext,
        ]);

        $cm = get_coursemodule_from_id('slideshow', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/slideshow:manage', $context);

        $slideshow = $DB->get_record('slideshow', ['id' => $cm->instance], '*', MUST_EXIST);

        if (empty($slideshow->enablevoiceover)) {
            return ['success' => false, 'audioContent' => '', 'audioType' => '', 'duration' => 0, 'ocrtext' => '', 'error' => 'Voiceover is not enabled'];
        }

        $aiconfiglib = $CFG->dirroot . '/local/aiconfig/lib.php';
        if (file_exists($aiconfiglib)) {
            require_once($aiconfiglib);
        }

        if (function_exists('local_aiconfig_get_siteid')) {
            $siteid = local_aiconfig_get_siteid('mod_slideshow');
        } else {
            $siteid = trim(get_config('mod_slideshow', 'siteid') ?? '');
        }
        if (function_exists('local_aiconfig_get_apikey')) {
            $apikey = local_aiconfig_get_apikey('mod_slideshow');
        } else {
            $apikey = trim(get_config('mod_slideshow', 'apikey') ?? '');
        }

        if (empty($siteid) || empty($apikey)) {
            return ['success' => false, 'audioContent' => '', 'audioType' => '', 'duration' => 0, 'ocrtext' => '',
                'error' => 'API not configured. Please install AI Grader Central Config or configure Site ID and API Key in plugin settings.'];
        }

        $fs = get_file_storage();
        $file = $fs->get_file($context->id, 'mod_slideshow', 'slideimages', $slideshow->id, '/', $params['filename']);
        if (!$file) {
            return ['success' => false, 'audioContent' => '', 'audioType' => '', 'duration' => 0, 'ocrtext' => '', 'error' => 'Slide image not found'];
        }

        $existing = $DB->get_record('slideshow_voiceovers', [
            'slideshowid' => $slideshow->id,
            'filename' => $params['filename'],
        ]);

        $providedtext = trim($params['customtext']);
        $usetext = '';

        if (!empty($providedtext)) {
            $usetext = $providedtext;
        } else if ($existing && !empty($existing->customtext)) {
            $usetext = trim($existing->customtext);
        }

        $voiceid = self::getChirpVoiceId($slideshow->voicelanguage ?: 'en-AU', $slideshow->voicestyle ?: 'Aoede');

        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => 180, 'CURLOPT_RETURNTRANSFER' => true, 'CURLOPT_SSL_VERIFYPEER' => true]);
        $curl->setHeader(['Content-Type: application/json', 'Accept: application/json']);

        $payload = [
            'siteId' => $siteid,
            'apiKey' => $apikey,
            'languageCode' => $slideshow->voicelanguage ?: 'en-AU',
            'voiceId' => $voiceid,
            'voiceGender' => $slideshow->voicegender ?: 'female',
            'creditsToUse' => self::CREDITS_PER_VOICEOVER,
        ];

        if (!empty($usetext)) {
            $payload['customText'] = $usetext;
        } else {
            $imagecontent = $file->get_content();
            $mimetype = $file->get_mimetype() ?: 'image/png';
            $imagebase64 = base64_encode($imagecontent);
            $payload['imageData'] = $imagebase64;
            $payload['mimeType'] = $mimetype;
        }

        $url = 'https://lms-labs.com/api/moodle/slideshow/generate-voiceover';
        $response = $curl->post($url, json_encode($payload));
        $info = $curl->get_info();
        $httpcode = isset($info['http_code']) ? $info['http_code'] : 0;

        if ($httpcode < 200 || $httpcode >= 300) {
            $data = json_decode($response, true);
            $error = $data['error'] ?? "API error: $httpcode";
            self::upsertVoiceoverRecord($DB, $slideshow, $params['filename'], $file->get_contenthash(), '', $usetext, 'error', 0, $error);
            return ['success' => false, 'audioContent' => '', 'audioType' => '', 'duration' => 0, 'ocrtext' => '', 'error' => $error];
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['success']) || !$data['success']) {
            $error = $data['error'] ?? 'Voiceover generation failed';
            self::upsertVoiceoverRecord($DB, $slideshow, $params['filename'], $file->get_contenthash(), '', $usetext, 'error', 0, $error);
            return ['success' => false, 'audioContent' => '', 'audioType' => '', 'duration' => 0, 'ocrtext' => '', 'error' => $error];
        }

        if (empty($data['hasText']) || empty($data['audioContent'])) {
            $ocrtext = $data['text'] ?? '';
            self::upsertVoiceoverRecord($DB, $slideshow, $params['filename'], $file->get_contenthash(), $ocrtext, $usetext, 'skipped', 0);
            return ['success' => true, 'audioContent' => '', 'audioType' => '', 'duration' => 0, 'ocrtext' => $ocrtext, 'error' => 'No text found on slide'];
        }

        $ocrtext = $data['text'] ?? '';
        $audioContent = base64_decode($data['audioContent']);
        $duration = $data['duration'] ?? 0;

        $audiofilename = $params['filename'] . '.ogg';
        $existingaudio = $fs->get_file($context->id, 'mod_slideshow', 'slidevoiceovers', $slideshow->id, '/', $audiofilename);
        if ($existingaudio) {
            $existingaudio->delete();
        }

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_slideshow',
            'filearea' => 'slidevoiceovers',
            'itemid' => $slideshow->id,
            'filepath' => '/',
            'filename' => $audiofilename,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $storedfile = $fs->create_file_from_string($filerecord, $audioContent);
        $audiohash = $storedfile->get_contenthash();

        if (!empty($providedtext)) {
            self::upsertVoiceoverRecord($DB, $slideshow, $params['filename'], $file->get_contenthash(), $ocrtext, $providedtext, 'ready', $duration, '', 1, $audiohash);
        } else {
            self::upsertVoiceoverRecord($DB, $slideshow, $params['filename'], $file->get_contenthash(), $ocrtext, '', 'ready', $duration, '', 1, $audiohash);
        }

        return [
            'success' => true,
            'audioContent' => $data['audioContent'],
            'audioType' => $data['audioType'] ?? 'audio/ogg',
            'duration' => (float)$duration,
            'ocrtext' => $ocrtext,
            'error' => '',
        ];
    }

    private static function upsertVoiceoverRecord($DB, $slideshow, $filename, $contenthash, $ocrtext, $customtext, $status, $duration, $error = '', $credits = 0, $audiohash = '') {
        $existing = $DB->get_record('slideshow_voiceovers', [
            'slideshowid' => $slideshow->id,
            'filename' => $filename,
        ]);

        $record = new \stdClass();
        $record->slideshowid = $slideshow->id;
        $record->filename = $filename;
        $record->contenthash = $contenthash;
        $record->ocrtext = $ocrtext;
        $record->customtext = $customtext;
        $record->voicelanguage = $slideshow->voicelanguage;
        $record->voicegender = $slideshow->voicegender;
        $record->voicestyle = $slideshow->voicestyle;
        $record->duration = $duration;
        $record->status = $status;
        $record->audiohash = $audiohash;
        $record->errortext = $error;
        $record->creditscharged = $credits;
        $record->timemodified = time();

        if ($existing) {
            $record->id = $existing->id;
            $record->creditscharged = $existing->creditscharged + $credits;
            $DB->update_record('slideshow_voiceovers', $record);
        } else {
            $record->timecreated = time();
            $DB->insert_record('slideshow_voiceovers', $record);
        }

        return $record;
    }

    private static function getChirpVoiceId(string $languageCode, string $voiceName): string {
        $languageMappings = [
            'zh-CN' => 'cmn-CN',
            'zh-TW' => 'cmn-TW',
            'zh-HK' => 'yue-HK',
            'nb-NO' => 'no-NO',
            'fil-PH' => 'fil-PH',
        ];

        $mappedCode = $languageMappings[$languageCode] ?? $languageCode;
        return "{$mappedCode}-Chirp3-HD-{$voiceName}";
    }

    public static function execute_returns(): vo_external_single_structure {
        return new vo_external_single_structure([
            'success' => new vo_external_value(PARAM_BOOL, 'Success status'),
            'audioContent' => new vo_external_value(PARAM_RAW, 'Base64 encoded audio'),
            'audioType' => new vo_external_value(PARAM_TEXT, 'Audio MIME type'),
            'duration' => new vo_external_value(PARAM_FLOAT, 'Audio duration in seconds'),
            'ocrtext' => new vo_external_value(PARAM_RAW, 'OCR extracted text from slide', VALUE_DEFAULT, ''),
            'error' => new vo_external_value(PARAM_TEXT, 'Error message if any', VALUE_DEFAULT, ''),
        ]);
    }
}
