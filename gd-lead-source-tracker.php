<?php
/**
 * Plugin Name: GD Lead Source Tracker
 * Description: Captures UTM parameters, gclid, referrer, and landing page in cookies.
 *              Classifies organic traffic source/medium from the referrer.
 *              Populates hidden form fields in Formidable Forms and Contact Form 7.
 * Version: 1.0.0
 * Author: Garrett Digital
 *
 * INSTALLATION:
 * Upload this file to /wp-content/mu-plugins/gd-lead-source-tracker.php
 * MU-plugins load automatically. No activation step needed.
 *
 * COOKIE PREFIX: gd_ls_
 * COOKIE DURATION: 30 days (configurable below)
 *
 * TRACKED FIELDS:
 *   gd_ls_source        - Traffic source (google, facebook, direct, etc.)
 *   gd_ls_medium        - Traffic medium (organic, cpc, social, referral, etc.)
 *   gd_ls_campaign      - UTM campaign name
 *   gd_ls_term          - UTM term / keyword
 *   gd_ls_content       - UTM content variant
 *   gd_ls_gclid         - Google Ads click identifier
 *   gd_ls_referrer      - Raw referrer URL
 *   gd_ls_landing_page  - First landing page URL of this visit
 *   gd_ls_timestamp     - When the lead source was first captured
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ──────────────────────────────────────────────
// CONFIGURATION
// ──────────────────────────────────────────────

define( 'GD_LS_COOKIE_DAYS', 30 );
define( 'GD_LS_PREFIX', 'gd_ls_' );
define( 'GD_LS_VERSION', '1.0.0' );

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


// ──────────────────────────────────────────────
// REFERRER CLASSIFICATION LISTS
// ──────────────────────────────────────────────

/**
 * Returns arrays of known domain fragments for each channel.
 * Matching is done with strpos against the referrer hostname.
 * Add or remove entries as needed.
 */
function gd_ls_get_channel_lists() {
    return array(
        'search' => array(
            'google.'        => 'Google',
            'bing.'          => 'Bing',
            'yahoo.'         => 'Yahoo',
            'duckduckgo.'    => 'DuckDuckGo',
            'ecosia.'        => 'Ecosia',
            'baidu.'         => 'Baidu',
            'yandex.'        => 'Yandex',
        ),
        'social' => array(
            'facebook.'      => 'Facebook',
            'fb.com'         => 'Facebook',
            'instagram.'     => 'Instagram',
            'linkedin.'      => 'LinkedIn',
            'lnkd.in'        => 'LinkedIn',
            'twitter.'       => 'Twitter',
            'x.com'          => 'Twitter',
            't.co'           => 'Twitter',
            'pinterest.'     => 'Pinterest',
            'tiktok.'        => 'TikTok',
            'reddit.'        => 'Reddit',
            'threads.net'    => 'Threads',
            'youtube.'       => 'YouTube',
            'youtu.be'       => 'YouTube',
            'nextdoor.'      => 'Nextdoor',
        ),
        'ai' => array(
            'chatgpt.com'    => 'ChatGPT',
            'chat.openai.'   => 'ChatGPT',
            'perplexity.ai'  => 'Perplexity',
            'claude.ai'      => 'Claude',
            'gemini.google.' => 'Gemini',
            'copilot.'       => 'Copilot',
        ),
    );
}


// ──────────────────────────────────────────────
// REFERRER CLASSIFICATION LOGIC
// ──────────────────────────────────────────────

/**
 * Given a referrer URL, returns array( 'source' => ..., 'medium' => ... ).
 * Returns null if the referrer is empty, internal, or unclassifiable as direct.
 */
function gd_ls_classify_referrer( $referrer_url ) {
    if ( empty( $referrer_url ) ) {
        return array( 'source' => 'direct', 'medium' => 'none' );
    }

    $parsed = wp_parse_url( $referrer_url );
    if ( empty( $parsed['host'] ) ) {
        return array( 'source' => 'direct', 'medium' => 'none' );
    }

    $host = strtolower( $parsed['host'] );

    // Skip if the referrer is our own domain.
    $site_host = strtolower( wp_parse_url( home_url(), PHP_URL_HOST ) );
    $site_host_bare = preg_replace( '/^www\./', '', $site_host );
    $ref_host_bare  = preg_replace( '/^www\./', '', $host );

    if ( $ref_host_bare === $site_host_bare ) {
        return null; // Internal navigation, don't overwrite.
    }

    $channels = gd_ls_get_channel_lists();

    // Check search engines.
    foreach ( $channels['search'] as $fragment => $label ) {
        if ( strpos( $host, $fragment ) !== false ) {
            return array( 'source' => $label, 'medium' => 'organic' );
        }
    }

    // Check social platforms.
    foreach ( $channels['social'] as $fragment => $label ) {
        if ( strpos( $host, $fragment ) !== false ) {
            return array( 'source' => $label, 'medium' => 'social' );
        }
    }

    // Check AI tools.
    foreach ( $channels['ai'] as $fragment => $label ) {
        if ( strpos( $host, $fragment ) !== false ) {
            return array( 'source' => $label, 'medium' => 'ai-referral' );
        }
    }

    // Anything else is a referral. Use the bare hostname as the source.
    return array( 'source' => $ref_host_bare, 'medium' => 'referral' );
}


// ──────────────────────────────────────────────
// SERVER-SIDE: CAPTURE & SET COOKIES
// ──────────────────────────────────────────────

add_action( 'template_redirect', 'gd_ls_capture', 1 );

function gd_ls_capture() {

    // 1. Original guard: don't run in admin, AJAX, cron, REST API, or CLI.
    if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || defined( 'REST_REQUEST' ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
        return;
    }

    // 2. Ghost-proof guard: Only capture data on real web pages.
    // Ignore calls to files, RSS feeds, or unexpected internal processes.
    if ( ! is_singular() && ! is_front_page() && ! is_archive() && ! is_home() && ! is_search() ) {
        return;
    }

    // 3. Ignore 404 errors (e.g., when the browser looks for an apple-touch-icon.png that doesn't exist)
    if ( is_404() ) {
        return;
    }

    // Don't run for logged-in admins/editors (avoids polluting data)
    if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
        return;
    }

    $cookie_duration = time() + ( DAY_IN_SECONDS * GD_LS_COOKIE_DAYS );
    $cookie_domain   = gd_ls_get_cookie_domain();
    $is_secure       = is_ssl();

    // ── Step 1: Check for UTM parameters and gclid in the URL. ──

    $has_utm   = false;
    $utm_data  = array();
    $param_map = array(
        'source'   => 'utm_source',
        'medium'   => 'utm_medium',
        'campaign' => 'utm_campaign',
        'term'     => 'utm_term',
        'content'  => 'utm_content',
        'gclid'    => 'gclid',
    );

    foreach ( $param_map as $field_key => $query_param ) {
        if ( isset( $_GET[ $query_param ] ) && $_GET[ $query_param ] !== '' ) {
            $utm_data[ $field_key ] = sanitize_text_field( wp_unslash( $_GET[ $query_param ] ) );
            if ( $field_key !== 'gclid' ) {
                $has_utm = true;
            }
        }
    }

    // If gclid is present but no explicit utm_medium, set medium to cpc.
    if ( ! empty( $utm_data['gclid'] ) && empty( $utm_data['medium'] ) ) {
        $utm_data['medium'] = 'cpc';
    }
    if ( ! empty( $utm_data['gclid'] ) && empty( $utm_data['source'] ) ) {
        $utm_data['source'] = 'Google';
    }

    // ── Step 2: If no UTMs, classify from referrer. ──

    if ( ! $has_utm && empty( $utm_data['gclid'] ) ) {
        $referrer   = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
        $classified = gd_ls_classify_referrer( $referrer );

        if ( $classified !== null ) {
            // Only write source/medium if we don't already have a cookie.
            // This preserves the original source across internal page navigations.
            $existing_source = isset( $_COOKIE[ GD_LS_PREFIX . 'source' ] ) ? $_COOKIE[ GD_LS_PREFIX . 'source' ] : '';

            if ( empty( $existing_source ) ) {
                $utm_data['source'] = $classified['source'];
                $utm_data['medium'] = $classified['medium'];
            }
        }
    } else {
        // UTMs present: always overwrite (last-touch attribution for paid campaigns).
        // This means if someone first came from organic and later clicks a Google Ad,
        // the source updates to the ad. This is intentional for paid campaign tracking.
    }

    // ── Step 3: Capture referrer URL (raw, always on first visit). ──

    if ( ! isset( $_COOKIE[ GD_LS_PREFIX . 'referrer' ] ) ) {
        // If there's a referrer we save it; if empty we explicitly save the string "(direct)".
        $referrer = isset( $_SERVER['HTTP_REFERER'] ) && ! empty( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '(direct)';
        $utm_data['referrer'] = $referrer;
    }

    // ── Step 4: Capture landing page (first page visited, set once). ──

    if ( ! isset( $_COOKIE[ GD_LS_PREFIX . 'landing_page' ] ) ) {
        $protocol = is_ssl() ? 'https://' : 'http://';
        $utm_data['landing_page'] = $protocol . sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) . esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
    }

    // ── Step 5: Capture timestamp (set once). ──

    if ( ! isset( $_COOKIE[ GD_LS_PREFIX . 'timestamp' ] ) ) {
        $utm_data['timestamp'] = current_time( 'Y-m-d H:i:s' );
    }

    // ── Step 6: Write cookies for any new data. ──

    foreach ( $utm_data as $key => $value ) {
        if ( $value === '' && isset( $_COOKIE[ GD_LS_PREFIX . $key ] ) ) {
            continue; // Don't overwrite existing cookie with empty value.
        }

        $cookie_name = GD_LS_PREFIX . $key;

        setcookie(
            $cookie_name,
            $value,
            $cookie_duration,
            '/',
            $cookie_domain,
            $is_secure,
            false // httpOnly = false so JS can read it for form population
        );

        // Make it available to PHP in the same request.
        $_COOKIE[ $cookie_name ] = $value;
    }
}


// ──────────────────────────────────────────────
// COOKIE DOMAIN HELPER
// ──────────────────────────────────────────────

function gd_ls_get_cookie_domain() {
    $domain = wp_parse_url( home_url(), PHP_URL_HOST );
    $domain = strtolower( $domain );

    // Strip www so the cookie works on both www and non-www.
    if ( strpos( $domain, 'www.' ) === 0 ) {
        $domain = substr( $domain, 4 );
    }

    // Prefix with dot for subdomain coverage.
    if ( strpos( $domain, '.' ) === 0 ) {
        return $domain;
    }
    return '.' . $domain;
}


// ──────────────────────────────────────────────
// CLIENT-SIDE: JAVASCRIPT TO POPULATE FORM FIELDS
// ──────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', 'gd_ls_enqueue_scripts' );

function gd_ls_enqueue_scripts() {
    // Don't load for logged-in admins.
    if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
        return;
    }

    wp_enqueue_script(
        'gd-lead-source-tracker',
        false, // Inline script, no external file needed.
        array(),
        GD_LS_VERSION,
        true // Load in footer.
    );

    // We'll use wp_add_inline_script instead of an external JS file.
    // This avoids an extra HTTP request and keeps the plugin self-contained.
}

add_action( 'wp_footer', 'gd_ls_inline_script', 99 );

function gd_ls_inline_script() {
    if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
        return;
    }
    ?>
<script id="gd-lead-source-tracker">
(function() {
    'use strict';

    var PREFIX = '<?php echo esc_js( GD_LS_PREFIX ); ?>';
    var COOKIE_DAYS = <?php echo intval( GD_LS_COOKIE_DAYS ); ?>;

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
                'input#field_' + cookieName, // NEW: Captures Formidable Forms ID (e.g., field_gd_ls_source)

                // 2. Class directly on the input
                'input.' + cookieName,

                // 3. Class on a wrapper div
                '.' + cookieName + ' input', 

                // Fallbacks for short names (e.g., "source" instead of "gd_ls_source")
                'input[name="' + field + '"]',
                'input#' + field,
                'input#field_' + field, // NEW: Fallback with field_ prefix
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
                selectors.push('input#field_' + utmMap[field]); // NEW: UTM with field_ prefix
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

})();
</script>
    <?php
}


// ──────────────────────────────────────────────
// WORDPRESS SHORTCODES
// ──────────────────────────────────────────────
// Usage in CF7 email templates, Formidable notification bodies, or page content:
//   [gd_ls_source]
//   [gd_ls_medium]
//   [gd_ls_campaign]
//   [gd_ls_referrer]
//   [gd_ls_landing_page]
//   [gd_ls_timestamp]
//   [gd_ls_summary]  ← formatted block for email notifications

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


// ──────────────────────────────────────────────
// CONTACT FORM 7 INTEGRATION
// ──────────────────────────────────────────────
// Process shortcodes inside CF7 form markup and email templates.

add_filter( 'wpcf7_form_elements', 'gd_ls_process_shortcodes_in_cf7' );

function gd_ls_process_shortcodes_in_cf7( $content ) {
    return do_shortcode( $content );
}


// ──────────────────────────────────────────────
// FORMIDABLE FORMS INTEGRATION
// ──────────────────────────────────────────────
// Formidable hidden fields can use shortcodes as default values.
// The JS above also populates fields by name/id/class as a fallback.
// No additional PHP hook needed for basic hidden field population.
//
// For Formidable email notifications, shortcodes like [gd_ls_summary]
// work in the notification message body because Formidable processes
// WordPress shortcodes in its email content.
//
// SETUP IN FORMIDABLE:
//   1. Add Hidden Field, set Default Value to [gd_ls_source]
//      (or use field name "gd_ls_source" and let JS populate it)
//   2. Repeat for each field you want to capture.
//   3. In the email notification body, add [gd_ls_summary] at the bottom
//      or reference individual fields with their Formidable field IDs.


// ──────────────────────────────────────────────
// OPTIONAL: GRAVITY FORMS INTEGRATION
// ──────────────────────────────────────────────
// If you ever use Gravity Forms, this populates fields via the
// gform_field_value_ filter.

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
