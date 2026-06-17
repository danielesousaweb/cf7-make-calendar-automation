# CF7 + Make.com + Google Calendar — Webinar RSVP Automation

A no-code/low-code automation that turns a WordPress form submission into a calendar invite with a Google Meet link, with zero custom backend and zero Google Cloud functions.

Originally built and run in production for a series of B2B webinars (energy / Smart Grid sector) at a Brazilian technology company. This repository is a **sanitized extract** of that integration, shared to document the architecture and the engineering decisions behind it — not as a plug-and-play install. All form IDs, webhook URLs, domains, and company-specific identifiers have been replaced with placeholders.

## What it does

A visitor fills out a Contact Form 7 (CF7) registration form on a WordPress site. Within seconds, they're added as an attendee on an existing Google Calendar event, and Google automatically emails them an invite containing the Google Meet link — no manual list management, no spreadsheet exports, no copy-pasting emails into Calendar by hand.

The same flow also supports **one-click confirmation from email campaigns**: a tracked link in a Brevo/Mailchimp email pre-fills the visitor's data via URL parameters, so returning contacts can RSVP with a single click instead of re-typing a form.

![Architecture diagram](docs/diagrams/architecture.svg)

## Why this architecture

The brief was: ship fast, keep it maintainable by a marketing team (not just engineers), and avoid standing up infrastructure for what is fundamentally a glue job between two systems that already exist (a WordPress site and Google Calendar).

That ruled out a custom backend service or a Google Cloud Function — both are one more thing to deploy, monitor, and pay for, just to move a JSON object from point A to point B. Instead, the integration is split into two halves that each do the part they're good at:

- **WordPress (PHP) owns the "what triggered this and what page does the user see" logic** — intercepting form submissions, mapping form IDs to the right destination, redirecting to a thank-you page, and handling the one-click email confirmation flow.
- **Make.com owns the "talk to Google Calendar" logic** — OAuth2 connection handling, the actual API call to add an attendee, and triggering Calendar's native invite email.

This keeps the WordPress side free of any Google API credentials or OAuth complexity (genuinely painful to manage safely inside a shared WordPress install), and keeps the automation logic visual and auditable by non-engineers in the marketing team — important for a tool that needed to be handed off and maintained by people without an engineering background.

The trade-off is explicit and intentional: this isn't the architecture you'd pick for a multi-tenant SaaS product. It's the right architecture for "one team, one WordPress site, recurring internal events, ship it this week."

## How it works

1. A visitor submits the CF7 registration form (or arrives via a one-click email confirmation link — see below).
2. A PHP snippet hooks into CF7's `wpcf7_mail_sent` action, reads the submitted fields, and — based on which form was submitted — fires a `wp_remote_post` webhook call to the matching Make.com scenario. The call is non-blocking, so the visitor's page never waits on it.
3. Make receives the JSON payload via a Custom Webhook trigger module.
4. A Google Calendar "Update an Event" module adds the submitted email as a new attendee on the target event, with `Append = Yes` (so existing attendees are preserved) and `Send Updates = All` (so Calendar fires the invite email automatically).
5. The visitor is redirected client-side to a per-event "thank you" page.

### One-click confirmation from email campaigns

For contacts who are already in the CRM/ESP, the registration link itself can carry their data as query parameters:

```
https://example.com/webinar-slug/?nome=Jane+Doe&email=jane@example.com&cargo=Analyst&empresa=Acme+Corp&departamento=Engineering&telefone=15555550000
```

When that page loads, a script reads the parameters, shows a single "Confirm your spot, Jane?" prompt instead of the full form, and — on confirmation — programmatically fills and submits the *same* CF7 form used for manual signups. That reuse is deliberate: the email-link flow and the manual-form flow converge into one submission path, so the webhook dispatcher and the Make scenario never need to know which path the visitor came from.

A meaningful chunk of the script's logic exists to handle messy real-world ESP data: unresolved merge tags (a contact with no first name saved leaks the literal `*|FNAME|*` string into the URL), and placeholder values like `--`, `nao informado`, or `/` that some CRM exports use for blank fields. Each of those gets normalized to a safe fallback before the form is submitted, so a single bad ESP field never blocks a registration.

## Project structure

```
.
├── README.md
├── docs/
│   ├── architecture.md              # Design rationale and trade-offs
│   ├── make-scenario-setup.md       # Step-by-step Make.com configuration
│   ├── troubleshooting.md           # Real issues hit in production + fixes
│   ├── sample-webhook-payload.json  # Example JSON sent to Make
│   └── diagrams/
│       └── architecture.svg
└── src/
    ├── wordpress-snippets/
    │   ├── 01-webhook-dispatcher.php
    │   ├── 02-redirect-on-submit.php
    │   └── 03-get-param-confirmation.php
    └── styles/
        ├── registration-form.css
        └── confirmation-page.css
```

## Stack

WordPress · Contact Form 7 · Code Snippets (PHP) · Make.com · Google Calendar API · Elementor (confirmation page) · vanilla JavaScript (no build step, no dependencies)

## Documentation

- [`docs/architecture.md`](docs/architecture.md) — the full design walkthrough, including alternatives considered
- [`docs/make-scenario-setup.md`](docs/make-scenario-setup.md) — how the Make.com scenario is configured, module by module
- [`docs/troubleshooting.md`](docs/troubleshooting.md) — real failure modes encountered in production and how each was diagnosed and fixed

## Note on scope

This repo documents a working integration; it intentionally does not include the original company's form IDs, webhook tokens, domains, or Elementor element IDs. The PHP and CSS preserve the real logic and structure, with sanitized placeholders standing in for anything specific to the original deployment. The goal here is to show how the problem was approached and solved — not to provide a turnkey installer.

## Author

Built and maintained by [Daniele](https://github.com/) as part of marketing automation work for B2B webinar programs. Reach out via GitHub or LinkedIn if you'd like to discuss the implementation.
