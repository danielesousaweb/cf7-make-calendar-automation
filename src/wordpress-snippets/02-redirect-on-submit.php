<?php
/**
 * Post-submit redirect to a per-event "thank you" page.
 *
 * CF7 doesn't natively support redirecting to different URLs based on
 * which form was submitted, so this listens for the client-side
 * `wpcf7mailsent` DOM event and redirects based on a form-ID map -
 * mirroring the same pattern used for the webhook dispatcher.
 *
 * @sanitized true - real form IDs and destination paths replaced with
 *   placeholders.
 */

add_action( 'wp_footer', 'cas_cf7_redirect_on_sent' );

function cas_cf7_redirect_on_sent() {
    ?>
    <script>
    document.addEventListener('wpcf7mailsent', function (event) {
        var formId = event.detail.contactFormId;

        // Map: CF7 form ID => thank-you page URL
        var redirectMap = {
            'FORM_ID_PLACEHOLDER_1': '/webinar-event-slug-1/thank-you/',
            'FORM_ID_PLACEHOLDER_2': '/webinar-event-slug-2/thank-you/'
        };

        if (redirectMap[formId]) {
            window.location.href = redirectMap[formId];
        }
    }, false);
    </script>
    <?php
}
