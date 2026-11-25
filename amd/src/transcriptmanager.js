define(['jquery',
    'core/log',
    'core/ajax',
    'qtype_aitext/audiorecorder'
    ], function($, log, Ajax, audiorecorder) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the quiz stage
   */

  log.debug('qtype_aitext transcriptmanager: initialising');

  return {

      // A handle on the audio recorder
      audiorec: null,

      // For making multiple instances .. for making multiple instances .. for making multiple instances .. multiple..
      clone: function() {
          return $.extend(true, {}, this);
     },

    init: function(opts) {
      this.register_events(opts);
      this.init_components(opts);
    },

    register_events: function(opts) {
      $('.retry_' + opts.uniqueid).on('click', function() {
        $('.qtype_aitext_audiorecorder_' + opts.uniqueid).removeClass('hidden');
        $('.qtype_aitext_audiosummary_' + opts.uniqueid).addClass('hidden');
      });
    }, // End of register events

    init_components: function(opts) {
        var app = this;
        var theCallback = function(message) {

            switch (message.type) {
                case 'recording':
                    break;
                case 'interimspeech':
                    var wordcount = app.count_words(message.capturedspeech);
                    $('.' + opts.uniqueid + '_currentwordcount').text(wordcount);
                    break;
                case 'speech':
                    log.debug("speech at multiaudio");
                    var speechtext = message.capturedspeech;
                    log.debug('speechtext:', speechtext);

                    // Set speech text to the hidden input
                    $('.' + opts.uniqueid).val(speechtext);

                    // Update the wordcount
                    var wordcount = app.count_words(message.capturedspeech);
                    $('.' + opts.uniqueid + '_currentwordcount').text(wordcount);

                    // Hide the recorder and show the summary
                    $('.qtype_aitext_audiorecorder_' + opts.uniqueid).addClass('hidden');
                    $('.qtype_aitext_audiosummary_' + opts.uniqueid).removeClass('hidden');

                    // Browser speech recognition has no punctuation, so ask the AI to add it.
                    if (opts.punctuate && app.audiorec.usebrowserrec) {
                        app.punctuate(opts, speechtext);
                    }


            } // End of switch message type
        };

        // Init audio recorder
        opts.callback = theCallback;
        opts.stt_guided = false;
        app.audiorec = audiorecorder.clone();
        app.audiorec.init(opts);
    }, // End of init components

    /**
     * Replace the transcript in the answer with a punctuated version, if the server can make one.
     * The unpunctuated transcript stays in the answer until then, and if anything goes wrong.
     *
     * @param {Object} opts the init options
     * @param {String} transcript the transcript to punctuate
     */
    punctuate: function(opts, transcript) {
        var input = $('.' + opts.uniqueid);
        Ajax.call([{
            methodname: 'qtype_aitext_punctuate_transcript',
            args: {transcript: transcript, language: opts.lang, contextid: opts.contextid}
        }])[0].then(function(result) {
            // Ignore the result if the student has recorded again in the meantime.
            if (input.val() === transcript && result.transcript) {
                input.val(result.transcript);
            }
            return result;
        }).catch(function(error) {
            log.debug('qtype_aitext: could not punctuate transcript');
            log.debug(error);
        });
    },

    count_words: function(transcript) {
        return transcript.trim().split(/\s+/).filter(function(word) {
            return word.length > 0;
        }).length;
    }
  };
});