# Make.com scenario setup

Each webinar runs its own Make scenario, built from exactly two modules. This document describes how that scenario is configured — useful both as documentation of the live system and as a step-by-step for replicating the pattern for a new event.

## Scenario structure

| Module | Type | Role |
|---|---|---|
| 1 — Trigger | Webhooks → Custom Webhook | Receives the JSON payload posted by the WordPress PHP snippet |
| 2 — Action | Google Calendar → Update an Event | Adds the submitted email as an attendee on the target event |

## Module 1 — Custom Webhook

| Setting | Value |
|---|---|
| Type | Custom Webhook |
| HTTP method | `POST` |
| Content-Type | `application/json` |
| Authentication | None — the webhook URL itself is the implicit shared secret |
| Trigger mode | Immediately as data arrives |

Generate the webhook URL from this module and copy it into the `$webhooks` map in [`01-webhook-dispatcher.php`](../src/wordpress-snippets/01-webhook-dispatcher.php) for the corresponding CF7 form ID.

> **Trigger mode matters.** If the scenario is left on a periodic polling schedule instead of "Immediately as data arrives," registrants experience a multi-hour delay before receiving their calendar invite — see [troubleshooting.md](troubleshooting.md).

## Module 2 — Google Calendar: Update an Event

| Field | Value / mapping |
|---|---|
| Connection | The Google account that owns the event (must be the organizer — see permissions below) |
| Calendar ID | The calendar's email address |
| Event ID | The target event's ID (see "Finding the Event ID" below) |
| Attendees → Email | `{{1.email}}` — mapped from the webhook module's `email` field |
| Append the Attendees | `Yes` — adds the new attendee without overwriting the existing list |
| Send Updates | `All` — this is what makes Calendar fire the invite email automatically |

### Finding the Event ID

1. Open the event in Google Calendar.
2. Click the three-dot menu → **Publish event**.
3. The Event ID appears at the end of the generated URL, after `eid=`.

Alternatively, use Make's **Search Events** module to look the event up by title and feed its ID into this module dynamically, instead of hardcoding it.

## Required Google Calendar permissions

- The account connected in Make must be the **organizer** of the event, not just a guest. If the event was created by someone else, they need to transfer ownership or add the Make account as a co-editor before the integration can add attendees.
- The event's guest list should be set to hidden, so registrants can't see each other — this is a Calendar event setting, not something Make controls.

## Verifying a scenario after setup

After wiring up both modules, the expected result for a single test submission is:

- Make → **History**: one execution, status `Success`, `2` operations consumed (one per module).
- Google Calendar: the test email appears in the event's attendee list with status `Pending` — Google Calendar does not allow forcing an "accepted" status via the API for external attendees, by design.
- The test inbox receives the calendar invite email, including the Google Meet link (check spam on first test).

## Onboarding checklist for a new webinar

- [ ] Create the event in Google Calendar with the Google Meet link attached; note the Event ID.
- [ ] Duplicate an existing CF7 form for the new event; note its form ID (visible in the form editor's URL).
- [ ] Create a new Make scenario (`+ Create a new scenario`).
- [ ] Add the Custom Webhook trigger module; copy its generated URL.
- [ ] Add the Google Calendar "Update an Event" module, configured as above.
- [ ] Enable "Immediately as data arrives" and save the scenario.
- [ ] Add the new form ID → webhook URL pair to the PHP snippet's `$webhooks` array.
- [ ] Add the new form ID → thank-you page path pair to the redirect snippet's map.
- [ ] Test with an email address that is not already on the event's guest list (directly or via a distribution list), and walk through the [verification steps](#verifying-a-scenario-after-setup) above.
