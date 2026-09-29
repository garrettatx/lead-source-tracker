# Lead Source Tracker: Plan

**Status (2026-09-29):** v1.2.1 is live on one production site, a WP Engine site, with a Formidable
form. Its embedded CRM form waits on field IDs. v2 is planned, not approved.

**Goal:** one plugin for every client site, on any host, with any common form plugin, and setup a
non-developer can finish.

---

## Principles

**One core, no forks.** Every site runs the same `gd-lead-source-tracker.php`. Capture runs in the
browser, which works behind any full-page cache (WP Engine, Cloudflare, LiteSpeed, Kinsta,
WP Rocket), so no host needs its own version.

**Everything that varies is configuration.**

| Varies by site | Where it lives |
|---|---|
| Timeouts, GBP campaign, marketplaces, extra domains | `gd_ls_config` filter in a per-site file |
| Embedded CRM forms (their own field names) | A field map in the same file |
| Domain lists | One copy in PHP, sent to the browser as JSON |

The per-site file is kept in the client's workspace. This repo holds no client names, field IDs or
CRM code. Updating a site means replacing the core file only.

**Branches:** `main` only. `wp-engine` was merged and deleted on 2026-09-29. Tag `v1.1.0` is the last
`wp-engine` release; roll back a v1.1 site to it if needed.

---

## v1.2 (Released)

Contents are in the [CHANGELOG](../CHANGELOG.md), setup in [docs/setup](setup/), testing in the
[QA plan](setup/qa-e2e-plan.md).

**Tested:**
- Automated: PHP server checks, 23 engine tests, 18 of 18 mutations caught, 28 browser fixture checks.
- Staging (WP Engine, page cache on): tagged, direct, ChatGPT, GBP, internal and referral visits;
  the cookie refresh route; no visitor data in cached HTML; a Formidable submission.
- Production: a real Google organic click, and two Formidable submissions.

**Found in testing:** the server-side fill stripped `%20` from URLs. Fixed in v1.2.1.

**Not yet tested:** an embedded CRM form submission, Gravity Forms and Contact Form 7 on a real site,
Safari cookie retention past 7 days.

---

## Attribution Model

Last non-direct touch, plus a one-line first touch, plus ad click IDs kept separately. Rules and
fields are in [fields and channels](setup/fields-and-channels.md).

**Why last non-direct:** a direct visit never overwrites a real source, the same rule GA4 and Google
Ads use, so CRM counts line up with theirs.

**Why first touch too:** remodel leads research for weeks. First touch shows what introduced them.

**Why no "paid wins" summary:** last non-direct already credits the ad when the converting visit came
from one. Preferring an older ad click over-credits Ads. Click IDs are kept for ad-platform imports,
which is their only job.

---

## Code Review Findings (v1.1)

Fixed in v1.2. Kept as the record of why v1.2 changed what it did.

| # | Problem in v1.1 | Effect |
|---|---|---|
| B1 | Hosts matched as substrings (`t.co`, `x.com`) | Target, Dropbox, Home Depot and Copilot recorded as Twitter |
| B2 | Search checked before AI, `google.` matching any Google host | Gemini, Gmail and Google Docs recorded as Google organic |
| B3 | A first direct visit locked the record | Typed the URL, then came from Google: recorded as direct |
| B4 | UTM visits overwrote only the fields they carried | Records mixed visits: new campaign, old term, first landing page |
| B5 | ChatGPT's `utm_source=chatgpt.com` had no medium | Never counted as AI |
| B6 | Only gclid captured, unvalidated | iOS Google Ads and Microsoft Ads clicks missed |
| B7 | Server-side defaults rendered into cached pages | One visitor's values could reach others |
| B8 | Undebounced MutationObserver, ~20 selectors per field | Wasted work on busy pages |
| B9 | No `input` / `change` events on fill | Scripted forms could miss values |
| B10 | Cookies set by JavaScript only | Safari caps them at 7 days |
| B11 | No length caps; landing page kept its full query string | Oversized cookies; personal data in URLs stored |
| B12 | Domain lists in PHP and JS | The copies drifted |
| B13 | `main` captured in PHP | Failed behind any full-page cache |
| B14 | No debug mode | Hard to QA |

---

## v2 Scope

| Item | Detail |
|---|---|
| Versioned JSON records | `gd_ls_v2_ft`, `gd_ls_v2_lt`, `gd_ls_v2_ids` with UTC and Unix timestamps; full first-touch fields instead of one line. Cookies stay primary while the server fill and shortcodes read them; mirror to localStorage. Keep writing the v1 `gd_ls_*` cookies until every site is on v2 |
| Regular plugin with settings | A settings screen edits the same structure as `gd_ls_config`; the filter still wins, so file-configured sites keep working. Optional mu-plugin loader |
| Setup screens | Detect host, caching, consent plugin and form plugins; show results in plain language; **Add tracking fields** per detected form; test links and a checklist. Embedded forms stay out of setup and use the field map |
| Form adapters | Add fields automatically and fill them on the server at submit. Order: Formidable, Gravity Forms, Fluent Forms, Kadence Advanced Form, Ninja Forms, WPForms, Contact Form 7 |
| Script as a file | `assets/js/lst.js`, enqueued and versioned, for strict-CSP sites |
| Hooks | `gd_ls_normalize_channel`, `gd_ls_export_payload`; browser event `gd_ls:submit`; a JSON export for Zapier and webhooks |
| Later | Google Ads offline conversion export; WooCommerce order meta |

**Adapter hooks to confirm at build time** (from memory, unverified): Formidable
`frm_pre_create_entry`, Gravity `gform_pre_submission`, Fluent `fluentform/insert_response_data`,
Ninja `ninja_forms_submit_data`, WPForms `wpforms_process_filter`, CF7 `wpcf7_posted_data`.

**Target repo layout:** `gd-lead-source-tracker.php`, `assets/js/`, `includes/` (settings,
adapters, server fill, REST), `tests/`, `docs/`.

### Consent Backlog

v1.2 has consent modes, the WP Consent API check and change event, the `gd_ls:consent` event, and
deletion on refusal. Still to do:
- Register the cookies with `wp_add_cookie_info()` so consent plugins list them.
- Separate filters for storing and for filling fields, each with a context array, and a
  `gd_ls_consent_state_changed` action.
- Adapters for consent tools without WP Consent API support.

"No signal, no storage" is available as `event` mode. It isn't the default, because a site with no
consent tool would never store anything.

---

## Research Reconciliation

AI research in [research/](research/) was weighed against the code review and live tests.

**Adopted:** first and last touch together; one plugin with adapters; browser capture on every host;
versioned records; separate timeouts; raw values kept beside normalized ones; consent filter and
browser event; JS events; an admin debug view; a JSON export; CRMs as consumers of the data.

| Research proposed | Decision |
|---|---|
| A WP Engine compatibility mode | Not needed; one code path works everywhere |
| localStorage as the primary store | Cookies stay primary while the server fill reads them |
| A summary that prefers paid clicks | Over-credits Ads |
| GBP tagged `utm_medium=local` | Accepted, but `organic` plus a GBP campaign keeps GA4's grouping. Both map to Google Business Profile |
| Renaming the prefix to `acw_` | Would break live sites |
| A session-storage submit guard | Conversion dedupe belongs in GTM. Possible v2 use against repeated fills on multi-step forms |

---

## Decisions Needed for v2

1. **Package:** a regular plugin with settings (recommended), or stay an mu-plugin.
2. **Audience:** agency-only, or a product for other agencies. A product moves Gravity Forms and
   WPForms to the front of the adapter list and needs docs that assume less.
