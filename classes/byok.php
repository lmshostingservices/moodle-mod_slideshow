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

namespace mod_slideshow;

use mod_slideshow\local\byok\engine;

/**
 * Bring your own AI: slide narration on the site's own AI instead of LMS Labs credits.
 *
 * Narration runs here when the Voice service is set to the site's own key in AI Grader Central Config. The slide's
 * text is read by the site's own Vision AI when that is set up too; otherwise LMS Labs reads it, free of credits, as
 * long as the site has LMS Labs credentials.
 *
 * @package    mod_slideshow
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class byok {
    /** @var string This plugin, as AI Grader Central Config knows it. */
    const COMPONENT = 'mod_slideshow';

    /** @var float The service's narration speed for slides (generateVoiceover()'s default). */
    const SPEAKING_RATE = 0.92;

    /** @var string[] The closest OpenAI voice to each Chirp 3 HD voice. */
    const OPENAI_VOICES = ['Aoede' => 'nova', 'Kore' => 'shimmer', 'Leda' => 'coral', 'Zephyr' => 'sage',
        'Puck' => 'onyx', 'Charon' => 'echo', 'Fenrir' => 'ash', 'Orus' => 'ballad'];

    /** @var string The LMS Labs route that reads a slide's text without charging credits. */
    const OCR_URL = 'https://lms-labs.com/api/moodle/slideshow/ocr';

    /** @var string|null What the site's AI said when it last failed. */
    protected static $lasterror = null;

    /**
     * Whether slide narration runs on the site's own AI now.
     *
     * @return bool
     */
    public static function uses_voice(): bool {
        return class_exists('\local_aiconfig\byok\ai') && \local_aiconfig\byok\ai::uses_own_ai(self::COMPONENT, 'voice');
    }

    /**
     * Whether the slide's text is read by the site's own AI too.
     *
     * @return bool
     */
    public static function uses_vision(): bool {
        return class_exists('\local_aiconfig\byok\ai') && \local_aiconfig\byok\ai::uses_own_ai(self::COMPONENT, 'vision');
    }

    /**
     * Narrate a slide (POST /api/moodle/slideshow/generate-voiceover) on the site's own AI.
     *
     * @param array $payload The request the plugin would send to LMS Labs.
     * @return array [HTTP status, the service's answer]
     */
    public static function voiceover(array $payload): array {
        \core_php_time_limit::raise(300);
        self::$lasterror = null;
        $siteid = (string) ($payload['siteId'] ?? '');
        $apikey = (string) ($payload['apiKey'] ?? '');
        engine::$ocr = function (string $prompt, string $mime, string $base64) use ($siteid, $apikey): array {
            if (self::uses_vision()) {
                $text = self::call(fn() => \local_aiconfig\byok\ai::text(self::COMPONENT, '', $prompt, [
                    'images' => [['mime' => $mime, 'data' => (string) base64_decode($base64)]], 'maxtokens' => 2048,
                    'temperature' => 0.1, 'timeout' => 120,
                ]));
                return [$text, false];
            }
            return [self::service_ocr($siteid, $apikey, $mime, $base64), true];
        };
        engine::$tts = function (string $text, string $voiceid, string $language, string $format): string {
            return self::call(fn() => self::speech($text, $voiceid, $language, $format));
        };
        engine::$sleep = null;
        $body = json_decode(json_encode($payload, JSON_PRESERVE_ZERO_FRACTION));
        foreach (['siteId', 'apiKey'] as $key) {
            if (!is_string($body->$key ?? null) || trim($body->$key) === '') {
                $body->$key = 'byok';
            }
        }
        [$status, $answer] = engine::voiceover($body);
        if (empty($answer['success']) && self::$lasterror !== null && strpos($answer['error'], self::$lasterror) === false) {
            $answer['error'] .= ' ' . get_string('byok_said', 'mod_slideshow', self::$lasterror);
        }
        return [$status, $answer];
    }

    /**
     * Run a call to the site's AI, keeping what it said when it fails.
     *
     * @param callable $call
     * @return string
     */
    protected static function call(callable $call): string {
        try {
            return $call();
        } catch (\moodle_exception $e) {
            self::$lasterror = $e->getMessage();
            throw $e;
        }
    }

    /**
     * Have LMS Labs read the slide's text (its free /ocr route), when the site's own AI has no Vision set up.
     *
     * @param string $siteid
     * @param string $apikey
     * @param string $mime
     * @param string $base64
     * @return string The tidied text, or '' when the slide has none.
     */
    protected static function service_ocr(string $siteid, string $apikey, string $mime, string $base64): string {
        if (trim($siteid) === '' || trim($apikey) === '') {
            throw new \moodle_exception('byok_needvision', 'mod_slideshow');
        }
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/json', 'Accept: application/json']);
        $reply = $curl->post(self::OCR_URL, json_encode(['siteId' => $siteid, 'apiKey' => $apikey,
            'imageData' => $base64, 'mimeType' => $mime]), ['CURLOPT_TIMEOUT' => 120]);
        $data = json_decode((string) $reply, true);
        $code = (int) ($curl->get_info()['http_code'] ?? 0);
        if ($code < 200 || $code >= 300 || !is_array($data) || empty($data['success'])) {
            $error = is_array($data) && is_string($data['error'] ?? null) ? $data['error'] : 'HTTP ' . $code;
            throw new \RuntimeException('Reading the slide failed: ' . $error);
        }
        return !empty($data['hasText']) && is_string($data['text'] ?? null) ? $data['text'] : '';
    }

    /**
     * One narration request.
     *
     * The service narrates slides with Google Chirp 3 HD voices at 0.92 speed. A Google key uses the same voice; an
     * OpenAI key gets the closest OpenAI voice of the same kind; ElevenLabs uses the voice the administrator chose.
     *
     * @param string $text
     * @param string $voiceid A Chirp 3 HD voice, such as en-AU-Chirp3-HD-Aoede.
     * @param string $language
     * @param string $format ogg or mp3.
     * @return string Audio bytes.
     */
    protected static function speech(string $text, string $voiceid, string $language, string $format): string {
        $vendor = \local_aiconfig\byok\router::vendor(\local_aiconfig\byok\router::VOICE);
        if ($vendor === 'googletts') {
            $name = $voiceid;
        } else if ($vendor === 'openai') {
            $parts = explode('-', $voiceid);
            $name = self::OPENAI_VOICES[end($parts)] ?? '';
        } else {
            $name = '';
        }
        return \local_aiconfig\byok\ai::speech(self::COMPONENT, $text,
            ['language' => $language, 'voice' => $name, 'format' => $format, 'rate' => self::SPEAKING_RATE]);
    }
}
