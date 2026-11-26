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

namespace mod_slideshow\local\byok;

/**
 * Slide narration, ported from the LMS Labs service for Bring your own AI.
 *
 * A port of the service's POST /api/moodle/slideshow/generate-voiceover route: read the slide's text (or take the
 * teacher's own), tidy it for narration and speak it. Reading the slide goes to {@see self::$ocr}, speaking to
 * {@see self::$tts}. The route was compared with the service's code on generated requests and AI replies.
 *
 * @package    mod_slideshow
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engine {
    /**
     * @var callable|null Reads a slide: fn(string $prompt, string $mimetype, string $base64): array [text, bool already
     *     tidied]. The text is '' when nothing came back.
     */
    public static $ocr = null;

    /** @var callable|null fn(string $text, string $voiceid, string $language, string $format): string, audio bytes. */
    public static $tts = null;

    /** @var callable|null fn(int $ms): void, the wait between voice retries (tests make it instant). */
    public static $sleep = null;

    /** @var int The service's TTS_MAX_BYTES: Google's 5,000-byte limit less a margin. */
    const TTS_MAX_BYTES = 4800;

    /** @var string JavaScript's whitespace (\s and String.prototype.trim()). */
    const WS = '\x{9}-\x{d}\x{20}\x{a0}\x{1680}\x{2000}-\x{200a}\x{2028}\x{2029}\x{202f}\x{205f}\x{3000}\x{feff}';

    /** @var string The service's instruction for reading a slide. */
    const OCR_PROMPT = 'Extract ALL text visible in this image for voiceover narration. Join words that are split across ' .
        'lines into complete sentences - do NOT break sentences mid-word or mid-phrase just because the slide has a line ' .
        'break. Remove any stray single characters or fragments that are not part of meaningful text. Return clean, ' .
        'flowing sentences grouped into logical paragraphs separated by blank lines. Headings should be on their own ' .
        'line. If there is no text in the image, respond with exactly: NO_TEXT_FOUND';

    /**
     * POST /api/moodle/slideshow/generate-voiceover.
     *
     * @param \stdClass $body The request.
     * @return array [status, body]
     */
    public static function voiceover(\stdClass $body): array {
        $v = self::fields($body);
        if ($v === null) {
            return [400, ['success' => false, 'error' => 'Invalid request parameters']];
        }
        $custom = $v['customText'];
        $image = $v['imageData'];
        $hascustom = $custom !== null && self::trim($custom) !== '';
        $hasimage = $image !== null && $image !== '';
        if (!$hascustom && !$hasimage) {
            return [400, ['success' => false, 'error' => 'Either customText or imageData must be provided']];
        }
        if ($hasimage) {
            if (strpos($v['mimeType'], 'image/') !== 0) {
                return [400, ['success' => false, 'error' => 'Invalid mime type']];
            }
            if (self::base64_length($image) > 20 * 1024 * 1024) {
                return [400, ['success' => false, 'error' => 'Image too large (max 20MB)']];
            }
        }
        try {
            if ($hascustom) {
                $text = self::trim($custom);
            } else {
                [$read, $tidied] = (self::$ocr)(self::OCR_PROMPT, $v['mimeType'], $image);
                if ($read === '' || self::trim($read) === 'NO_TEXT_FOUND') {
                    return [200, ['success' => true, 'audioContent' => '', 'audioType' => '', 'duration' => 0, 'text' => '',
                        'hasText' => false, 'error' => '']];
                }
                $text = $tidied ? $read : self::tidy($read);
            }
            [$audio, $seconds, $type] = self::speak($text, $v['voiceId'], $v['languageCode']);
            return [200, ['success' => true, 'audioContent' => base64_encode($audio), 'audioType' => $type,
                'duration' => $seconds, 'text' => $text, 'hasText' => true, 'credits' => -1, 'error' => '']];
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            return [500, ['success' => false, 'error' => $message !== '' ? $message : 'Voiceover generation failed']];
        }
    }

    /**
     * The route's request schema (zod): the values it reads, or null when the request is refused.
     *
     * @param \stdClass $body
     * @return array|null
     */
    protected static function fields(\stdClass $body): ?array {
        // Required (no default), optional (null: left undefined when missing), or the default when missing.
        $rules = ['siteId' => false, 'apiKey' => false, 'imageData' => null, 'mimeType' => 'image/png',
            'languageCode' => 'en-AU', 'voiceId' => false, 'voiceGender' => 'female', 'customText' => null];
        $v = [];
        foreach ($rules as $name => $default) {
            if (!property_exists($body, $name)) {
                if ($default === false) {
                    return null;
                }
                $v[$name] = $default;
            } else if (is_string($body->$name)) {
                $v[$name] = $body->$name;
            } else {
                return null;
            }
        }
        $credits = property_exists($body, 'creditsToUse') ? $body->creditsToUse : 5;
        if ((!is_int($credits) && !is_float($credits)) || $credits < 1) {
            return null;
        }
        return $v;
    }

    /**
     * The service's cleanOcrTextForVoiceover(): join lines broken by the slide layout back into sentences.
     *
     * @param string $raw
     * @return string
     */
    public static function tidy(string $raw): string {
        $ws = '[' . self::WS . ']';
        $text = preg_replace(['/```[^`]*```/u', '/\*\*/u', '/#{1,6}' . $ws . '*/u'], '', $raw);
        $merged = [];
        $buffer = '';
        foreach (explode("\n", $text) as $line) {
            $trimmed = self::trim($line);
            if ($trimmed === '') {
                if ($buffer !== '') {
                    $merged[] = $buffer;
                    $buffer = '';
                }
                $merged[] = '';
                continue;
            }
            if (self::len($trimmed) <= 2 && !preg_match('/\d/', $trimmed)) {
                continue;
            }
            if (preg_match('/^[^a-zA-Z0-9]+$/Du', $trimmed)) {
                continue;
            }
            if ($buffer !== '') {
                $punctuated = preg_match('/[.!?:;]$/D', $buffer);
                $upper = preg_match('/^[A-Z]/', $trimmed);
                $bullet = preg_match('/^[\x{2022}\x{2023}\x{25E6}\x{2043}\-\*\d+\.\)]' . $ws . '/u', $trimmed);
                $lower = preg_match('/[a-z,]$/D', $buffer);
                $sentence = $upper && self::len($trimmed) > 20;
                $continuation = $lower && !$sentence;
                if ($punctuated || $bullet || ($upper && !$continuation)) {
                    $merged[] = $buffer;
                    $buffer = $trimmed;
                } else {
                    $buffer .= ' ' . $trimmed;
                }
            } else {
                $buffer = $trimmed;
            }
        }
        if ($buffer !== '') {
            $merged[] = $buffer;
        }
        // As the service filters: a blank line survives unless the first blank line is the last line.
        $first = array_search('', $merged, true);
        $kept = [];
        foreach ($merged as $line) {
            $line = self::trim($line);
            if ($line !== '' || ($first !== false && $first < count($merged) - 1)) {
                $kept[] = $line;
            }
        }
        return self::trim(preg_replace('/\n{3,}/', "\n\n", implode("\n", $kept)));
    }

    /**
     * The service's generateVoiceover(): one Ogg Opus clip, or MP3 pieces joined when the text is long.
     *
     * @param string $text
     * @param string $voiceid
     * @param string $language
     * @return array [audio bytes, seconds, mime type]
     */
    public static function speak(string $text, string $voiceid, string $language): array {
        $chunks = self::chunks($text, self::TTS_MAX_BYTES);
        $words = function () use ($text): int {
            return (int) max(2, ceil((count(preg_split('/[' . self::WS . ']+/u', $text)) / 150) * 60));
        };
        if (count($chunks) === 1) {
            $audio = self::synthesise($chunks[0], $voiceid, $language, 'ogg');
            try {
                $seconds = self::ogg_seconds($audio);
            } catch (\Throwable $e) {
                $seconds = $words();
            }
            return [$audio, $seconds, 'audio/ogg'];
        }
        $audio = '';
        foreach ($chunks as $chunk) {
            $audio .= self::synthesise($chunk, $voiceid, $language, 'mp3');
        }
        return [$audio, $words(), 'audio/mpeg'];
    }

    /**
     * One request to the voice, tried up to four times as the service does.
     *
     * @param string $text
     * @param string $voiceid
     * @param string $language
     * @param string $format ogg or mp3.
     * @return string
     */
    protected static function synthesise(string $text, string $voiceid, string $language, string $format): string {
        $wait = 1000;
        for ($attempt = 1;; $attempt++) {
            try {
                $audio = (self::$tts)($text, $voiceid, $language, $format);
                if ($audio === '') {
                    throw new \RuntimeException('No audio content in TTS response');
                }
                return $audio;
            } catch (\Throwable $e) {
                if ($attempt >= 4) {
                    throw $e;
                }
                $sleep = self::$sleep ?? function (int $ms): void {
                    usleep($ms * 1000);
                };
                $sleep(min(5000, $wait));
                $wait *= 2;
            }
        }
    }

    /**
     * The service's splitTextIntoChunks(): sentences packed into pieces of at most $max UTF-8 bytes.
     *
     * @param string $text
     * @param int $max
     * @return string[]
     */
    public static function chunks(string $text, int $max): array {
        if (strlen($text) <= $max) {
            return [$text];
        }
        $chunks = [];
        $current = '';
        foreach (preg_split('/(?<=[.!?])[' . self::WS . ']+/u', $text) as $sentence) {
            $test = $current . ($current !== '' ? ' ' : '') . $sentence;
            if (strlen($test) <= $max) {
                $current = $test;
                continue;
            }
            if ($current !== '') {
                $chunks[] = $current;
            }
            if (strlen($sentence) <= $max) {
                $current = $sentence;
                continue;
            }
            $remaining = $sentence;
            while (strlen($remaining) > $max) {
                $cut = (int) floor($max / 2);
                $sub = self::slice($remaining, 0, $cut);
                $space = strrpos($sub, ' ');
                if ($space !== false && self::len(substr($sub, 0, $space)) > self::len($sub) * 0.3) {
                    $cut = self::len(substr($sub, 0, $space));
                }
                $chunks[] = self::trim(self::slice($remaining, 0, $cut));
                $remaining = self::trim(self::slice($remaining, $cut));
            }
            $current = $remaining;
        }
        if (self::trim($current) !== '') {
            $chunks[] = self::trim($current);
        }
        return $chunks;
    }

    /**
     * The service's parseOggOpusDuration(): seconds from the last granule position.
     *
     * @param string $buffer Ogg Opus bytes.
     * @return float
     */
    public static function ogg_seconds(string $buffer): float {
        $offset = 0;
        $last = 0.0;
        $preskip = 0;
        $n = strlen($buffer);
        while ($offset + 27 <= $n) {
            if (substr($buffer, $offset, 4) !== 'OggS') {
                break;
            }
            $parts = unpack('Vlo/Vhi', $buffer, $offset + 6);
            $granule = $parts['hi'] * 4294967296.0 + $parts['lo'];
            $segments = ord($buffer[$offset + 26]);
            if ($offset + 27 + $segments > $n) {
                break;
            }
            $length = 0;
            for ($i = 0; $i < $segments; $i++) {
                $length += ord($buffer[$offset + 27 + $i]);
            }
            $start = $offset + 27 + $segments;
            if ($start + 8 <= $n && substr($buffer, $start, 8) === 'OpusHead' && $start + 12 <= $n) {
                $preskip = unpack('v', $buffer, $start + 10)[1];
            }
            if ($granule > 0) {
                $last = $granule;
            }
            $offset = $start + $length;
        }
        if ($last <= 0) {
            throw new \RuntimeException('Could not determine OGG duration');
        }
        return max(0.1, ($last - $preskip) / 48000);
    }

    /**
     * Node's Buffer.byteLength(string, 'base64'): the decoded size from the length alone.
     *
     * @param string $base64
     * @return int
     */
    public static function base64_length(string $base64): int {
        $n = self::len($base64);
        if ($n > 0 && substr($base64, -1) === '=') {
            $n--;
            if ($n > 1 && substr($base64, -2, 1) === '=') {
                $n--;
            }
        }
        return ($n * 3) >> 2;
    }

    /**
     * String.prototype.trim().
     *
     * @param string $s
     * @return string
     */
    public static function trim(string $s): string {
        return preg_replace('/^[' . self::WS . ']+|[' . self::WS . ']+$/Du', '', $s);
    }

    /**
     * A string's length in UTF-16 code units.
     *
     * @param string $s
     * @return int
     */
    public static function len(string $s): int {
        return (int) (strlen(mb_convert_encoding($s, 'UTF-16LE', 'UTF-8')) / 2);
    }

    /**
     * String.prototype.slice() with a non-negative start, in UTF-16 code units.
     *
     * @param string $s
     * @param int $start
     * @param int|null $end
     * @return string
     */
    public static function slice(string $s, int $start, ?int $end = null): string {
        $u = mb_convert_encoding($s, 'UTF-16LE', 'UTF-8');
        $units = (int) (strlen($u) / 2);
        $end = $end === null ? $units : min($end, $units);
        if ($end <= $start) {
            return '';
        }
        $previous = mb_substitute_character();
        mb_substitute_character(0xFFFD);
        $out = mb_convert_encoding(substr($u, $start * 2, ($end - $start) * 2), 'UTF-8', 'UTF-16LE');
        mb_substitute_character($previous);
        return $out;
    }
}
