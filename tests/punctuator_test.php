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

namespace qtype_aitext;

use qtype_aitext\local\punctuator;

/**
 * Tests for punctuating audio response transcripts.
 *
 * @package    qtype_aitext
 * @copyright  2024 Justin Hunt (poodll.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_aitext\local\punctuator
 */
final class punctuator_test extends \advanced_testcase {
    /**
     * Replies that only add punctuation and change case are accepted, anything else is not.
     *
     * @dataProvider same_words_provider
     * @param string $punctuated the AI reply
     * @param bool $expected
     */
    public function test_same_words(string $punctuated, bool $expected): void {
        $this->assertSame($expected, punctuator::same_words('i think so dont you its a nice day', $punctuated));
    }

    /**
     * Data for test_same_words.
     *
     * @return array
     */
    public static function same_words_provider(): array {
        return [
            'punctuation and case' => ["I think so, don't you? It's a nice day.", true],
            'unchanged' => ['i think so dont you its a nice day', true],
            'word corrected' => ["I think so, don't you? It is a nice day.", false],
            'word added' => ["I really think so, don't you? It's a nice day.", false],
            'word removed' => ["I think so, don't you? It's nice day.", false],
            'words reordered' => ["Don't you think so? It's a nice day.", false],
            'empty' => ['', false],
            'comment added' => ["Here is the text: I think so, don't you? It's a nice day.", false],
        ];
    }

    /**
     * Accented letters and scripts without spaces are compared too.
     */
    public function test_same_words_other_scripts(): void {
        $this->assertTrue(punctuator::same_words('ça va très bien merci', 'Ça va très bien, merci.'));
        $this->assertFalse(punctuator::same_words('ça va très bien merci', 'Ça va tres bien, merci.'));
        $this->assertTrue(punctuator::same_words('今日はいい天気ですね', '今日はいい天気ですね。'));
        $this->assertFalse(punctuator::same_words('今日はいい天気ですね', '今日は良い天気ですね。'));
    }

    /**
     * Code fences and quotes around the reply are removed.
     */
    public function test_clean_reply(): void {
        $this->assertSame('Hello, world.', punctuator::clean_reply("  Hello, world.\n"));
        $this->assertSame('Hello, world.', punctuator::clean_reply("\x60\x60\x60\nHello, world.\n\x60\x60\x60"));
        $this->assertSame('Hello, world.', punctuator::clean_reply("\x60\x60\x60text\nHello, world.\n\x60\x60\x60"));
        $this->assertSame('Hello, world.', punctuator::clean_reply('"Hello, world."'));
        $this->assertSame('Hello, world.', punctuator::clean_reply("\u{201C}Hello, world.\u{201D}"));
        $this->assertSame('He said "hi" to me.', punctuator::clean_reply('He said "hi" to me.'));
    }

    /**
     * The prompt names the language and ends with the transcript.
     */
    public function test_build_prompt(): void {
        $prompt = punctuator::build_prompt('bonjour tout le monde', 'fr-fr');
        $this->assertStringContainsString('fr-fr', $prompt);
        $this->assertStringContainsString('Do not add, remove, replace', $prompt);
        $this->assertStringEndsWith("\n\nbonjour tout le monde", $prompt);
    }

    /**
     * Under PHPUnit the AI replies "AI Feedback", which has different words, so the transcript is kept.
     */
    public function test_punctuate_keeps_transcript_when_words_change(): void {
        $this->resetAfterTest();
        $context = \context_system::instance();
        $this->assertSame('hello world', punctuator::punctuate(' hello world ', 'en-us', $context->id));
        $this->assertSame('', punctuator::punctuate('', 'en-us', $context->id));
    }

    /**
     * The external function returns the transcript, and needs access to the context.
     *
     * The external class loads lib/externallib.php, which needs a separate process.
     *
     * @runInSeparateProcess
     */
    public function test_external_punctuate_transcript(): void {
        global $CFG;
        require_once($CFG->dirroot . '/question/type/aitext/classes/external.php');
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $context = \context_course::instance($course->id);
        $this->setUser($student);

        $this->assertTrue(punctuator::is_enabled());
        $result = \qtype_aitext_external::punctuate_transcript('hello world', 'en-us', $context->id);
        $result = \core_external\external_api::clean_returnvalue(
            \qtype_aitext_external::punctuate_transcript_returns(),
            $result
        );
        $this->assertSame(['transcript' => 'hello world'], $result);

        set_config('punctuatetranscripts', 0, 'qtype_aitext');
        $this->assertFalse(punctuator::is_enabled());
        $result = \qtype_aitext_external::punctuate_transcript('hello world', 'en-us', $context->id);
        $this->assertSame(['transcript' => 'hello world'], $result);

        $othercourse = $this->getDataGenerator()->create_course();
        $this->expectException(\require_login_exception::class);
        \qtype_aitext_external::punctuate_transcript('hello world', 'en-us', \context_course::instance($othercourse->id)->id);
    }
}
