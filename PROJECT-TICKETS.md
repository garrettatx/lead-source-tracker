# GD Lead Source Tracker - Project Tickets

> **Historical (2026-03-05).** The original ticket list. v1.2 closed Tickets 1b, 1c, 1e, 2, 3b
> and 3c; the rest are tracked in `docs/v2-plan.md`, which is the current roadmap.

## GitHub Repository Setup

**Repo name:** `gd-lead-source-tracker`  
**Visibility:** Private  
**Branch strategy:**

```
main              ← stable, production-ready code (standard PHP version)
├── wp-engine     ← long-lived branch for WP Engine JS-based adaptation
└── feature/*     ← short-lived branches for individual improvements
```

The `wp-engine` branch isn't a temporary feature branch. It's a permanent parallel version of the plugin because the two versions serve different hosting environments with fundamentally different behavior. Keep it as a long-lived branch that gets cherry-picked improvements from `main` when applicable.

**Initial repo structure:**

```
gd-lead-source-tracker/
├── README.md                          ← Plugin documentation (the README created alongside this ticket)
├── gd-lead-source-tracker.php         ← The mu-plugin file
├── CHANGELOG.md                       ← Version history
└── .gitignore                         ← Standard WP ignores
```

**Recommended workflow for Ivan and you:**
- Work on `feature/*` branches off `main`, merge via pull request.
- Tag releases (e.g., `v1.0.0`, `v1.1.0`).
- When `main` gets improvements that also apply to WP Engine, cherry-pick or merge them into `wp-engine`.

---

## Ticket 1: Security Hardening (Priority: High)

**Status:** Ready for review  
**Assignee:** Ivan  
**Branch:** `feature/security-hardening`

### Items to address:

**1a. Nonce verification on cookie writes**  
Currently cookies are set purely based on URL params and referrer. This is standard behavior for tracking pixels and analytics tools, so it's not a vulnerability per se. But adding a nonce to the inline JS that writes form data would prevent CSRF-style form injection.

**1b. Cookie value length limits**  
Add `substr()` limits on cookie values before writing. A malicious URL with an extremely long `utm_source` value could write oversized cookies. Cap each field at a reasonable length (e.g., 200 characters for UTM fields, 500 for URLs, 50 for gclid).

```php
$value = substr( $value, 0, $max_lengths[ $key ] ?? 200 );
```

**1c. Validate gclid format**  
Google click IDs follow a known pattern (alphanumeric plus hyphens/underscores). Add a regex check before accepting the value:

```php
if ( $field_key === 'gclid' && ! preg_match( '/^[a-zA-Z0-9_-]{20,100}$/', $utm_data['gclid'] ) ) {
    unset( $utm_data['gclid'] );
}
```

**1d. Content-Security-Policy consideration**  
The inline `<script>` block works fine now, but if a client site adds a strict CSP, it'll break. For v2, consider moving the JS to an external file loaded via `wp_enqueue_script` with a proper handle. Low urgency unless a specific client runs into this.

**1e. Sanitize `$_SERVER['SERVER_NAME']`**  
Line 259 uses `$_SERVER['SERVER_NAME']` for the landing page URL. This value can be spoofed in some server configurations. Replace with `wp_parse_url( home_url(), PHP_URL_HOST )` for a WordPress-controlled value.

---

## Ticket 2: WP Engine Adaptation (Priority: High)

**Status:** To do  
**Assignee:** Ivan / Garrett  
**Branch:** `wp-engine`

### Problem

WP Engine caches pages at the server level. When a cached page is served, PHP hooks like `template_redirect` don't fire. That means the server-side cookie capture (the core of the plugin) never runs for most visitors.

### Recommended approach

Move all cookie capture logic into JavaScript. The form-population JS already works client-side, so you're extending that pattern.

**What changes:**
1. Remove (or gate behind a constant) the `gd_ls_capture()` PHP function and its `template_redirect` hook.
2. Add a JS-based capture function that runs on page load:
   - Parse `window.location.search` for UTM params and gclid.
   - Read `document.referrer` for referrer classification.
   - Port the referrer classification logic (search/social/AI domain lists) to JS.
   - Set cookies via `document.cookie` with the same naming convention.
   - Respect the same first-touch vs. last-touch rules.
3. Keep the shortcode system intact. Shortcodes read from `$_COOKIE`, and cookies set by JS on the previous page load will be available to PHP on subsequent requests. The only gap is the very first pageview where the shortcode and cookie write happen simultaneously. For hidden form fields, the JS population handles this since it reads cookies on the same page.
4. Keep the Gravity Forms `gform_field_value_` filters. These fire on form render, and by that point the cookies from the previous page should exist.

**What stays the same:**
- Cookie names, prefix, and duration.
- Form population JS (already client-side).
- Shortcodes and form plugin integrations.
- The `gd_ls_get_cookie_domain()` helper (still needed for PHP shortcode reads).

**Testing:**
- Enable WP Engine's page cache in staging.
- Visit with UTM params, verify cookies are set.
- Visit from Google organic (use a real referrer or simulate with browser devtools), verify source/medium.
- Fill out a form, verify hidden fields contain the right values.
- Verify shortcodes in email notifications pull correct data.

---

## Ticket 3: Nice-to-Have Improvements (Priority: Medium to Low)

Ordered by impact:

### 3a. Session vs. attribution cookie separation (Medium)

Right now, all cookies share the same 30-day expiration. Consider splitting into two tiers:
- **Attribution cookies** (source, medium, campaign, gclid): 30 days, last-touch for paid, first-touch for organic.
- **Session cookies** (referrer, landing_page, timestamp): Reset per session or per new external referrer.

This would let you track both "what campaign brought them" and "what page they landed on this visit." The current setup captures only the first visit's landing page, which is fine for most use cases but limits multi-visit analysis.

### 3b. Debug mode (Medium)

Add a `?gd_ls_debug=1` parameter (restricted to logged-in admins) that outputs current cookie values in the browser console or as an HTML comment. Makes troubleshooting much faster without needing browser dev tools.

```php
if ( isset( $_GET['gd_ls_debug'] ) && current_user_can( 'manage_options' ) ) {
    // Output cookie values as console.log statements
}
```

### 3c. Cookie consent integration (Medium)

If a client uses a consent management platform (OneTrust, Cookiebot, etc.), the plugin should check for consent before setting cookies. Add a filter hook so you can gate cookie writes:

```php
if ( ! apply_filters( 'gd_ls_should_set_cookies', true ) ) {
    return;
}
```

This lets you hook into whatever consent tool the client uses without modifying the plugin itself.

### 3d. Expand AI referrer list (Low)

AI search and answer tools are changing fast. Set a reminder to review and update `gd_ls_get_channel_lists()['ai']` quarterly. Candidates to watch: Meta AI, Grok, Arc Search, SearchGPT (if it gets its own domain), Brave AI.

### 3e. Admin dashboard widget (Low)

A simple widget showing the last 30 days of lead sources from form submissions. This would require storing submissions server-side (probably a custom post type or a simple database table). Adds complexity, so only build this if clients keep asking for it.

### 3f. Configurable attribution model (Low)

Right now the plugin hard-codes first-touch for organic and last-touch for paid. A settings page or constant-based config to choose between first-touch, last-touch, or always-overwrite would add flexibility. Most clients won't need this, but it's good architecture for the long term.

### 3g. WooCommerce order meta integration (Low)

For e-commerce clients, automatically save lead source data as order meta when a WooCommerce order is placed. This connects attribution directly to revenue.

---

## GitHub Setup Steps

If you want to set this up manually (or have Claude Code do it):

1. Create the repo on GitHub (private, under your account or a Garrett Digital org).
2. Clone locally.
3. Add the files: `gd-lead-source-tracker.php`, `README.md`, `CHANGELOG.md`, `.gitignore`.
4. Commit and push to `main`.
5. Create the `wp-engine` branch from `main`.
6. Tag the current version: `git tag v1.0.0`.

**For Claude Code:** Claude Code can handle the git operations directly (init, commit, push) if you connect it to your GitHub account. It can also create the `wp-engine` branch and scaffold the JS-based version. That's probably the fastest path for the WP Engine adaptation since it involves rewriting the capture logic in JS.

**For this chat (claude.ai):** I can create the files but can't push to GitHub. You'd need to copy them into a local repo or use Claude Code for the git workflow.
