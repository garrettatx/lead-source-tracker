# Changelog

## v1.2.1 - 2026-09-29

**Fixed**
- The server-side fill at submit deleted percent-encoded characters from landing pages and
  referrers (`utm_term=handyman%20austin` became `handymanaustin`). `sanitize_text_field()` strips
  `%xx` sequences; the plugin now uses its own sanitizer, matching the browser script. Found in the
  first staging test submission.

## v1.2.0 - 2026-09-29

One core for every host: browser capture, which the `wp-engine` branch introduced, is now the only
path. Field names, shortcodes and the mu-plugin install are unchanged from v1.1.

**Added**
- Field maps for embedded forms with their own field names (a CRM form rendered on the page). Fields
  are added or taken over at page load and filled again at submit; the embed code isn't edited.
- Site config through the `gd_ls_config` filter, from a per-site file beside the core.
- Fields `channel` (GA4-style channel groups, including AI Assistant and Google Business Profile),
  `source_medium`, `first_touch`, `form_page`, `gbraid`, `wbraid`, `msclkid`, `click_time`.
- Consent modes: `auto` (WP Consent API when present), `wp_consent_api`, `event`, `off`. Nothing is
  stored or filled until consent allows it; cookies are deleted on refusal.
- Server fill at submit for Formidable, Gravity Forms and Contact Form 7, from the visitor's cookies.
- A REST route that re-sends cookies from the server after a new touch, for Safari's 7-day limit on
  script-set cookies.
- `?gd_ls_debug=1` console output, and `window.gdLeadSource` for inspection.
- Tests: PHP harness, engine suite, mutation check, browser fixture.

**Fixed**
- Hosts were matched as substrings: Target, Dropbox, Home Depot and Microsoft Copilot were recorded
  as Twitter.
- Search was checked before AI: Gemini, Gmail and Google Docs were recorded as Google organic.
- A first direct visit locked the source for the whole cookie window.
- A new tagged visit kept term, content and campaign from older visits, and landing page and referrer
  from the first visit.
- ChatGPT's `utm_source=chatgpt.com` links had no medium and were never counted as AI.
- Click IDs weren't format-checked; values had no length caps.
- Any input inside an element with class `source`, `content` or `term` could be overwritten, including
  visible fields. That rule is removed; bare v1.1 names still fill hidden inputs.
- Hidden-field values baked into a cached page are replaced, including with empty.
- Form population ran undebounced on every DOM change, and set values without `input`/`change` events.
- The landing page kept its whole query string, including any personal data; only UTMs are kept.

**Changed**
- Sources are lowercase (`google`, not `Google`), so referrer and UTM visits group together.
- `gd_ls_landing_page`, `gd_ls_referrer` and `gd_ls_timestamp` describe the last touch, not the very
  first visit. The first visit is in `gd_ls_first_touch`.
- The referrer keeps origin and path only.
- `GD_LS_COOKIE_DAYS` now sets the last-touch window. First touch and click IDs default to 90 days.

## v1.0.0 - 2025-03-05

- Initial release
- Server-side cookie capture via `template_redirect`
- Client-side form population for Formidable Forms, Contact Form 7, and Gravity Forms
- Referrer classification for search engines, social platforms, and AI tools
- Shortcodes for email notification templates
- Gravity Forms integration via `gform_field_value_` filters
