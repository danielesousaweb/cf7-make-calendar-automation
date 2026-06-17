<?php
/**
 * CF7 -> Make.com webhook dispatcher
 *
 * Listens for the wpcf7_mail_sent hook (fired after Contact Form 7
 * successfully processes a submission) and forwards the submitted
 * fields as a JSON payload to a Make.com scenario webhook.
 *
 * Each form ID maps to its own webhook URL, so a single snippet can
 * serve an unlimited number of webinars/events without code changes -
 * onboarding a new event is just adding one line to the $webhooks map.
 *
 * Deployment target: WP "Code Snippets" plugin, scope "Run everywhere".
 *
 * @sanitized true - all real form IDs and webhook URLs have been
 *   replaced with placeholders. See README "Configuration" section.
 */

add_action( 'wpcf7_mail_sent', 'cas_dispatch_webinar_webhook', 10, 1 );

function cas_dispatch_webinar_webhook( $contact_form ) {

    $submission = WPCF7_Submission::get_instance();
    if ( ! $submission ) {
        return;
    }

    $form_id = $contact_form->id();
    $data    = $submission->get_posted_data();

    // Map: CF7 form ID => Make.com scenario webhook URL.
    // One entry per event/webinar. Add new rows here to onboard a new event.
    $webhooks = array(
        'FORM_ID_PLACEHOLDER_1' => 'https://hook.example-region.make.com/WEBHOOK_TOKEN_1',
        'FORM_ID_PLACEHOLDER_2' => 'https://hook.example-region.make.com/WEBHOOK_TOKEN_2',
        // Add new webinars/events here.
    );

    if ( ! isset( $webhooks[ $form_id ] ) ) {
        return;
    }

    wp_remote_post( $webhooks[ $form_id ], array(
        'method'   => 'POST',
        'headers'  => array( 'Content-Type' => 'application/json' ),
        'body'     => json_encode( $data ),
        'blocking' => false,  // fire-and-forget: don't delay the user-facing response
        'timeout'  => 15,
    ) );
}
