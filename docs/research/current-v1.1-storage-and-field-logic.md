# GD Lead Source Tracker v1.1.0-wpengine: Storage and Field Logic

Extracted verbatim from `gd-lead-source-tracker.php` on the `wp-engine` branch (commit 63a86bf), 2026-09-29, for review. Line numbers are from that file.

## Summary

- **Storage:** nine separate first-party cookies, prefix `gd_ls_`, 30 days, `path=/`, `SameSite=Lax`, `Secure` on HTTPS. Set by JavaScript only (no domain attribute, so host-only). No localStorage, no JSON, no version field.
- **Fields:** `source`, `medium`, `campaign`, `term`, `content`, `gclid`, `referrer`, `landing_page`, `timestamp` (site timezone, `YYYY-MM-DD HH:MM:SS`).
- **Rules:** UTM visits overwrite only the UTM fields present. A gclid with no UTMs sets `Google / cpc`. With no UTMs, the referrer is classified (search, social, AI, else referral, empty = direct / none) and written only if no `source` cookie exists yet. `referrer`, `landing_page` and `timestamp` are written once and never overwritten.
- **Classification:** substring match of the referrer hostname against three lists, checked search, then social, then AI.
- **Form fill:** for each field with a cookie value, sets `.value` on hidden or text inputs matched by name, id, `field_` id, class or wrapper class, using `gd_ls_<field>`, `<field>`, or `utm_<field>`. Runs at DOM ready, after 1.5 s, and on every DOM mutation.
- **Server side:** shortcodes `[gd_ls_<field>]` and `[gd_ls_summary]` read `$_COOKIE`; Gravity `gform_field_value_gd_ls_<field>`; CF7 runs shortcodes in form markup.
- **Skipped for:** logged-in users who can `edit_posts`.

## Configuration and Field List (lines 45-61)

```php
define( 'GD_LS_COOKIE_DAYS', 30 );
define( 'GD_LS_PREFIX', 'gd_ls_' );
define( 'GD_LS_VERSION', '1.1.0-wpengine' );

// Fields we track. The cookie name is GD_LS_PREFIX + key.
// "param" is the URL query parameter that maps to this field (if any).
$gd_ls_fields = array(
    'source'       => array( 'param' => 'utm_source' ),
    'medium'       => array( 'param' => 'utm_medium' ),
    'campaign'     => array( 'param' => 'utm_campaign' ),
    'term'         => array( 'param' => 'utm_term' ),
    'content'      => array( 'param' => 'utm_content' ),
    'gclid'        => array( 'param' => 'gclid' ),
    'referrer'     => array( 'param' => null ),
    'landing_page' => array( 'param' => null ),
    'timestamp'    => array( 'param' => null ),
);
```

## Classification Lists and Logic, JavaScript (lines 263-376)

```js
    // ── Cookie helpers ──

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()\[\]\\\/+^])/g, '\\$1') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : '';
    }

    function setCookie(name, value, days) {
        var d = new Date();
        d.setTime(d.getTime() + (days * 86400000));
        var parts = [
            name + '=' + encodeURIComponent(value),
            'expires=' + d.toUTCString(),
            'path=/',
            'SameSite=Lax'
        ];
        <?php if ( is_ssl() ) : ?>
        parts.push('Secure');
        <?php endif; ?>
        document.cookie = parts.join('; ');
    }

    // ── Referrer classification lists (mirrors PHP gd_ls_get_channel_lists()) ──
    // If you update the PHP lists, update these too.

    var SEARCH_ENGINES = {
        'google.'     : 'Google',
        'bing.'       : 'Bing',
        'yahoo.'      : 'Yahoo',
        'duckduckgo.' : 'DuckDuckGo',
        'ecosia.'     : 'Ecosia',
        'baidu.'      : 'Baidu',
        'yandex.'     : 'Yandex'
    };

    var SOCIAL_PLATFORMS = {
        'facebook.'   : 'Facebook',
        'fb.com'      : 'Facebook',
        'instagram.'  : 'Instagram',
        'linkedin.'   : 'LinkedIn',
        'lnkd.in'     : 'LinkedIn',
        'twitter.'    : 'Twitter',
        'x.com'       : 'Twitter',
        't.co'        : 'Twitter',
        'pinterest.'  : 'Pinterest',
        'tiktok.'     : 'TikTok',
        'reddit.'     : 'Reddit',
        'threads.net' : 'Threads',
        'youtube.'    : 'YouTube',
        'youtu.be'    : 'YouTube',
        'nextdoor.'   : 'Nextdoor'
    };

    var AI_TOOLS = {
        'chatgpt.com'    : 'ChatGPT',
        'chat.openai.'   : 'ChatGPT',
        'perplexity.ai'  : 'Perplexity',
        'claude.ai'      : 'Claude',
        'gemini.google.' : 'Gemini',
        'copilot.'       : 'Copilot'
    };

    // ── Classify a referrer hostname into {source, medium} ──

    function classifyReferrer(referrerUrl) {
        if (!referrerUrl) {
            return { source: 'direct', medium: 'none' };
        }

        var a = document.createElement('a');
        a.href = referrerUrl;
        var host = a.hostname ? a.hostname.toLowerCase() : '';

        if (!host) {
            return { source: 'direct', medium: 'none' };
        }

        // Strip www for site comparison.
        var refHostBare = host.replace(/^www\./, '');
        if (refHostBare === SITE_HOST) {
            return null; // Internal navigation, don't overwrite.
        }

        var fragment, label;

        for (fragment in SEARCH_ENGINES) {
            if (SEARCH_ENGINES.hasOwnProperty(fragment) && host.indexOf(fragment) !== -1) {
                return { source: SEARCH_ENGINES[fragment], medium: 'organic' };
            }
        }

        for (fragment in SOCIAL_PLATFORMS) {
            if (SOCIAL_PLATFORMS.hasOwnProperty(fragment) && host.indexOf(fragment) !== -1) {
                return { source: SOCIAL_PLATFORMS[fragment], medium: 'social' };
            }
        }

        for (fragment in AI_TOOLS) {
            if (AI_TOOLS.hasOwnProperty(fragment) && host.indexOf(fragment) !== -1) {
                return { source: AI_TOOLS[fragment], medium: 'ai-referral' };
            }
        }

        // Anything else: referral using bare hostname as source.
        return { source: refHostBare, medium: 'referral' };
    }

    // ── Parse URL query parameters ──

    function getQueryParam(name) {
        var search = window.location.search;
        var match = search.match(new RegExp('[?&]' + name.replace(/([.*+?^=!:${}()|[\]\/\\])/g, '\\$1') + '=([^&]*)'));
        return match ? decodeURIComponent(match[1].replace(/\+/g, ' ')) : '';
    }
```

## Capture and Storage (lines 378-465)

```js
    // ── Cookie capture (replaces PHP gd_ls_capture() for WP Engine) ──

    function captureLeadSource() {
        // ── Step 1: Check for UTM parameters and gclid in the URL. ──

        var hasUtm  = false;
        var utmData = {};

        var utmSource   = getQueryParam('utm_source');
        var utmMedium   = getQueryParam('utm_medium');
        var utmCampaign = getQueryParam('utm_campaign');
        var utmTerm     = getQueryParam('utm_term');
        var utmContent  = getQueryParam('utm_content');
        var gclid       = getQueryParam('gclid');

        if (utmSource)   { utmData.source   = utmSource;   hasUtm = true; }
        if (utmMedium)   { utmData.medium   = utmMedium;   hasUtm = true; }
        if (utmCampaign) { utmData.campaign = utmCampaign; hasUtm = true; }
        if (utmTerm)     { utmData.term     = utmTerm;     hasUtm = true; }
        if (utmContent)  { utmData.content  = utmContent;  hasUtm = true; }
        if (gclid)       { utmData.gclid    = gclid; }

        // gclid auto-classification: if gclid present but no explicit medium/source.
        if (utmData.gclid && !utmData.medium) { utmData.medium = 'cpc'; }
        if (utmData.gclid && !utmData.source) { utmData.source = 'Google'; }

        // ── Step 2: If no UTMs, classify from referrer. ──

        if (!hasUtm && !utmData.gclid) {
            var referrer   = document.referrer || '';
            var classified = classifyReferrer(referrer);

            if (classified !== null) {
                // Only write source/medium if we don't already have a cookie.
                // This preserves the original source across internal page navigations.
                var existingSource = getCookie(PREFIX + 'source');
                if (!existingSource) {
                    utmData.source = classified.source;
                    utmData.medium = classified.medium;
                }
            }
        }
        // UTMs present: always overwrite (last-touch attribution for paid campaigns).

        // ── Step 3: Capture referrer URL (raw, always on first visit). ──

        if (!getCookie(PREFIX + 'referrer')) {
            utmData.referrer = document.referrer || '(direct)';
        }

        // ── Step 4: Capture landing page (first page visited, set once). ──

        if (!getCookie(PREFIX + 'landing_page')) {
            utmData.landing_page = window.location.href;
        }

        // ── Step 5: Capture timestamp (set once). ──

        if (!getCookie(PREFIX + 'timestamp')) {
            // Use WordPress site timezone (GMT_OFFSET) so the timestamp matches
            // the time shown in WP admin and form notification emails.
            var now      = new Date();
            var sitems   = now.getTime() + (GMT_OFFSET * 3600000);
            var siteTime = new Date(sitems);
            var pad = function(n) { return n < 10 ? '0' + n : n; };
            utmData.timestamp = siteTime.getUTCFullYear() + '-' +
                pad(siteTime.getUTCMonth() + 1) + '-' +
                pad(siteTime.getUTCDate()) + ' ' +
                pad(siteTime.getUTCHours()) + ':' +
                pad(siteTime.getUTCMinutes()) + ':' +
                pad(siteTime.getUTCSeconds());
        }

        // ── Step 6: Write cookies for any new data. ──

        var key;
        for (key in utmData) {
            if (utmData.hasOwnProperty(key) && utmData[key] !== '') {
                // Don't overwrite an existing cookie with an empty value.
                var existing = getCookie(PREFIX + key);
                if (utmData[key] === '' && existing) { continue; }
                setCookie(PREFIX + key, utmData[key], COOKIE_DAYS);
            }
        }
    }

    // Run capture immediately (before DOM ready — cookies don't need the DOM).
    captureLeadSource();
```

## Form Field Population (lines 467-556)

```js
    // ── Field list (matches PHP) ──

    var fields = [
        'source', 'medium', 'campaign', 'term', 'content',
        'gclid', 'referrer', 'landing_page', 'timestamp'
    ];

    // ── Populate hidden form fields ──
    // Looks for inputs by name, id, or class using the cookie name (gd_ls_source, etc.)
    // Also checks for short names (source, utm_source) for flexibility.

    function populateFields() {
        fields.forEach(function(field) {
            var cookieName = PREFIX + field;
            var val = getCookie(cookieName);

            if (!val) return;

            // Selectors to try, in order of specificity.
            var selectors = [
                // 1. Exact name or ID on the input
                'input[name="' + cookieName + '"]',
                'input#' + cookieName,
                'input#field_' + cookieName, // Captures Formidable Forms ID (e.g., field_gd_ls_source)

                // 2. Class directly on the input
                'input.' + cookieName,

                // 3. Class on a wrapper div
                '.' + cookieName + ' input',

                // Fallbacks for short names (e.g., "source" instead of "gd_ls_source")
                'input[name="' + field + '"]',
                'input#' + field,
                'input#field_' + field, // Fallback with field_ prefix
                'input.' + field,
                '.' + field + ' input', // Wrapper fallback
            ];

            // Also try utm_ prefixed names for source/medium/campaign/term/content.
            var utmMap = {
                source: 'utm_source',
                medium: 'utm_medium',
                campaign: 'utm_campaign',
                term: 'utm_term',
                content: 'utm_content'
            };

            if (utmMap[field]) {
                selectors.push('input[name="' + utmMap[field] + '"]');
                selectors.push('input#' + utmMap[field]);
                selectors.push('input#field_' + utmMap[field]); // UTM with field_ prefix
                selectors.push('input.' + utmMap[field]);
                selectors.push('.' + utmMap[field] + ' input'); // Wrapper fallback
            }

            for (var i = 0; i < selectors.length; i++) {
                var els = document.querySelectorAll(selectors[i]);
                for (var j = 0; j < els.length; j++) {
                    if (els[j].type === 'hidden' || els[j].type === 'text') {
                        els[j].value = val;
                    }
                }
            }
        });
    }

    // Run on DOM ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', populateFields);
    } else {
        populateFields();
    }

    // Also run after a short delay to catch dynamically rendered forms
    // (Formidable sometimes renders forms via AJAX).
    setTimeout(populateFields, 1500);

    // Watch for new forms added to the DOM (covers AJAX-loaded forms).
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].addedNodes.length > 0) {
                    populateFields();
                    break;
                }
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }
```

## Server-Side Reads (lines 576-618, 664-681)

```php
add_action( 'init', 'gd_ls_register_shortcodes' );

function gd_ls_register_shortcodes() {
    $fields = array( 'source', 'medium', 'campaign', 'term', 'content', 'gclid', 'referrer', 'landing_page', 'timestamp' );

    foreach ( $fields as $field ) {
        add_shortcode( GD_LS_PREFIX . $field, function() use ( $field ) {
            $cookie_name = GD_LS_PREFIX . $field;
            return isset( $_COOKIE[ $cookie_name ] ) ? sanitize_text_field( $_COOKIE[ $cookie_name ] ) : '';
        });
    }

    // Summary shortcode for email notifications.
    add_shortcode( 'gd_ls_summary', 'gd_ls_summary_shortcode' );
}

function gd_ls_summary_shortcode() {
    $source       = isset( $_COOKIE[ GD_LS_PREFIX . 'source' ] )       ? sanitize_text_field( $_COOKIE[ GD_LS_PREFIX . 'source' ] )       : '(not set)';
    $medium       = isset( $_COOKIE[ GD_LS_PREFIX . 'medium' ] )       ? sanitize_text_field( $_COOKIE[ GD_LS_PREFIX . 'medium' ] )       : '(not set)';
    $campaign     = isset( $_COOKIE[ GD_LS_PREFIX . 'campaign' ] )     ? sanitize_text_field( $_COOKIE[ GD_LS_PREFIX . 'campaign' ] )     : '';
    $gclid        = isset( $_COOKIE[ GD_LS_PREFIX . 'gclid' ] )       ? sanitize_text_field( $_COOKIE[ GD_LS_PREFIX . 'gclid' ] )       : '';
    $referrer     = isset( $_COOKIE[ GD_LS_PREFIX . 'referrer' ] )     ? sanitize_text_field( $_COOKIE[ GD_LS_PREFIX . 'referrer' ] )     : '(direct)';
    $landing_page = isset( $_COOKIE[ GD_LS_PREFIX . 'landing_page' ] ) ? sanitize_text_field( $_COOKIE[ GD_LS_PREFIX . 'landing_page' ] ) : '';
    $timestamp    = isset( $_COOKIE[ GD_LS_PREFIX . 'timestamp' ] )    ? sanitize_text_field( $_COOKIE[ GD_LS_PREFIX . 'timestamp' ] )    : '';

    $lines = array();
    $lines[] = '--- Lead Source ---';
    $lines[] = 'Source: ' . $source;
    $lines[] = 'Medium: ' . $medium;

    if ( $campaign ) {
        $lines[] = 'Campaign: ' . $campaign;
    }
    if ( $gclid ) {
        $lines[] = 'GCLID: ' . $gclid;
    }

    $lines[] = 'Referrer: ' . ( $referrer ? $referrer : '(direct)' );
    $lines[] = 'Landing Page: ' . $landing_page;
    $lines[] = 'First Visit: ' . $timestamp;

    return implode( "\n", $lines );
}

add_action( 'init', 'gd_ls_gravity_forms_integration' );

function gd_ls_gravity_forms_integration() {
    if ( ! class_exists( 'GFForms' ) ) {
        return;
    }

    $fields = array( 'source', 'medium', 'campaign', 'term', 'content', 'gclid', 'referrer', 'landing_page', 'timestamp' );

    foreach ( $fields as $field ) {
        $cookie_name = GD_LS_PREFIX . $field;
        $param_name  = GD_LS_PREFIX . $field;

        add_filter( 'gform_field_value_' . $param_name, function() use ( $cookie_name ) {
            return isset( $_COOKIE[ $cookie_name ] ) ? sanitize_text_field( $_COOKIE[ $cookie_name ] ) : '';
        });
    }
}
```
