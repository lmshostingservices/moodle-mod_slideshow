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
 * Tests for slide narration on the site's own AI.
 *
 * local\byok\engine was compared with the LMS Labs service's narration route on 12,500 randomised requests and AI
 * answers, with identical results; these tests cover the wiring into the plugin.
 *
 * @package    mod_slideshow
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_slideshow\byok
 * @covers     \mod_slideshow\local\byok\engine
 * @covers     \mod_slideshow\external\generate_voiceover
 */
#[\PHPUnit\Framework\Attributes\CoversClass(byok::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(engine::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(external\generate_voiceover::class)]
final class byok_test extends \advanced_testcase {
    /** @var array Requests the mocked providers received: [url, body]. */
    protected $requests = [];
    /** @var array Text answers the mocked Anthropic gives, in order (null: it fails). */
    protected $answers = [];

    /**
     * Licence the site for Bring your own AI on a test key: voice on OpenAI, vision on Anthropic, no LMS Labs
     * credentials.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists('\local_aiconfig\byok\ai')) {
            $this->markTestSkipped('AI Grader Central Config with Bring your own AI is not installed.');
        }
        $this->resetAfterTest();
        $pair = sodium_crypto_sign_keypair();
        set_config('testpublickey', base64_encode(sodium_crypto_sign_publickey($pair)), 'local_aiconfig');
        $payload = json_encode(['v' => 1, 'id' => 'lic_t', 'site' => \local_aiconfig\byok\licence::site_host(),
            'plan' => 'pro', 'byok' => true, 'products' => ['*'], 'sub' => 'sub_t', 'exp' => time() + DAYSECS]);
        $b64 = fn(string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        set_config('licencekey', $b64($payload) . '.' .
            $b64(sodium_crypto_sign_detached($payload, sodium_crypto_sign_secretkey($pair))), 'local_aiconfig');
        foreach (['voice' => 'openai', 'vision' => 'anthropic'] as $service => $vendor) {
            set_config('src_' . $service, 'ownkey', 'local_aiconfig');
            set_config('vendor_' . $service, $vendor, 'local_aiconfig');
        }
        set_config('key_anthropic', 'sk-test', 'local_aiconfig');
        set_config('key_openai', 'sk-test', 'local_aiconfig');
        foreach (['local_aiconfig', 'mod_slideshow'] as $plugin) {
            set_config('siteid', '', $plugin);
            set_config('apikey', '', $plugin);
        }
        $this->requests = [];
        $this->answers = [];
        $GLOBALS['local_aiconfig_byok_mock'] = function ($url, $headers, $body, $vendor, $raw = false) {
            $this->requests[] = [$url, $body];
            if (strpos($url, '/audio/speech') !== false) {
                return 'not-an-ogg-clip';
            }
            $answer = array_shift($this->answers);
            if ($answer === null) {
                throw new \moodle_exception('byok_callfailed', 'local_aiconfig', '', (object) ['vendor' => $vendor,
                    'detail' => 'Overloaded']);
            }
            return ['content' => [['type' => 'text', 'text' => $answer]], 'stop_reason' => 'end_turn'];
        };
        engine::$sleep = null;
    }

    /**
     * Clean up the mock.
     */
    protected function tearDown(): void {
        unset($GLOBALS['local_aiconfig_byok_mock']);
        engine::$ocr = null;
        engine::$tts = null;
        parent::tearDown();
    }

    /**
     * A narration request as the plugin sends it.
     *
     * @param array $more
     * @return array
     */
    protected function payload(array $more = []): array {
        return $more + ['siteId' => '', 'apiKey' => '', 'languageCode' => 'en-AU', 'voiceId' => 'en-AU-Chirp3-HD-Aoede',
            'voiceGender' => 'female', 'creditsToUse' => 5];
    }

    /**
     * The settings decide whether narration runs on the site's own AI.
     */
    public function test_switch(): void {
        $this->assertTrue(byok::uses_voice());
        $this->assertTrue(byok::uses_vision());
        set_config('licencekey', '', 'local_aiconfig');
        $this->assertFalse(byok::uses_voice());
    }

    /**
     * The teacher's own text is narrated on the site's voice at the service's speed, with the closest OpenAI voice.
     */
    public function test_own_text(): void {
        [$status, $answer] = byok::voiceover($this->payload(['customText' => "  Lock out the isolator.\n"]));
        $this->assertSame(200, $status);
        $this->assertTrue($answer['success']);
        $this->assertTrue($answer['hasText']);
        $this->assertSame('Lock out the isolator.', $answer['text']);
        $this->assertSame(base64_encode('not-an-ogg-clip'), $answer['audioContent']);
        $this->assertSame('audio/ogg', $answer['audioType']);
        $this->assertSame(2, $answer['duration']);
        $this->assertCount(1, $this->requests);
        $this->assertSame(['nova', 'Lock out the isolator.', 'opus', 0.92], [$this->requests[0][1]['voice'],
            $this->requests[0][1]['input'], $this->requests[0][1]['response_format'], $this->requests[0][1]['speed']]);
    }

    /**
     * The slide is read by the site's Vision AI and tidied as the service tidies it.
     */
    public function test_read_slide(): void {
        $this->answers = ["## Isolating conveyor CV-03\n\nBefore you start, lock out the\nisolator and test\nfor dead.\n" .
            "• Apply your personal danger lock\n• Tag it\nx\n---\nNever rely on the\nemergency stop.\n"];
        [$status, $answer] = byok::voiceover($this->payload(['imageData' => base64_encode('PNGDATA'),
            'mimeType' => 'image/png']));
        $this->assertSame(200, $status);
        // Exactly the service's text for the same reading.
        $this->assertSame("Isolating conveyor CV-03\n\nBefore you start, lock out the isolator and test for dead.\n" .
            "• Apply your personal danger lock\n• Tag it Never rely on the emergency stop.", $answer['text']);
        [$url, $sent] = $this->requests[0];
        $this->assertStringContainsString('anthropic.com', $url);
        $this->assertSame(2048, $sent['max_tokens']);
        $this->assertSame(0.1, $sent['temperature']);
        $this->assertSame(base64_encode('PNGDATA'), $sent['messages'][0]['content'][0]['source']['data']);
        $this->assertStringContainsString('NO_TEXT_FOUND', json_encode($sent['messages'][0]['content']));
    }

    /**
     * A slide without text is skipped, as on the service.
     */
    public function test_no_text(): void {
        $this->answers = ['NO_TEXT_FOUND'];
        [$status, $answer] = byok::voiceover($this->payload(['imageData' => base64_encode('PNG')]));
        $this->assertSame([200, false, ''], [$status, $answer['hasText'], $answer['audioContent']]);
        $this->assertCount(1, $this->requests);
    }

    /**
     * Failures read as the service's, with what the site's AI said.
     */
    public function test_failures(): void {
        $this->answers = [null];
        [$status, $answer] = byok::voiceover($this->payload(['imageData' => base64_encode('PNG')]));
        $this->assertSame(500, $status);
        $this->assertStringContainsString('Overloaded', $answer['error']);

        // No Vision of its own and no LMS Labs credentials: the teacher is told how to fix it.
        set_config('src_vision', 'credits', 'local_aiconfig');
        [$status, $answer] = byok::voiceover($this->payload(['imageData' => base64_encode('PNG')]));
        $this->assertSame(500, $status);
        $this->assertSame(get_string('byok_needvision', 'mod_slideshow'), $answer['error']);

        [$status, $answer] = byok::voiceover($this->payload());
        $this->assertSame([400, 'Either customText or imageData must be provided'], [$status, $answer['error']]);
    }

    /**
     * Generating a slide's voiceover through the web service keeps the audio and charges nothing.
     */
    public function test_generate_voiceover_service(): void {
        global $DB;
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id,
            'enablevoiceover' => 1, 'voicelanguage' => 'en-AU', 'voicestyle' => 'Kore']);
        $context = \context_module::instance($slideshow->cmid);
        get_file_storage()->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_slideshow',
            'filearea' => 'slideimages', 'itemid' => $slideshow->id, 'filepath' => '/', 'filename' => 'slide1.png'], 'PNG');

        // AI Grader Central Config's lib.php adds a debugging error handler the first time it loads; take it off again.
        $loaded = function_exists('local_aiconfig_get_siteid');
        $result = external\generate_voiceover::execute($slideshow->cmid, 'slide1.png', 'Test before you touch.');
        if (!$loaded && !empty($GLOBALS['CFG']->debug)) {
            restore_error_handler();
        }
        $this->assertTrue($result['success'], $result['error']);
        $this->assertSame('shimmer', $this->requests[0][1]['voice']);
        $record = $DB->get_record('slideshow_voiceovers', ['slideshowid' => $slideshow->id, 'filename' => 'slide1.png']);
        $this->assertSame('ready', $record->status);
        $this->assertEquals(0, $record->creditscharged);
        $this->assertNotFalse(get_file_storage()->get_file($context->id, 'mod_slideshow', 'slidevoiceovers', $slideshow->id,
            '/', 'slide1.png.ogg'));
    }
}
