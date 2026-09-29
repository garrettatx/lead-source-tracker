# Lead Source Tracker: v1.2 and v2 Plan

**Status:** Planning. Not approved. Written 2026-09-29 from a code review of both branches, a live
test on a WP Engine client site, and AI research (`research/`). **Goal:** one plugin for every client
site, whatever the host or form plugin, with setup a non-developer can finish.

**This repo stays client-neutral.** No client names, field IDs or CRM-specific code live here.
A client's mappings are saved in that site's plugin settings, and its setup notes live in that
client's own workspace. There are no per-client or per-integration branches.

---

## Summary

**One version for every host.** Full-page caching isn't only a WP Engine thing: Cloudflare APO,
LiteSpeed, Kinsta, SiteGround and WP Rocket all serve cached HTML. So the plugin captures in the
browser on every host, with one code path, and the `wp-engine` branch becomes `main`. Host detection
is useful only to explain things during setup.

**Embedded forms are generic.** Any form rendered as HTML on the page, including CRM embeds that
name fields their own way, is handled by a field map: a form selector plus lines that map a plugin
field to an input name. Nothing CRM-specific goes in the code.

---

## Common Core, Thin Edges

Drift so far came from keeping two copies of the code (`main` and `wp-engine`) and two copies of the
domain lists (PHP and JS). The fix is one core that every site runs unmodified, with everything that
varies expressed as configuration.

| What varies | How it's handled | Never |
|---|---|---|
| Host (WP Engine, other hosts, cached or not) | Nothing varies. Browser capture plus server fill at submit works everywhere | A host branch or a compatibility mode |
| Install type (regular plugin or mu-plugin) | Same code. The mu-plugin option is a three-line loader that requires the plugin's main file | A separate mu-plugin build |
| CRM or embedded form (any CRM that embeds an HTML form) | A field map: form selector plus field-to-input-name lines, supplied by the site's config | CRM code or client IDs in the core |
| Client settings (timeouts, GBP campaign, marketplaces, exclusions) | The same config structure | Edits to the core file |
| Domain lists | One list in PHP, passed to the browser script as JSON | A second copy in JS |

**Where config lives:** in v1.2 (still an mu-plugin, no settings screen), a site supplies its config
through the `gd_ls_config` filter from a small per-site snippet, version-controlled in that client's
workspace. In v2 a settings screen edits the same structure and saves it to an option; the filter
still wins, so a snippet-configured site keeps working after the upgrade.

**Updating a site** means replacing the core file only. Its config snippet stays put.

---

## Repo Structure

One repo, one long-lived branch (`main`), short-lived `feature/*` branches merged back.

```
lead-source-tracker/
├── gd-lead-source-tracker.php   plugin file (v1.2: still a single-file mu-plugin)
├── assets/js/lst.js             v2: the browser script, moved out of inline
├── includes/                    v2: settings, adapters/, server fill, REST route
├── tests/                       classifier fixtures and tests
├── docs/
│   ├── v2-plan.md               this file
│   ├── research/                inputs and the v1.1 code extract
│   └── setup/                   v2: quick start, one page per form plugin, embedded forms, QA
├── README.md
├── CHANGELOG.md
└── PROJECT-TICKETS.md           tickets 1-3, partly superseded by this plan
```

**Branch consolidation: done 2026-09-29.** `wp-engine` and `feature/v1.2-hardening` merged into
`main`; `wp-engine` deleted locally and on GitHub. Tag `v1.1.0` marks the last `wp-engine` release
(what existing v1.1 sites run), `v1.2.0` the merge.

---

## v1.2 (Built 2026-09-29, Not Yet Installed Anywhere)

Released as tag `v1.2.0` on `main`. What it contains is in the [CHANGELOG](../CHANGELOG.md); how to
test it before and after install is in [the QA plan](setup/qa-e2e-plan.md).

It brought forward several items first planned for v2: server fill at submit (B7), the Safari
cookie refresh (B10), one copy of the domain lists (B12), debug output (B14) and consent handling.
It keeps v1.1's cookie names, shortcodes and mu-plugin install, so a site configured for v1.1 keeps
working after the file is replaced.

**Status of the tests:** PHP server checks pass; 23 engine tests pass; 18 of 18 mutations caught;
28 of 28 browser fixture checks pass in Chrome. Not yet run: a real staging install with page
caching, real Formidable and Gravity submissions, and Safari retention.

**Left for v2:** the versioned JSON records and full first-touch fields (v1.2 keeps first touch as
one line), the setup screens, adapters that add fields automatically, Fluent Forms, Kadence, Ninja
Forms and WPForms, and the regular-plugin packaging.

### Consent Backlog

v1.2 has the core: modes, the WP Consent API check and change event, the `gd_ls:consent` event, and
deletion on refusal. Filed for later, from research on 2026-09-29:
- Register the plugin's cookies with `wp_add_cookie_info()` so consent plugins list them.
- Separate filters for storing and for filling fields (`gd_ls_can_persist_attribution`,
  `gd_ls_can_populate_attribution_fields`), each with a context array, and a
  `gd_ls_consent_state_changed` action.
- Adapters for consent tools that don't support the WP Consent API.
- A stricter site default ("no positive signal, no storage") is already available as `event` mode.
  It isn't the global default, because on a site with no consent tool the plugin would never store.

## Attribution Model

**Last non-direct touch as the primary record, plus first touch alongside it.** Pure last touch is
close to right, with one change: a direct visit never overwrites a real source. Someone who finds a
business on Google and types the URL a week later is a Google lead. GA4 and Google Ads both work
this way, so CRM numbers line up with theirs.

First touch shows which channel introduced someone who researched for weeks (often SEO content or a
Business Profile); last touch shows what brought them back to convert.

**Two different jobs, kept apart.** Source, medium, channel and the summary line are for people
reading reports, and they follow last non-direct plus first touch. Click IDs are for ad-platform
imports and reconciliation, and they're kept separately for 90 days whatever later visits do. So
there's no summary that prefers paid clicks: last non-direct already credits the ad when the
converting visit came from one, and preferring an older ad click over-credits Ads.

### Rules

| Arrival | What happens |
|---|---|
| Has UTM parameters or a click ID, or an external referrer | **New touch.** Rewrite the entire last-touch record at once: source, medium, campaign, term, content, referrer, landing page, timestamp. Any field this visit lacks is cleared, not kept from an older visit. If there's no first-touch record yet, write it too |
| No referrer and no parameters (direct) | Write direct / none only if there's no record, or the last touch has expired. Otherwise do nothing |
| Referrer is the site itself | Nothing. UTMs on internal links are ignored too, so an internal banner can't reset the source |
| Referrer on the exclusion list (payment pages, sign-in, the site's other domains) | Nothing |
| Any click ID (gclid, gbraid, wbraid, msclkid) | Stored in its own record with its own date, kept 90 days even if later visits change the last touch |
| Form submit | Add the page the form was on and the time. These are never stored |
| "How did you hear about us?" | A separate form field that tracking never overwrites |

**Timeouts:** first touch 90 days; last touch 30 days after the most recent touch; click IDs 90 days
(Google Ads' click window). In Safari, cookies set by JavaScript last 7 days at most, whatever the
plugin asks for; see B10.

### Channels

A derived `channel` field, named after GA4's default channel groups so reports read the same:

| Channel | Rule (checked in this order) |
|---|---|
| Paid Search | gclid, gbraid, wbraid or msclkid; or medium `cpc` / `ppc` / `paid-search` |
| Google Business Profile | Source `google` and a campaign matching the site's GBP setting (for example `gbp-listing`), or medium `local` |
| Paid Social | Medium `paid-social` / `cpm` / `paid`, with a social source |
| AI Assistant | An AI host or source (list below). Medium `ai-assistant`, matching GA4's channel since 2026-05-13 |
| Organic Search | Search engine referrer, or medium `organic` |
| Organic Social | Social referrer, or medium `social` |
| Email | Medium `email`, or a webmail referrer (Gmail, Outlook, Yahoo Mail) |
| SMS | Medium `sms` |
| Marketplace | A per-site list, for example Angi, HomeAdvisor, Houzz, Yelp, Thumbtack, Porch. Off by default |
| Referral | Any other external site |
| Direct | Nothing else |

**Sources are normalized to lowercase names** (`google`, `bing`, `chatgpt`, `facebook`). Today a
referrer visit stores `Google` and a UTM visit stores `google`, which splits one source in two.
Common variants are mapped too: `chatgpt.com` to `chatgpt`, `fb` to `facebook`, `ig` to `instagram`.
Raw values are kept beside normalized ones.

**Domain lists** (one copy in PHP, passed to the script as JSON; reviewed quarterly):
- **Search:** google.* (and the Android Google app referrer), bing.com, yahoo.*, duckduckgo.com,
  ecosia.org, search.brave.com, startpage.com, qwant.com, kagi.com, yandex.*, baidu.com, naver.com,
  seznam.cz, aol.com, ask.com
- **AI:** chatgpt.com, chat.openai.com, perplexity.ai, claude.ai, gemini.google.com, copilot.microsoft.com,
  meta.ai, grok.com, chat.deepseek.com, chat.mistral.ai, you.com, phind.com, poe.com
- **Social:** facebook.com (m., l., lm.), fb.com, fb.me, instagram.com, linkedin.com, lnkd.in, x.com,
  twitter.com, t.co (exact host only), pinterest.*, pin.it, tiktok.com, reddit.com, threads.net,
  youtube.com, youtu.be, nextdoor.com, snapchat.com, bsky.app
- **Webmail:** mail.google.com, outlook.live.com, outlook.office.com, mail.yahoo.com, mail.aol.com
- **Excluded by default:** the site's own hosts, accounts.google.com, paypal.com, checkout.stripe.com

---

## Findings From the Code Review

Reviewed the `wp-engine` branch (v1.1.0-wpengine). B1 and B2 were confirmed by running the plugin's
own matching code against the hostnames listed.

| # | Problem | Effect |
|---|---|---|
| B1 | **Hosts are matched as substrings.** `t.co` and `x.com` match inside other names | Target, Dropbox, Home Depot and Microsoft Copilot are recorded as **Twitter / social** |
| B2 | **Search is checked before AI,** with `google.` matching any Google host | Gemini, Gmail and Google Docs are recorded as **Google / organic** |
| B3 | **A first direct visit locks the record.** Organic can't overwrite an existing source | Typed the URL, then came back from Google: recorded as direct for 30 days |
| B4 | **UTM visits overwrite only the fields they carry.** Landing page and referrer are first-visit only | The stored record mixes visits: last campaign, earlier term, a stale gclid, first landing page |
| B5 | **ChatGPT links carry `utm_source=chatgpt.com` with no medium** | Stored as source `chatgpt.com` with an empty or stale medium, never as AI |
| B6 | **Only gclid is captured,** and it isn't validated (Ticket 1c) | iOS Google Ads clicks (gbraid, wbraid) and Microsoft Ads (msclkid) are missed |
| B7 | **Server-side defaults get baked into cached pages.** Formidable shortcode defaults and Gravity's `gform_field_value_` render at page generation, and the README recommends them. The browser fill skips any field without a cookie (`if (!val) return;`) | On a cached host, one visitor's values can be served to others. Likely rather than proven; test on staging with cache on |
| B8 | **The MutationObserver re-runs about 20 selectors per field on every page change,** undebounced | Wasted work on pages with carousels and chat widgets |
| B9 | **Values are set without firing `input` / `change` events,** and only on hidden or text inputs | Forms with conditional logic or scripted state can miss the values |
| B10 | **Cookies are set by JavaScript only** | Safari caps those at 7 days, and iPhone traffic is a large share for local service businesses |
| B11 | **No length caps** (Ticket 1b), and the landing page keeps its whole query string | Oversized cookies, and any personal data in a URL gets stored |
| B12 | **Two copies of the domain lists** (PHP and JS), and the PHP classifier isn't used on this branch | They drift apart |
| B13 | **`main` still captures in PHP,** which fails behind any full-page cache | The "standard host" branch is wrong on most modern hosts too |
| B14 | **Logged-in editors are skipped with no debug mode** | Hard to QA without a private window |

What held up: capturing in the browser was the right call, first-party cookies are the right store,
the AI list was a good start, and the population fallbacks (1.5 s delay, observer) cover forms that
render late.

---

## v2 Architecture

**One plugin, one code path, adapters per form system.**

1. **Capture engine (browser).** Reads UTMs, click IDs, referrer and landing page; classifies with
   host-suffix matching (`host === d` or ends with `.d`), most specific first. Writes three
   versioned JSON records: first touch (`gd_ls_v2_ft`), last touch (`gd_ls_v2_lt`) and click IDs
   (`gd_ls_v2_ids`), each with UTC and Unix timestamps. **Stored in cookies and mirrored to
   localStorage,** read from whichever copy is valid and newer. Cookie-primary is a compatibility
   choice: the server fill at submit and the shortcodes read cookies. v2 can invert it once those
   dependencies are retired. Safari limits how long script-written storage lasts, so retention is
   measured in Safari during QA rather than assumed from the nominal expiry. Malformed JSON or a
   storage error falls back to the other copy, then to nothing, without a JS error. **Legacy names:** keeps writing the v1 `gd_ls_*`
   cookies, mapped to last touch, until every site is on v2.
2. **Cookie refresh (server, host-agnostic).** After capture, one small `POST` to a REST route
   re-sends the same cookies from the server. A POST is never page-cached, and cookies the server
   sets aren't subject to Safari's cap on script-set cookies (verify in Safari during QA). Fixes B10
   without host detection.
3. **Adapters.** Each knows how to (a) add the hidden fields to a form, (b) fill them in the browser
   and fire `input` / `change`, and (c) where the form posts to WordPress, fill them again on the
   server at submit from the cookies that came with the POST. Step (c) never touches a cached page,
   so it fixes B7. Embedded forms use the v1.2 field map.
4. **Settings and setup** (below). A regular plugin rather than an mu-plugin, with an optional
   mu-plugin loader for sites where someone might deactivate it.
5. **Script as a file** (`assets/js/lst.js`, enqueued, versioned) for strict-CSP sites (Ticket 1d)
   and browser caching.
6. **Hooks.** PHP: `gd_ls_config` (site config), `gd_ls_can_store` (consent), `gd_ls_normalize_channel`,
   `gd_ls_export_payload`. Browser events: `gd_ls:captured`, `gd_ls:populated`, `gd_ls:submit`, and
   a listened-for `gd_ls:consent` event for consent tools.

### Fields (Canonical Keys)

Last touch: `source`, `medium`, `campaign`, `term`, `content`, `channel`, `referrer`,
`landing_page`, `touch_time`. First touch: the same with a `ft_` prefix. Click IDs: `gclid`,
`gbraid`, `wbraid`, `msclkid`, `click_time`. At submit: `form_page`, `submit_time`. Plus `summary`,
one human-readable line for CRMs and emails:
`google / organic · Organic Search · /bathroom-remodel/ · first: google / organic · 2026-09-12`.

Every adapter and field map uses these keys, so a CRM can take the full set or a small subset. A
JSON export of the same data is available for Zapier and webhooks.

### Form Adapters, in Build Order

Order follows the agency's client stack; a commercial release would put Gravity and WPForms first.

| # | Adapter | Add fields | Browser fill | Server fill at submit |
|---|---|---|---|---|
| 1 | Formidable | Formidable's field API | By field key | `frm_pre_create_entry` |
| 2 | Gravity Forms | `GFAPI` | By input name | `gform_pre_submission` |
| 3 | Fluent Forms | Form JSON | By name | `fluentform/insert_response_data` |
| 4 | Kadence Advanced Form | Block attributes | By name | Hook to confirm |
| 5 | Ninja Forms | Field API | By key | `ninja_forms_submit_data` |
| 6 | WPForms | Form JSON | By name | `wpforms_process_filter` |
| 7 | Contact Form 7 | Form tag (existing) | By name | `wpcf7_posted_data` |
| any | Embedded HTML form | Field map | Adopt or inject by name; fill again on submit | None |

Hook names are from memory of each plugin's API. Confirm each against the installed version at
build time.

---

## Setup

Settings > Lead Source Tracker opens a four-step setup. It detects what it can and asks only for what
it can't.

1. **Check the site.** Detects the host, page caching, a consent plugin, and active form plugins,
   then shows plain-language results, for example "Found Formidable Forms (3 forms). Page caching is
   on." The user confirms or corrects the list rather than being asked cold what they use.
2. **Choose settings.** Timeouts, the GBP campaign value, the marketplace list, extra domains to
   exclude. Defaults work for most sites.
3. **Connect forms.** Each detected WordPress form shows whether its tracking fields are present,
   with an **Add tracking fields** button. Embedded forms stay out of setup and use a field map
   (settings screen or config snippet), documented on its own page.
4. **Test.** Test links (`?utm_source=lst-test&utm_medium=test&utm_campaign=onboarding`), a debug
   panel for admins at `?gd_ls_debug=1`, and a checklist: open the link in a private window, submit,
   confirm the values on the entry or CRM record.

**Docs (`docs/setup/`):** quick start; one page per form plugin; embedded forms and the field map; a
plain-language page for clients on first and last touch; channel definitions; troubleshooting
(caching, Safari, consent, "the values are empty"); the QA checklist.

**Consent:** the `gd_ls_can_store` filter and the `gd_ls:consent` event, with optional support for
common consent tools later.

---

## Phases

| Phase | Scope | Proves it's done |
|---|---|---|
| 0 | Branch consolidation (above) | Done 2026-09-29 |
| 1a, v1.2 | Built; see v1.2 above | Staging QA plan passes; embedded form values arrive in the CRM; an existing Formidable site still records entries |
| 1b, Core | First-touch and last-touch records; B7, B10, B12, B14 fixed; server fill for Formidable; debug panel; classifier tests | Every QA case passes on a staging site with page caching on, then production |
| 2, Setup | Setup screens, auto-add fields, Gravity and Fluent adapters, docs | A second site installed from the docs alone |
| 3, Coverage | Kadence, Ninja, WPForms adapters; Safari cookie refresh; consent integrations | Adapter tests per plugin |
| 4, Later | Google Ads offline conversion export; WooCommerce order meta (Ticket 3g) | |

### QA Matrix

Google organic click; tagged GBP link; Google Ads click (gclid); direct first, then Google (B3);
ChatGPT link with `utm_source=chatgpt.com` (B5); a Gemini referrer (B2); a Target or Dropbox referrer
(B1); an internal UTM link; a submit on the first page of a visit; a return visit after a UTM visit
(B4); an iPhone Safari visit (B10); a form rendered late by AJAX. Classifier cases run as automated
tests, and every guard gets a mutation test: break it and confirm a test fails.

---

## Research Reconciliation

AI research (`research/`) was weighed against the code review and a live site test. Adopted: the
combined first-touch and last-touch model; one plugin with adapters; browser capture on every host;
versioned JSON records; separate first-touch and last-touch timeouts; raw values kept beside
normalized ones; consent filter plus browser event; JS events; an admin debug panel; a JSON export
for Zapier; CRMs as downstream consumers, not core logic.

| Research said | Why this plan differs |
|---|---|
| A "Compatibility Mode: Auto / Standard / WP Engine" setting | No separate code path. Host detection stays, for setup and troubleshooting only |
| localStorage as the primary store, cookies optional | Cookies primary for now, because server fill and shortcodes read them. Revisit in v2 |
| A summary field that prefers any paid click | Over-credits Ads. Click IDs are kept separately for Ads import |
| GBP tagged `utm_medium=local` | Keeping `organic` with a GBP campaign keeps GA4 in Organic Search. The channel rule accepts either |
| Rename the prefix to `acw_` | Would break working sites. Keep `gd_ls_`, add `ft_` names in v2 |
| A session-storage submit guard | Deduping conversion tags belongs in GTM. A guard against repeated injection or refresh calls on multi-step forms may earn a place in v2 |
| Channel list without AI | AI Assistant stays, matching GA4 |

## Decisions Needed

1. **Attribution model:** last non-direct plus first touch (recommended), or last touch only.
2. **Timeouts:** first touch 90 days, last touch 30 rolling (recommended), or 30 days for everything.
3. **Package:** a regular plugin with settings (recommended), or stay an mu-plugin.
4. **Branches:** consolidate to `main` now (recommended).
5. **Audience:** agency-only (adapter order above), or a product for other agencies.

## Not Verified

- The form plugins' hook names (confirm at build time).
- Safari retention of script-set and server-refreshed cookies (QA on an iPhone).
- Whether B7 contaminates on WP Engine (staging test with cache on).
