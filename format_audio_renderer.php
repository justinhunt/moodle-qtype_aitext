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
 * Audio recorder response format renderer.
 *
 * @package    qtype_aitext
 * @copyright  2024 Justin Hunt (poodll.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The student records a spoken response. It is transcribed in the browser and the
 * transcript is submitted, and graded, as a plain text answer.
 *
 * @copyright  2024 Justin Hunt (poodll.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_aitext_format_audio_renderer extends qtype_aitext_format_renderer_base {
    /** @var string Speech recognition service used when the browser cannot transcribe by itself. */
    const ASR_URL = 'https://useast.ls.poodll.com/transcribe';

    /** @var string Region of the speech recognition service. */
    const ASR_REGION = 'useast1';

    /** @var string[] Response languages that do not separate words with spaces, so words cannot be counted. */
    const NO_WORDCOUNT_LANGUAGES = ['ja-jp', 'zh-cn', 'zh-tw', 'ko-kr'];

    /**
     * Specific class name to add to the input element.
     *
     * @return string
     */
    protected function class_name() {
        return 'qtype_aitext_audio';
    }

    /**
     * The transcript of the recording, read-only.
     *
     * @param question_attempt $qa the question attempt being displayed.
     * @param question_attempt_step $step the current step.
     * @param int $lines not used by this format.
     * @param object $context the context the attempt belongs to.
     * @return string HTML fragment.
     */
    public function response_area_read_only($qa, $step, $lines, $context) {
        $id = $qa->get_qt_field_name('answer') . '_id';

        $responselabel = $this->displayoptions->add_question_identifier_to_label(get_string('answeraudio', 'qtype_aitext'));
        $output = html_writer::tag('label', $responselabel, ['class' => 'visually-hidden', 'for' => $id]);
        $output .= html_writer::div(
            format_text((string) $step->get_qt_var('answer'), FORMAT_PLAIN, ['para' => false]),
            $this->class_name() . ' qtype_aitext_response readonly',
            ['id' => $id]
        );
        return $output;
    }

    /**
     * The audio recorder, with a hidden input that receives the transcript.
     *
     * @param question_attempt $qa the question attempt being displayed.
     * @param question_attempt_step $step the current step.
     * @param int $lines not used by this format.
     * @param object $context the context the attempt belongs to.
     * @return string HTML fragment.
     */
    public function response_area_input($qa, $step, $lines, $context) {
        $question = $qa->get_question();
        $inputname = $qa->get_qt_field_name('answer');
        $id = $inputname . '_id';
        $response = (string) $step->get_qt_var('answer');
        if ($response === (string) $question->responsetemplate) {
            // The renderer pre-fills unanswered questions with the response template, which means nothing to a recorder.
            $response = '';
        }
        $language = $question->responselanguage ?: \qtype_aitext\constants::DEFAULT_LANGUAGE;

        $responselabel = $this->displayoptions->add_question_identifier_to_label(get_string('answeraudio', 'qtype_aitext'));
        $output = html_writer::tag('label', $responselabel, ['class' => 'visually-hidden', 'for' => $id]);
        $output .= html_writer::start_tag('div', ['class' => $this->class_name() . ' qtype_aitext_response']);
        $output .= $this->render_from_template('qtype_aitext/audiorecorder', [
            'id' => $id,
            'inputname' => $inputname,
            'safeid' => preg_replace('/[^a-zA-Z0-9_-]/', '_', $id),
            'haveresponse' => $response !== '',
            'response' => $response,
            'formatplain' => FORMAT_PLAIN,
            'waveheight' => 75,
            'asrurl' => self::ASR_URL,
            'region' => self::ASR_REGION,
            'language' => $language,
            'maxtime' => (int) $question->maxtime,
            'wordcount' => $response === '' ? 0 : count_words($response),
            'cancountwords' => !in_array($language, self::NO_WORDCOUNT_LANGUAGES),
            'contextid' => $context->id,
            'punctuate' => \qtype_aitext\local\punctuator::is_enabled(),
        ]);
        $output .= html_writer::end_tag('div');

        return $output;
    }
}
