/**
 * AI Site Chat — Admin Knowledge Base JS
 * Loaded only on the Knowledge Base and Settings admin pages.
 * All AJAX calls use nonces passed via aichatAdmin (wp_localize_script).
 */
(function ($) {
  'use strict';

  if (typeof aichatAdmin === 'undefined') return;

  $(document).ready(function () {
    initColorPicker();
    initTrainButton();
  });

  /* ── Colour picker (Settings page) ──────────────────────────────────────── */

  function initColorPicker() {
    if ($.fn.wpColorPicker) {
      $('.aichat-color-picker').wpColorPicker();
    }
  }

  /* ── Train Now button ────────────────────────────────────────────────────── */

  function initTrainButton() {
    $(document).on('click', '#aichat-train-btn', function () {
      var $btn    = $(this);
      var $icon   = $btn.find('.aichat-spin-target');
      var $status = $('#aichat-train-status');

      $btn.prop('disabled', true);
      $icon.addClass('aichat-spinning');
      $status.removeClass('is-success is-error').text('Training… this may take a moment.');

      $.ajax({
        url:  aichatAdmin.ajaxUrl,
        type: 'POST',
        data: { action: 'aichat_train_now', nonce: aichatAdmin.trainNonce },
        success: function (r) {
          if (r.success) {
            $status.addClass('is-success').text(r.data.message);
            $('#aichat-trained-text').text(r.data.post_count + ' items indexed. Last: ' + r.data.last_trained);
            $('#aichat-trained-info').show();
          } else {
            $status.addClass('is-error').text(r.data && r.data.message ? r.data.message : 'Training failed.');
          }
        },
        error: function () {
          $status.addClass('is-error').text('An error occurred. Please try again.');
        },
        complete: function () {
          $btn.prop('disabled', false);
          $icon.removeClass('aichat-spinning');
        }
      });
    });
  }

}(jQuery));
