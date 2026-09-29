# Changelog

All notable changes to this plugin are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Plugin header fields `Plugin URI`, `Author URI`, `Tested up to` (WordPress 7.1, the version it was
  tested on) and `Update URI: false`, which stops WordPress from offering a wordpress.org update for
  a plugin with a similar name.

## [1.2.1] - 2026-09-29

### Fixed
- The server-side fill at submit deleted percent-encoded characters from landing pages and
  referrers: `utm_term=handyman%20austin` became `handymanaustin`. `sanitize_text_field()` strips
  `%xx` sequences, so the plugin now uses its own sanitizer, matching the browser script. Found in
  the first staging test submission.

## [1.2.0] - 2026-09-29

One core for every host. Browser capture, introduced in 1.1.0, is now the only code path. Field
names, shortcodes and the mu-plugin install are unchanged from 1.1.0.

### Added
- Field maps for embedded forms that use their own field names, such as a CRM form rendered on the
  page. Fields are added or taken over at page load and filled again at submit; the embed code isn't
  edited.
- Site config through the `gd_ls_config` filter, from a per-site file beside the core.
- Fields `channel` (GA4-style channel groups, including AI Assistant and Google Business Profile),
  `source_medium`, `first_touch`, `form_page`, `gbraid`, `wbraid`, `msclkid` and `click_time`.
- Consent modes: `auto` (WP Consent API when present), `wp_consent_api`, `event` and `off`. Nothing
  is stored or filled until consent allows it, and cookies are deleted on refusal.
- Server fill at submit for Formidable, Gravity Forms and Contact Form 7, from the visitor's cookies.
- A REST route that re-sends cookies from the server after a new touch, for Safari's 7-day limit on
  cookies set by JavaScript.
- `?gd_ls_debug=1` console output, and `window.gdLeadSource` for inspection.
- Tests: PHP harness, engine suite, mutation check, browser fixture.
- Docs: setup guides, QA and E2E plan, roadmap.

### Changed
- Sources are lowercase (`google`, not `Google`), so referrer and UTM visits group together.
- `gd_ls_landing_page`, `gd_ls_referrer` and `gd_ls_timestamp` describe the last touch, not the
  first visit. The first visit is in `gd_ls_first_touch`.
- The referrer keeps origin and path only. The landing page keeps only UTM parameters.
- `GD_LS_COOKIE_DAYS` sets the last-touch window. First touch and click IDs default to 90 days.
- Domain lists live once in PHP and are passed to the browser as JSON.

### Removed
- The rule that filled any input inside an element with class `source`, `content` or `term`. It
  overwrote visible fields. Bare v1.1 names on hidden inputs still work.
- The separate `wp-engine` branch. `main` holds the only version.

### Fixed
- Hosts were matched as substrings: Target, Dropbox, Home Depot and Microsoft Copilot were recorded
  as Twitter.
- Search was checked before AI: Gemini, Gmail and Google Docs were recorded as Google organic.
- A first direct visit locked the source for the whole cookie window.
- A new tagged visit kept term, content and campaign from older visits, and landing page and
  referrer from the first visit.
- ChatGPT's `utm_source=chatgpt.com` links had no medium and were never counted as AI.
- Form population ran undebounced on every DOM change and set values without `input` or `change`
  events.

### Security
- Hidden-field values rendered into cached pages are replaced in the browser and again on the
  server, so one visitor's values can't reach another.
- Click IDs are format-checked; all values are length-capped and stripped of tags, quotes and
  control characters.
- Personal data in landing-page query strings is no longer stored.

## [1.1.0] - 2026-03-05

### Changed
- Cookie capture moved from PHP (`template_redirect`) to browser JavaScript, so it works on hosts
  with full-page caching such as WP Engine. Released on the `wp-engine` branch.
- The first-visit timestamp uses the WordPress site time zone, matching admin screens and form
  emails.

### Security
- The landing page uses `home_url()` instead of `SERVER_NAME`, which a spoofed host header could
  change.

## [1.0.0] - 2026-03-05

### Added
- Initial release as a must-use plugin.
- Cookie capture of UTM parameters, gclid, referrer and landing page via `template_redirect`.
- Referrer classification for search engines, social platforms and AI tools.
- Hidden-field population for Formidable Forms, Contact Form 7 and Gravity Forms.
- Shortcodes for email notifications, including `[gd_ls_summary]`.

[Unreleased]: https://github.com/garrettatx/lead-source-tracker/compare/v1.2.1...HEAD
[1.2.1]: https://github.com/garrettatx/lead-source-tracker/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/garrettatx/lead-source-tracker/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/garrettatx/lead-source-tracker/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/garrettatx/lead-source-tracker/releases/tag/v1.0.0
