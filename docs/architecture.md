Architecture
Problem statement
A marketing team runs recurring B2B webinars and needs every registrant to end up as a Google Calendar attendee — so they receive the native calendar invite, with the Google Meet link, without anyone manually copying email addresses into Calendar after each event.
Constraints that shaped the design:
No dedicated backend. The team has a WordPress site and access to Make.com (then called Integromat-successor), but no appetite to stand up, deploy, and pay for a separate service just to move data from a form to an API.
Maintainable by marketing, not just engineering. Onboarding a new webinar needs to be a config change, not a deploy.
Must support two entry points. Registrants either fill out the form directly, or click a tracked link from an email campaign (Brevo or Mailchimp) and should be able to confirm in one click without retyping their information.
Privacy-conscious. The list of attendees on an event needs to stay hidden from other attendees, and no personal data should be stored outside of what Google Calendar and the ESP already hold.
Components and why each one was chosen
Component	Role	Why this, not an alternative
WordPress + Contact Form 7	Collects registrant data	Already the platform in use; CF7 is the de facto standard form plugin and exposes the hooks needed (`wpcf7_mail_sent`) without forking the plugin
Code Snippets (PHP)	Intercepts the submission, dispatches webhook	Avoids editing the theme's `functions.php` directly — snippets are versioned, toggleable, and survive theme updates
Make.com	Talks to the Google Calendar API	Handles OAuth2 token refresh and API versioning so the integration never breaks silently when Google changes an API detail; visual scenario history makes failures debuggable by non-engineers
Google Calendar API	Source of truth for the event and its attendee list	The event already exists in Calendar; the integration's only job is to append an attendee
Brevo / Mailchimp	Originates the one-click confirmation links	Existing ESPs already used for the webinar's email campaigns
Why not a custom backend or a Cloud Function
This was the central design decision, and it was made deliberately rather than by default.
A custom backend (even a small serverless function) would mean: writing and testing OAuth2 token storage and refresh logic, handling Google API error responses and retries, deploying somewhere, monitoring it, and rotating credentials — all to perform one API call. None of that complexity buys anything the team needed; it's pure overhead for a job that fundamentally is "receive a JSON object, call one API."
Make.com absorbs all of that: the OAuth2 connection is configured once in the UI and Make handles refresh tokens transparently; failures are visible in a History view with full request/response detail, which a marketing team member can read without needing to SSH into a server or read application logs; and there's no infrastructure to patch or pay to keep running between webinars.
The trade-off: Make scenarios are not source-controlled, and complex branching logic is harder to express visually than in code. For this integration, the actual logic that needs branching — mapping form IDs to destinations, normalizing messy data — was deliberately kept in version-controlled PHP rather than inside Make, and the Make scenario itself was kept to exactly two modules (trigger + one action) per webinar. Make is used only for the part it's genuinely better at: talking to Google's API.
Why a PHP snippet instead of a CF7-to-webhook plugin
An off-the-shelf "CF7 to webhook" plugin was tried first and dropped after intermittent activation conflicts with other installed plugins (see troubleshooting.md). The PHP snippet approach — hooking `wpcf7_mail_sent` directly — has fewer moving parts, no plugin-to-plugin compatibility surface, and is roughly a dozen lines of auditable code instead of an opaque third-party plugin. For a single, well-understood hook like this, a small owned snippet is more maintainable than a general-purpose plugin solving a broader problem.
Why the email-link flow re-submits the same CF7 form instead of calling the webhook directly
It would be technically simpler for the one-click confirmation script to call the Make webhook directly from the browser, skipping the form entirely. That was deliberately avoided for two reasons:
Single source of truth for "what counts as a registration." If the email-link flow and the manual-form flow both fed the webhook independently, any future change to the payload shape, validation, or webhook-dispatch logic would need to happen in two places and could drift out of sync.
Reuses CF7's own client-side validation and submission lifecycle (spinner states, the `wpcf7mailsent` event used for the redirect) for free, instead of re-implementing them.
The cost is a small amount of extra complexity in the confirmation script — it has to locate and populate the real form fields, then trigger a real submit — which is documented inline in `03-get-param-confirmation.php`.
Data normalization
Real-world CRM/ESP exports are inconsistent: a contact with no first name on file can leak a literal unresolved merge tag (e.g. Mailchimp's `*|FNAME|*`) into the URL; some exports use `--`, `nao informado`, or `/` as a stand-in for an empty field. Rather than let any of that reach the webhook (and therefore Google Calendar) as garbage data, the confirmation script normalizes every field through a single fallback function before submission. This was added after the issue surfaced in production — see the corresponding entry in troubleshooting.md.
What would change at larger scale
This design is intentionally scoped to "one team running recurring webinars from one WordPress site." If the same problem showed up at, say, 50 concurrent events across multiple sites with stricter audit requirements, the parts that would change first:
Move the form-ID-to-webhook mapping out of a PHP array and into a small config table or options page, so non-developers can add events without touching code.
Add signature verification on the webhook (currently relying on the webhook URL itself being unguessable, which is acceptable for the threat model of an internal marketing tool but wouldn't be for anything handling more sensitive data).
Consider moving the Google Calendar call into an owned service if the branching logic needed inside Make ever grew past what's comfortable to express visually.
