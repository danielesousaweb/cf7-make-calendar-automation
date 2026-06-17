# Troubleshooting log

Real issues encountered while running this integration in production, kept here both as a reference and as a record of how each was diagnosed. Several of these directly shaped decisions documented in [architecture.md](architecture.md).

---

### Webhook never receives data after a form submission

**Symptom:** Make's History shows no execution at all for a submission that the user confirms went through.

**Diagnosis path:** Confirmed the snippet was active in Code Snippets, then checked whether the form ID in the `$webhooks` map actually matched the real CF7 form ID (visible in the form editor's URL) — a mismatch here fails silently, since the dispatcher function returns early with no error when the form ID isn't found in the map.

**Fix:** Correct the form ID in the PHP map. Added a comment directly above the map (see [`01-webhook-dispatcher.php`](../src/wordpress-snippets/01-webhook-dispatcher.php)) flagging this as the first thing to check.

---

### Make scenario succeeds, but the attendee never appears on the event

**Symptom:** History shows `Success`, but the Google Calendar event's guest list is unchanged.

**Diagnosis path:** The Google account connected to the Make module was a guest on the event, not the organizer.

**Fix:** Either transfer event ownership to the connected account, or reconnect the Google Calendar module using the organizer's account. Documented as a hard requirement in [make-scenario-setup.md](make-scenario-setup.md#required-google-calendar-permissions).

---

### Attendee is added, but never receives the invite email

**Possible causes, in order of likelihood:**

1. The module's **Send Updates** field isn't set to `All`. This is the field that actually tells the Calendar API to fire the notification — without it, the attendee is added silently.
2. The email address was already covered by an existing Distribution List attached to the event, so Calendar treats it as already notified. Re-test with an address that isn't part of any DL on the event.

---

### `Missing value of required parameter email`

**Symptom:** Make execution fails on the Google Calendar module with this exact error.

**Diagnosis path:** The Attendees → Email field's **Map** toggle wasn't enabled, or the binding referenced the wrong module number (e.g. `{{2.email}}` when the webhook module is actually module `1`).

**Fix:** Re-enable Map on that field and verify the binding points at the webhook trigger module specifically.

---

### Scenario gets auto-deactivated by Make

**Symptom:** New submissions stop triggering executions entirely; the scenario shows as turned off.

**Diagnosis path:** Make automatically deactivates a scenario after a module hits a hard error repeatedly. Checking **History → Details** on the last few executions before deactivation pointed to the specific failing module and parameter — most often an incorrect Event ID or an OAuth connection that had lost the necessary Calendar permission.

**Fix:** Resolve the underlying module error (see the entries above), then manually re-activate the scenario.

---

### Hours-long delay before the webhook fires

**Symptom:** The form submits fine and the user is redirected, but the calendar invite doesn't arrive for hours.

**Diagnosis path:** The scenario's trigger was set to periodic polling rather than **Immediately as data arrives**.

**Fix:** Re-enable the "Immediately as data arrives" toggle. This is now called out explicitly in [make-scenario-setup.md](make-scenario-setup.md) as a required setting, not a default to assume.

---

### One-click confirmation button doesn't submit the form

**Symptom:** Clicking "Yes, confirm" on the email-link confirmation prompt does nothing, or the form silently fails to submit.

**Diagnosis path:** Browser console showed a required CF7 field was still empty at submit time — usually because the `dispatchEvent` calls that simulate user input hadn't fired before the programmatic `.click()` on the submit button.

**Fix:** Increased the `setTimeout` delay before the programmatic click, and double-checked that every required field has a corresponding fallback value in `valueOrFallback()` (see [`03-get-param-confirmation.php`](../src/wordpress-snippets/03-get-param-confirmation.php)) so it's never left genuinely empty.

---

### Confirmation block doesn't render at all, even with valid URL parameters

**Symptom:** Visiting the tracked email link does nothing — the manual form just shows as normal.

**Diagnosis path:** A `SyntaxError` in the browser console (F12) pointed to encoding corruption of special characters (accented vowels, cedillas) inside the inline PHP-echoed `<script>` block — some hosting/CDN layers mangle non-ASCII characters embedded directly in PHP-output JavaScript.

**Fix:** Replaced every accented character in the script with its Unicode escape sequence (`\u00e3`, `\u00e7`, etc.) instead of the literal character. Also avoided ternary operators inside the PHP-wrapped script after they triggered a separate, similar parsing issue on one hosting environment — replaced with explicit `if/else`.

---

### Submission goes through with literal `--` or `nao informado` in required fields

**Symptom:** A registration arrives in Calendar/Make with garbage placeholder text instead of real data in optional-looking fields.

**Diagnosis path:** Traced back to the CRM/ESP export itself using literal placeholder strings for contacts with missing fields, which then flowed straight through the URL parameters into the form.

**Fix:** This is exactly what `valueOrFallback()` exists to catch (see [`03-get-param-confirmation.php`](../src/wordpress-snippets/03-get-param-confirmation.php)). When a new placeholder pattern shows up in the wild, it gets added to the `invalidValues` array rather than handled as a one-off.
