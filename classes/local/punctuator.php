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

namespace qtype_aitext\local;

/**
 * Adds punctuation to speech recognition transcripts with the configured AI backend.
 *
 * Browser speech recognition returns text without punctuation. The transcript is the
 * student's answer and is graded, so the AI may only add punctuation and change letter
 * case. Its reply is used only if it has exactly the same letters and digits, in the same
 * order, as the transcript; otherwise the transcript is kept as it was.
 *
 * @package    qtype_aitext
 * @copyright  2024 Justin Hunt (poodll.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class punctuator {
    /** @var int Transcripts longer than this (in characters) are not sent to the AI. */
    const MAX_LENGTH = 50000;

    /**
     * Is punctuation of audio transcripts enabled on this site?
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool) get_config('qtype_aitext', 'punctuatetranscripts');
    }

    /**
     * Return the transcript with punctuation added, or unchanged if that could not be done safely.
     *
     * @param string $transcript the speech recognition transcript
     * @param string $language the response language code, e.g. en-us
     * @param int $contextid the context to make the AI request in
     * @return string
     */
    public static function punctuate(string $transcript, string $language, int $contextid): string {
        $transcript = trim($transcript);
        if ($transcript === '' || \core_text::strlen($transcript) > self::MAX_LENGTH) {
            return $transcript;
        }

        \question_bank::load_question_definition_classes('aitext');
        $question = new \qtype_aitext_question();
        $question->contextid = $contextid;
        try {
            $reply = $question->perform_request(self::build_prompt($transcript, $language), 'feedback');
        } catch (\Throwable $e) {
            debugging('qtype_aitext: could not punctuate transcript: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return $transcript;
        }

        $punctuated = self::clean_reply($reply);
        return self::same_words($transcript, $punctuated) ? $punctuated : $transcript;
    }

    /**
     * The prompt asking the AI to punctuate a transcript.
     *
     * @param string $transcript
     * @param string $language the response language code, e.g. en-us
     * @return string
     */
    public static function build_prompt(string $transcript, string $language): string {
        return get_string('punctuationprompt', 'qtype_aitext', $language) . "\n\n" . $transcript;
    }

    /**
     * Strip the wrapping an AI sometimes puts around its answer (code fences, quotes).
     *
     * @param string $reply
     * @return string
     */
    public static function clean_reply(string $reply): string {
        $reply = trim($reply);
        $reply = preg_replace('/^\x60{3}[a-z]*\s*|\s*\x60{3}$/i', '', $reply);
        if (preg_match('/^(["\x{201C}])(.*)(["\x{201D}])$/us', $reply, $matches)) {
            $reply = $matches[2];
        }
        return trim($reply);
    }

    /**
     * Do two texts have the same letters and digits in the same order, ignoring case,
     * punctuation and spacing?
     *
     * @param string $original
     * @param string $punctuated
     * @return bool
     */
    public static function same_words(string $original, string $punctuated): bool {
        $normalise = function (string $text): string {
            return \core_text::strtolower(preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '', $text));
        };
        return $punctuated !== '' && $normalise($original) === $normalise($punctuated);
    }
}
