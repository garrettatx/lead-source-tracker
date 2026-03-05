# GD Lead Source Tracker

**Version:** 1.0.0  
**Author:** Garrett Digital  
**Type:** WordPress Must-Use Plugin (mu-plugin)

## What It Does

Captures traffic attribution data (UTM parameters, gclid, referrer, landing page) on a visitor's first pageview and stores it in cookies. When the visitor fills out a form, the plugin populates hidden fields with that attribution data so you know where each lead came from.

Supports Formidable Forms, Contact Form 7, and Gravity Forms out of the box.

## Installation

Upload `gd-lead-source-tracker.php` to `/wp-content/mu-plugins/`. MU-plugins load automatically. No activation step needed.

## How It Works

### Cookie Capture (Server-Side, PHP)

On every front-end page load (`template_redirect`), the plugin runs this logic:

1. **Guard checks** skip admin pages, AJAX, cron, REST API, CLI, 404s, RSS feeds, and logged-in editors/admins.
2. **UTM parameters** in the URL (`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `gclid`) are read and sanitized.
3. **gclid auto-classification** sets source to "Google" and medium to "cpc" when gclid is present but UTMs are missing.
4. **Referrer classification** kicks in when no UTMs are present. The plugin checks the HTTP referrer against known domain lists for search engines, social platforms, and AI tools, then assigns source/medium accordingly.
5. **First-touch vs. last-touch** behavior differs by channel type. Organic/social/referral sources only write cookies if no source cookie exists yet (first-touch). UTM-tagged visits always overwrite (last-touch for paid campaigns).
6. **Referrer, landing page, and timestamp** are captured once and never overwritten.

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
| `gd_ls_timestamp` | Date/time of first visit (site timezone) | First visit only |

Cookie duration: **30 days** (configurable via `GD_LS_COOKIE_DAYS` constant).

Cookies are set with `httpOnly = false` so the client-side JavaScript can read them for form population.

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

To add new domains, edit the arrays in `gd_ls_get_channel_lists()`.

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

When new search engines, social platforms, or AI tools gain meaningful traffic share, add them to `gd_ls_get_channel_lists()`. The format is `'domain.fragment' => 'Display Name'`. Matching uses `strpos` against the referrer hostname, so `'google.'` matches `google.com`, `google.co.uk`, etc.

## Known Limitations

1. **Cookie-based tracking** means data is lost if the user clears cookies or uses a different browser/device.
2. **No server-side form integration for WP Engine** or other hosts with aggressive page caching. The PHP cookie-setting runs on `template_redirect`, which gets bypassed on cached pages. See the WP Engine adaptation notes below.
3. **30-day window** means a visitor who returns after 31 days starts fresh.
4. **No cross-domain tracking.** If you run multiple domains, cookies are scoped per domain.

## WP Engine Considerations

WP Engine's page caching serves static HTML for most visitors, which means the PHP `template_redirect` hook never fires on cached pages. The cookies won't get set server-side for the majority of visits.

**Recommended approach:** Move all cookie capture logic to JavaScript. The JS version would read UTM params from `window.location.search`, read the referrer from `document.referrer`, classify the source client-side, and set cookies via `document.cookie`. The form population logic already works client-side, so that part stays the same.

See the `wp-engine` branch for this adaptation.
