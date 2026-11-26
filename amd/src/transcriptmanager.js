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

      // While the transcript is being punctuated, a promise that settles when that is done.
      pending: null,

      // Longest time (ms) a form submission waits for punctuation before going ahead without it.
      MAX_PUNCTUATION_WAIT: 15000,

      // Punctuation marks that show a transcript is already punctuated (Edge punctuates, Chrome does not).
      // Apostrophes and hyphens are left out because they occur inside words.
      // Keep in step with punctuator::PUNCTUATION in classes/local/punctuator.php.
      PUNCTUATION: /[.,!?;:()[\]{}"«»„“”‹›¡¿،؛؟。、「」『』【】《》]/u,

      // For making multiple instances .. for making multiple instances .. for making multiple instances .. multiple..
      clone: function() {
          return $.extend(true, {}, this);
     },

    init: function(opts) {
      this.register_events(opts);
      this.init_components(opts);
    },

    register_events: function(opts) {
      var app = this;
      $('.retry_' + opts.uniqueid).on('click', function() {
        $('.qtype_aitext_audiorecorder_' + opts.uniqueid).removeClass('hidden');
        $('.qtype_aitext_audiosummary_' + opts.uniqueid).addClass('hidden');
      });

      // Punctuation takes a few seconds. If the student submits before it is done, hold the
      // submission until it is, so the punctuated transcript is what gets submitted and graded.
      var form = $('.' + opts.uniqueid).closest('form').get(0);
      if (form) {
        form.addEventListener('submit', function(event) {
          if (app.pending) {
            event.preventDefault();
            app.submit_when_punctuated(form, event.submitter);
          }
        });
      }
    }, // End of register events

    /**
     * Submit the form once punctuation has finished, or has taken too long.
     *
     * @param {HTMLFormElement} form the form being submitted
     * @param {HTMLElement|null} submitter the button that submitted it, so the same action happens
     */
    submit_when_punctuated: function(form, submitter) {
      var app = this;
      if (app.waitingtosubmit) {
        return;
      }
      app.waitingtosubmit = true;
      var timeout = new Promise(function(resolve) {
        setTimeout(resolve, app.MAX_PUNCTUATION_WAIT);
      });
      Promise.race([app.pending, timeout]).then(function() {
        app.pending = null;
        app.waitingtosubmit = false;
        if (form.requestSubmit) {
          form.requestSubmit(submitter || undefined);
        } else if (submitter) {
          submitter.click();
        } else {
          form.submit();
        }
        return null;
      }).catch(function() {
        app.waitingtosubmit = false;
      });
    },

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

                    // Some browsers' speech recognition (e.g. Chrome) has no punctuation, so ask the AI to add it.
                    if (opts.punctuate && app.audiorec.usebrowserrec && !app.PUNCTUATION.test(speechtext)) {
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
        var app = this;
        var input = $('.' + opts.uniqueid);
        var status = $('.qtype_aitext_punctuating_' + opts.uniqueid);
        status.removeClass('hidden');
        var request = Ajax.call([{
            methodname: 'qtype_aitext_punctuate_transcript',
            args: {transcript: transcript, language: opts.lang, contextid: opts.contextid}
        }])[0];
        var pending = new Promise(function(resolve) {
            request.then(function(result) {
                // Ignore the result if the student has recorded again in the meantime.
                if (input.val() === transcript && result.transcript) {
                    input.val(result.transcript);
                }
                return result;
            }).catch(function(error) {
                log.debug('qtype_aitext: could not punctuate transcript');
                log.debug(error);
            }).always(function() {
                if (app.pending === pending) {
                    app.pending = null;
                    status.addClass('hidden');
                }
                resolve();
            });
        });
        app.pending = pending;
    },

    count_words: function(transcript) {
        return transcript.trim().split(/\s+/).filter(function(word) {
            return word.length > 0;
        }).length;
    }
  };
});