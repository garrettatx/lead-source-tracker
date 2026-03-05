# GD Lead Source Tracker

**Version:** 1.1.0-wpengine
**Author:** Garrett Digital
**Type:** WordPress Must-Use Plugin (mu-plugin)

> **You are on the `wp-engine` branch.** This is the WP Engine adaptation of the plugin. It moves all cookie capture logic to client-side JavaScript to work around WP Engine's aggressive full-page cache. For the standard PHP-based version, see the `main` branch.

## What's Different on This Branch

WP Engine caches pages at the server level. When a cached page is served, PHP hooks like `template_redirect` don't fire. This means the server-side `gd_ls_capture()` function never runs for the majority of visitors.

**This branch removes the `template_redirect` hook and `gd_ls_capture()` function entirely.** All cookie capture logic has been moved into the inline JavaScript that runs in the footer. The JS handles:

- Parsing `window.location.search` for UTM parameters and gclid
- Reading `document.referrer` for referrer classification
- Classifying the referrer against the same search/social/AI domain lists (ported from PHP to JS)
- Writing cookies via `document.cookie` with the same `gd_ls_` prefix and 30-day expiration
- Following the same first-touch / last-touch attribution rules

Everything else is unchanged: the PHP shortcodes, form plugin integrations (Formidable, CF7, Gravity Forms), and cookie names all work identically. PHP shortcodes read from `$_COOKIE`, which is populated by JS-set cookies on the next page request.

**First-visit behavior:** On a visitor's very first pageview, JS sets cookies and populates form fields in the same page load. If the visitor submits a form on that very first page, the JS form population path is the reliable one. Shortcode-based hidden field defaults (e.g., `[gd_ls_source]` as a Formidable default value) will be empty on that first visit but populated correctly on every subsequent page.

---

## What It Does

Captures traffic attribution data (UTM parameters, gclid, referrer, landing page) on a visitor's first pageview and stores it in cookies. When the visitor fills out a form, the plugin populates hidden fields with that attribution data so you know where each lead came from.

Supports Formidable Forms, Contact Form 7, and Gravity Forms out of the box.

## Installation

Upload `gd-lead-source-tracker.php` to `/wp-content/mu-plugins/`. MU-plugins load automatically. No activation step needed.

## How It Works

### Cookie Capture (Client-Side, JavaScript — WP Engine Adaptation)

On every front-end page load, the inline JS runs this logic:

1. **Guard checks** skip logged-in editors/admins (applied in PHP before the script outputs).
2. **UTM parameters** in the URL (`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `gclid`) are read from `window.location.search` and sanitized.
3. **gclid auto-classification** sets source to "Google" and medium to "cpc" when gclid is present but UTMs are missing.
4. **Referrer classification** kicks in when no UTMs are present. The JS reads `document.referrer` and checks it against the same domain lists for search engines, social platforms, and AI tools, then assigns source/medium accordingly.
5. **First-touch vs. last-touch** behavior is identical to the PHP version. Organic/social/referral sources only write cookies if no source cookie exists yet (first-touch). UTM-tagged visits always overwrite (last-touch for paid campaigns).
6. **Referrer, landing page (`window.location.href`), and timestamp** are captured once and never overwritten.

### Cookie Names

All cookies use the `gd_ls_` prefix:

| Cookie | Contents | Set When |
|--------|----------|----------|
| `gd_ls_source` | Traffic source (Google, Facebook, direct, etc.) | First visit or UTM visit |
| `gd_ls_medium` | Traffic medium (organic, cpc, social, referral, ai-referral, none) | First visit or UTM visit |
| `gd_ls_campaign` | UTM campaign name | UTM visit only |
| `gd_ls_term` | UTM term / keyword | UTM visit only |
| `gd_ls_content` | UTM content variant | UTM visit only |
| `gd_ls_gclid` | Google Ads click ID | When gclid param present |
| `gd_ls_referrer` | Raw referrer URL or "(direct)" | First visit only |
| `gd_ls_landing_page` | Full URL of first page visited | First visit only |
| `gd_ls_timestamp` | Date/time of first visit (browser local time) | First visit only |

Cookie duration: **30 days** (configurable via `GD_LS_COOKIE_DAYS` constant).

Cookies are set with `SameSite=Lax` and, on HTTPS sites, the `Secure` flag. `httpOnly` is not set so PHP can read them from `$_COOKIE` on subsequent requests.

### Form Population (Client-Side, JavaScript)

A script loads in the footer on every front-end page (excluding logged-in editors/admins). It:

1. Reads each `gd_ls_*` cookie.
2. Searches the DOM for matching hidden or text inputs using multiple selector strategies (by name, ID, class, wrapper class, and `field_` prefix for Formidable).
3. Populates matching inputs with cookie values.
4. Runs on DOMContentLoaded, after a 1.5-second delay (for AJAX-rendered forms), and via MutationObserver for dynamically added forms.

### Referrer Classification

The plugin classifies referrers into four channels:

| Channel | Medium Value | Examples |
|---------|-------------|----------|
| Search | `organic` | Google, Bing, Yahoo, DuckDuckGo, Ecosia, Baidu, Yandex |
| Social | `social` | Facebook, Instagram, LinkedIn, Twitter/X, Pinterest, TikTok, Reddit, YouTube, Nextdoor, Threads |
| AI | `ai-referral` | ChatGPT, Perplexity, Claude, Gemini, Copilot |
| Other external | `referral` | Any domain not in the lists above (uses bare hostname as source) |
| No referrer | `none` | Direct traffic (source = "direct") |

The domain lists are defined twice — once in PHP (`gd_ls_get_channel_lists()`) and once in JavaScript. If you add a domain to one, add it to the other.

### Shortcodes

Available for use in CF7 email templates, Formidable notification bodies, or page content:

| Shortcode | Output |
|-----------|--------|
| `[gd_ls_source]` | Source value from cookie |
| `[gd_ls_medium]` | Medium value |
| `[gd_ls_campaign]` | Campaign value |
| `[gd_ls_term]` | Term value |
| `[gd_ls_content]` | Content value |
| `[gd_ls_gclid]` | GCLID value |
| `[gd_ls_referrer]` | Referrer URL |
| `[gd_ls_landing_page]` | Landing page URL |
| `[gd_ls_timestamp]` | First visit timestamp |
| `[gd_ls_summary]` | Formatted block with all fields (for email notifications) |

### Form Plugin Setup

**Formidable Forms:**
1. Add a Hidden Field.
2. Set Default Value to the shortcode (e.g., `[gd_ls_source]`).
3. Alternatively, set the field name to `gd_ls_source` and the JS will populate it.
4. Add `[gd_ls_summary]` to your email notification body.

**Contact Form 7:**
1. Add hidden fields with names matching the cookie names (e.g., `[hidden gd_ls_source]`).
2. The plugin processes shortcodes in CF7 form markup.

**Gravity Forms:**
1. Add Hidden Fields with parameter names matching `gd_ls_source`, `gd_ls_medium`, etc.
2. The plugin uses `gform_field_value_` filters to populate them.

## Configuration

The only configurable value is the cookie duration at the top of the file:

```php
define( 'GD_LS_COOKIE_DAYS', 30 );
```

## Referrer List Maintenance

When new search engines, social platforms, or AI tools gain meaningful traffic share, add them to `gd_ls_get_channel_lists()` in PHP **and** to the corresponding JS objects (`SEARCH_ENGINES`, `SOCIAL_PLATFORMS`, `AI_TOOLS`) in `gd_ls_inline_script()`. Both must stay in sync.

## Testing on WP Engine

1. Enable WP Engine's page cache in staging.
2. Visit with UTM params (e.g., `?utm_source=google&utm_medium=cpc&utm_campaign=test`). Verify cookies are set via browser DevTools → Application → Cookies.
3. Visit from Google organic (simulate with browser devtools by setting `document.referrer`). Verify `gd_ls_source=Google` and `gd_ls_medium=organic`.
4. Fill out a form. Verify hidden fields contain the correct values.
5. Verify shortcodes in email notifications pull correct data (requires a form submission so the cookie is available server-side).

## Known Limitations

1. **Cookie-based tracking** means data is lost if the user clears cookies or uses a different browser/device.
2. **Timestamp timezone** is the visitor's browser local time (not site timezone). This differs from the PHP version, which uses `current_time()`.
3. **30-day window** means a visitor who returns after 31 days starts fresh.
4. **No cross-domain tracking.** Cookies are scoped per domain.
5. **First-visit shortcode gap**: PHP shortcodes read `$_COOKIE`, which is populated by JS cookies on subsequent requests. A form submitted on the visitor's very first pageview will have shortcode values empty server-side; JS form field population handles this case client-side.

## Branch Strategy

```
main          ← stable, standard PHP version (for non-cached environments)
└── wp-engine ← permanent parallel branch for WP Engine (JS-based capture)
```

The `wp-engine` branch is a long-lived permanent branch, not a feature branch to merge back. When `main` gets improvements that also apply here (e.g., new referrer domains, security fixes), cherry-pick or merge them into `wp-engine` and keep both domain lists in sync.
