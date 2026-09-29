<?php
/**
 * Plugin Name: GD Lead Source Tracker
 * Description: Records where each visitor came from (UTMs, ad click IDs, referrer, landing page) in
 *              first-party cookies and fills hidden form fields with it. Works on cached hosts.
 * Version:     1.2.0
 * Author:      Garrett Digital
 * Requires PHP: 7.4
 *
 * One core for every host and every site. Install as an mu-plugin (/wp-content/mu-plugins/) or a
 * regular plugin. Site-specific settings, including field maps for embedded CRM forms, come from the
 * gd_ls_config filter in a separate per-site file, so updating this file never touches a site's
 * setup. Docs: docs/setup/ in the plugin repo.
 *
 * Replacing v1.1: overwrite the old file with this one (same filename). Never run both.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// A second copy loaded (for example mu-plugin and regular plugin at once) must not redeclare
// functions, which would take the whole site down.
if ( defined( 'GD_LS_VERSION' ) ) {
	return;
}

define( 'GD_LS_VERSION', '1.2.0' );
define( 'GD_LS_PREFIX', 'gd_ls_' );

// v1.1 constant, kept so existing sites that set it still work. Now means the last-touch window.
if ( ! defined( 'GD_LS_COOKIE_DAYS' ) ) {
	define( 'GD_LS_COOKIE_DAYS', 30 );
}


// ──────────────────────────────────────────────
// FIELDS
// ──────────────────────────────────────────────

/**
 * Stored fields, by record. Cookie name is GD_LS_PREFIX + key.
 *
 * touch: the last non-direct touch, rewritten as a whole on every new touch.
 * first: one summary line for the first touch, written once.
 * click: ad click IDs, kept for their own window whatever later visits do.
 */
function gd_ls_field_groups() {
	return array(
		'touch' => array( 'source', 'medium', 'campaign', 'term', 'content', 'channel', 'referrer', 'landing_page', 'timestamp' ),
		'first' => array( 'first_touch' ),
		'click' => array( 'gclid', 'gbraid', 'wbraid', 'msclkid', 'click_time' ),
	);
}

function gd_ls_stored_keys() {
	$groups = gd_ls_field_groups();
	return array_merge( $groups['touch'], $groups['first'], $groups['click'] );
}

/** Keys a form can ask for: stored keys plus two computed ones. */
function gd_ls_mappable_keys() {
	return array_merge( gd_ls_stored_keys(), array( 'source_medium', 'form_page' ) );
}


// ──────────────────────────────────────────────
// CONFIG
// ──────────────────────────────────────────────

/**
 * Defaults. A site changes any of these with the gd_ls_config filter; see docs/setup/configuration.md.
 *
 * Domain lists map a host pattern to a source name. "name.*" matches any TLD (google.com,
 * google.co.uk). Other patterns match the host and its subdomains. Lists are checked in this order:
 * referral overrides, ai, email, search, social, marketplaces.
 */
function gd_ls_default_config() {
	return array(
		'last_touch_days'  => (int) GD_LS_COOKIE_DAYS,
		'first_touch_days' => 90,
		'click_id_days'    => 90,
		'gbp_campaign'     => '',
		'marketplaces'     => array(),
		'own_hosts'        => array(),
		'exclude_hosts'    => array( 'accounts.google.com', 'paypal.com', 'checkout.stripe.com' ),
		'field_maps'       => array(),
		'server_refresh'   => true,
		// auto: respect the WP Consent API if a consent plugin provides it, otherwise store.
		// event: wait for the site's consent tool (see docs/setup/consent.md). off: always store.
		'consent_mode'     => 'auto',
		'consent_category' => 'marketing',
		'delete_on_deny'   => true,
		'lists'            => array(
			'referral' => array(
				'docs.google.com'     => '',
				'drive.google.com'    => '',
				'sites.google.com'    => '',
				'groups.google.com'   => '',
				'calendar.google.com' => '',
				'translate.google.com' => '',
			),
			'ai'       => array(
				'chatgpt.com'             => 'chatgpt',
				'chat.openai.com'         => 'chatgpt',
				'perplexity.ai'           => 'perplexity',
				'claude.ai'               => 'claude',
				'gemini.google.com'       => 'gemini',
				'bard.google.com'         => 'gemini',
				'copilot.microsoft.com'   => 'copilot',
				'copilot.cloud.microsoft' => 'copilot',
				'meta.ai'                 => 'meta-ai',
				'grok.com'                => 'grok',
				'chat.deepseek.com'       => 'deepseek',
				'chat.mistral.ai'         => 'mistral',
				'you.com'                 => 'you',
				'phind.com'               => 'phind',
				'poe.com'                 => 'poe',
			),
			'email'    => array(
				'mail.google.com'       => 'gmail',
				'com.google.android.gm' => 'gmail',
				'outlook.live.com'      => 'outlook',
				'outlook.office.com'    => 'outlook',
				'outlook.office365.com' => 'outlook',
				'mail.yahoo.com'        => 'yahoo-mail',
				'mail.aol.com'          => 'aol-mail',
			),
			'search'   => array(
				'google.*'                                => 'google',
				'com.google.android.googlequicksearchbox' => 'google',
				'bing.com'                                => 'bing',
				'yahoo.*'                                 => 'yahoo',
				'duckduckgo.com'                          => 'duckduckgo',
				'ecosia.org'                              => 'ecosia',
				'search.brave.com'                        => 'brave',
				'startpage.com'                           => 'startpage',
				'qwant.com'                               => 'qwant',
				'kagi.com'                                => 'kagi',
				'yandex.*'                                => 'yandex',
				'baidu.com'                               => 'baidu',
				'naver.com'                               => 'naver',
				'seznam.cz'                               => 'seznam',
				'aol.com'                                 => 'aol',
				'ask.com'                                 => 'ask',
			),
			'social'   => array(
				'facebook.com'  => 'facebook',
				'fb.com'        => 'facebook',
				'fb.me'         => 'facebook',
				'instagram.com' => 'instagram',
				'linkedin.com'  => 'linkedin',
				'lnkd.in'       => 'linkedin',
				'x.com'         => 'twitter',
				'twitter.com'   => 'twitter',
				't.co'          => 'twitter',
				'pinterest.*'   => 'pinterest',
				'pin.it'        => 'pinterest',
				'tiktok.com'    => 'tiktok',
				'reddit.com'    => 'reddit',
				'threads.net'   => 'threads',
				'threads.com'   => 'threads',
				'youtube.com'   => 'youtube',
				'youtu.be'      => 'youtube',
				'nextdoor.com'  => 'nextdoor',
				'snapchat.com'  => 'snapchat',
				'bsky.app'      => 'bluesky',
			),
		),
		// utm_source values people type that mean a known source.
		'source_aliases'   => array(
			'fb'         => 'facebook',
			'ig'         => 'instagram',
			'insta'      => 'instagram',
			'tw'         => 'twitter',
			'li'         => 'linkedin',
			'yt'         => 'youtube',
			'gbp'        => 'google',
			'gmb'        => 'google',
			'adwords'    => 'google',
			'googleads'  => 'google',
			'google-ads' => 'google',
			'msn'        => 'bing',
			'microsoft'  => 'bing',
			'openai'     => 'chatgpt',
		),
	);
}

/** The site's config: defaults, then the gd_ls_config filter, then validation. */
function gd_ls_config() {
	static $config = null;
	if ( null !== $config ) {
		return $config;
	}

	$defaults = gd_ls_default_config();
	$filtered = apply_filters( 'gd_ls_config', $defaults );
	if ( ! is_array( $filtered ) ) {
		$filtered = $defaults;
	}
	$config = array_merge( $defaults, $filtered );

	foreach ( array( 'last_touch_days', 'first_touch_days', 'click_id_days' ) as $key ) {
		$config[ $key ] = max( 1, min( 400, (int) $config[ $key ] ) );
	}

	$config['gbp_campaign']     = strtolower( trim( (string) $config['gbp_campaign'] ) );
	$config['server_refresh'] = (bool) $config['server_refresh'];
	$config['delete_on_deny'] = (bool) $config['delete_on_deny'];

	// v1.2 early drafts used wait_for_consent; it maps to event mode.
	if ( ! empty( $filtered['wait_for_consent'] ) && empty( $filtered['consent_mode'] ) ) {
		$config['consent_mode'] = 'event';
	}
	if ( ! in_array( $config['consent_mode'], array( 'auto', 'event', 'wp_consent_api', 'off' ), true ) ) {
		$config['consent_mode'] = 'auto';
	}
	$config['consent_category'] = preg_replace( '/[^a-z_-]/', '', strtolower( (string) $config['consent_category'] ) ) ?: 'marketing';

	foreach ( array( 'marketplaces', 'own_hosts', 'exclude_hosts' ) as $key ) {
		$config[ $key ] = array_values( array_filter( array_map( 'gd_ls_clean_host', (array) $config[ $key ] ) ) );
	}

	$lists = array();
	foreach ( (array) $config['lists'] as $list => $entries ) {
		$lists[ $list ] = array();
		foreach ( (array) $entries as $pattern => $label ) {
			$pattern = gd_ls_clean_host( $pattern );
			if ( $pattern ) {
				$lists[ $list ][ $pattern ] = strtolower( (string) $label );
			}
		}
	}
	$config['lists'] = $lists;

	$aliases = array();
	foreach ( (array) $config['source_aliases'] as $from => $to ) {
		$aliases[ strtolower( trim( (string) $from ) ) ] = strtolower( trim( (string) $to ) );
	}
	$config['source_aliases'] = $aliases;

	$config['field_maps'] = gd_ls_clean_field_maps( $config['field_maps'] );

	return $config;
}

function gd_ls_clean_host( $host ) {
	$host = strtolower( trim( (string) $host ) );
	$host = preg_replace( '#^[a-z]+://#', '', $host );
	$host = preg_replace( '#[/:].*$#', '', $host );
	$host = preg_replace( '/^www\./', '', $host );
	return preg_match( '/^[a-z0-9.*-]+$/', $host ) ? $host : '';
}

/**
 * Field maps for forms that name fields their own way (embedded CRM forms, custom HTML).
 * Each map: array( 'label' => '', 'selector' => 'form[...]', 'fields' => array( key => input name ) ).
 * Keys must be mappable keys. A name containing REPLACE_ is kept here and skipped in the browser.
 */
function gd_ls_clean_field_maps( $maps ) {
	$clean   = array();
	$allowed = gd_ls_mappable_keys();

	foreach ( (array) $maps as $map ) {
		if ( ! is_array( $map ) || empty( $map['selector'] ) || empty( $map['fields'] ) ) {
			continue;
		}
		$fields = array();
		foreach ( (array) $map['fields'] as $key => $name ) {
			$name = trim( (string) $name );
			if ( in_array( $key, $allowed, true ) && '' !== $name && strlen( $name ) <= 200 ) {
				$fields[ $key ] = $name;
			}
		}
		if ( $fields ) {
			$clean[] = array(
				'label'    => isset( $map['label'] ) ? substr( (string) $map['label'], 0, 100 ) : '',
				'selector' => substr( (string) $map['selector'], 0, 300 ),
				'fields'   => $fields,
			);
		}
	}
	return $clean;
}

/** The part of the config the browser needs. */
function gd_ls_public_config() {
	$config = gd_ls_config();

	$own = $config['own_hosts'];
	foreach ( array( home_url(), site_url() ) as $url ) {
		$host = gd_ls_clean_host( wp_parse_url( $url, PHP_URL_HOST ) );
		if ( $host ) {
			$own[] = $host;
		}
	}

	$marketplaces = array();
	foreach ( $config['marketplaces'] as $host ) {
		$parts                   = explode( '.', $host );
		$marketplaces[ $host ]   = $parts[0];
	}
	$lists                = $config['lists'];
	$lists['marketplace'] = $marketplaces;

	return array(
		'version'          => GD_LS_VERSION,
		'prefix'           => GD_LS_PREFIX,
		'last_touch_days'  => $config['last_touch_days'],
		'first_touch_days' => $config['first_touch_days'],
		'click_id_days'    => $config['click_id_days'],
		'gbp_campaign'     => $config['gbp_campaign'],
		'own_hosts'        => array_values( array_unique( $own ) ),
		'exclude_hosts'    => $config['exclude_hosts'],
		'lists'            => $lists,
		'source_aliases'   => $config['source_aliases'],
		'field_maps'       => $config['field_maps'],
		'refresh_url'      => $config['server_refresh'] ? rest_url( 'gd-ls/v1/refresh' ) : '',
		'consent_mode'     => gd_ls_consent_mode(),
		'consent_category' => $config['consent_category'],
		'delete_on_deny'   => $config['delete_on_deny'],
		'gmt_offset'       => (float) get_option( 'gmt_offset' ),
	);
}


/** The consent mode the browser uses, with auto resolved for this site. */
function gd_ls_consent_mode() {
	$mode = gd_ls_config()['consent_mode'];
	if ( 'auto' === $mode ) {
		return function_exists( 'wp_has_consent' ) ? 'wp_consent_api' : 'off';
	}
	return $mode;
}

// Tells the WP Consent API this plugin asks for consent before storing, so consent plugins
// don't flag it as non-compliant.
add_filter( 'wp_consent_api_registered_' . plugin_basename( __FILE__ ), '__return_true' );


// ──────────────────────────────────────────────
// BROWSER SCRIPT
// ──────────────────────────────────────────────

/**
 * Capture runs in the browser on every page, because full-page caching (WP Engine, Cloudflare,
 * LiteSpeed, WP Rocket and others) serves stored HTML without running PHP.
 */
function gd_ls_should_output() {
	if ( is_admin() ) {
		return false;
	}
	// Editors are skipped so their visits don't pollute data, unless they ask for debug output.
	if ( is_user_logged_in() && current_user_can( 'edit_posts' ) && ! isset( $_GET['gd_ls_debug'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return false;
	}
	// Consent gate. Return false from this filter to stop all storage on the page.
	return (bool) apply_filters( 'gd_ls_can_store', true );
}

add_action( 'wp_footer', 'gd_ls_footer', 99 );

function gd_ls_footer() {
	if ( ! gd_ls_should_output() ) {
		return;
	}
	$json = wp_json_encode( gd_ls_public_config(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
	echo "<script id=\"gd-lead-source-tracker-config\">window.GD_LS_CONFIG=" . $json . ";</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	echo "<script id=\"gd-lead-source-tracker\">\n" . gd_ls_engine_js() . "\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * The browser engine. Plain JavaScript with no PHP inside, so tests can run it directly
 * (tests/engine.test.js extracts it from this function).
 */
function gd_ls_engine_js() {
	return <<<'JS'
/* GD_LS_ENGINE_START */
(function (w, d) {
	'use strict';

	var C = w.GD_LS_CONFIG;
	if (!C || w.gdLeadSource) { return; }

	var P = C.prefix || 'gd_ls_';
	var DAY = 86400000;
	var TOUCH = ['source', 'medium', 'campaign', 'term', 'content', 'channel', 'referrer', 'landing_page', 'timestamp'];
	var CLICK = ['gclid', 'gbraid', 'wbraid', 'msclkid'];
	var GOOGLE_CLICK = ['gclid', 'gbraid', 'wbraid'];
	var STORED = TOUCH.concat(['first_touch'], CLICK, ['click_time']);
	var LEGACY = ['source', 'medium', 'campaign', 'term', 'content', 'gclid', 'referrer', 'landing_page', 'timestamp'];
	var UTM_KEYS = ['source', 'medium', 'campaign', 'term', 'content'];
	var CLICK_RE = /^[A-Za-z0-9_\-]{8,200}$/;
	var DEBUG = /[?&]gd_ls_debug=1(&|$)/.test(w.location.search);
	var LISTS = C.lists || {};
	var ORDER = ['ai', 'email', 'search', 'social', 'marketplace'];
	var MEDIUM_FOR = { ai: 'ai-assistant', email: 'email', search: 'organic', social: 'social', marketplace: 'referral' };

	var state = { reason: '', touch: null, click: null, consent: 'unknown' };
	var granted = false;
	var written = {};

	// Source name -> list it belongs to, so a utm_source like "chatgpt" is known to be AI.
	var KIND = {};
	ORDER.forEach(function (list) {
		var entries = LISTS[list] || {};
		Object.keys(entries).forEach(function (pattern) {
			if (entries[pattern] && !KIND[entries[pattern]]) { KIND[entries[pattern]] = list; }
		});
	});

	// ── Values ──

	function clean(v, max) {
		if (v === null || v === undefined) { return ''; }
		v = String(v).replace(/[\u0000-\u001F\u007F<>"]/g, ' ').replace(/\s+/g, ' ').trim();
		return v.length > max ? v.slice(0, max) : v;
	}

	function parseUrl(url) {
		try { return new URL(url, w.location.href); } catch (e) { return null; }
	}

	function hostOf(url) {
		var u = url ? parseUrl(url) : null;
		return u && u.hostname ? u.hostname.toLowerCase().replace(/\.$/, '') : '';
	}

	function bare(host) { return host.replace(/^www\./, ''); }

	function escapeRe(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

	// "google.*" matches google.com and google.co.uk; "t.co" matches t.co and its subdomains only.
	function hostMatch(host, pattern) {
		if (pattern.slice(-2) === '.*') {
			var base = pattern.slice(0, -2);
			return new RegExp('(^|\\.)' + escapeRe(base) + '\\.[a-z]{2,6}(\\.[a-z]{2,3})?$').test(host);
		}
		return host === pattern || host.slice(-(pattern.length + 1)) === '.' + pattern;
	}

	function inList(host, list) {
		var entries = LISTS[list] || {};
		var patterns = Object.keys(entries);
		for (var i = 0; i < patterns.length; i++) {
			if (hostMatch(host, patterns[i])) { return { label: entries[patterns[i]] || bare(host) }; }
		}
		return null;
	}

	function anyMatch(host, patterns) {
		for (var i = 0; i < (patterns || []).length; i++) {
			if (hostMatch(host, patterns[i])) { return true; }
		}
		return false;
	}

	function isOwn(host) { return anyMatch(host, C.own_hosts); }

	function classifyHost(host) {
		host = (host || '').toLowerCase();
		if (!host) { return null; }
		if (inList(host, 'referral')) { return { source: bare(host), medium: 'referral', kind: 'referral' }; }
		for (var i = 0; i < ORDER.length; i++) {
			var hit = inList(host, ORDER[i]);
			if (hit) { return { source: hit.label, medium: MEDIUM_FOR[ORDER[i]], kind: ORDER[i] }; }
		}
		return { source: bare(host), medium: 'referral', kind: 'referral' };
	}

	function normSource(s) {
		s = clean(s, 200).toLowerCase();
		if (!s) { return ''; }
		if (C.source_aliases && C.source_aliases[s]) { return C.source_aliases[s]; }
		var host = s.replace(/^[a-z]+:\/\//, '').split(/[\/?#]/)[0];
		if (host.indexOf('.') > 0 && /^[a-z0-9.-]+$/.test(host)) { return classifyHost(host).source; }
		return s;
	}

	function kindOf(source) { return KIND[source] || ''; }

	function channelFor(t, clickType) {
		var s = t.source || '', m = (t.medium || '').toLowerCase(), k = kindOf(s);
		var camp = (t.campaign || '').toLowerCase();
		var paidSearch = /^(cpc|ppc|paid[-_ ]?search|sem)$/.test(m);
		var paidAny = paidSearch || /^(cpm|cpv|paid|display|banner|paid[-_ ]?social)$/.test(m);

		if (s === 'direct' && m === 'none') { return 'Direct'; }
		if (paidAny && (k === 'social' || /social/.test(m))) { return 'Paid Social'; }
		if (clickType || paidSearch) { return 'Paid Search'; }
		if (s === 'google' && ((C.gbp_campaign && camp === C.gbp_campaign) || m === 'local' || m === 'gbp')) { return 'Google Business Profile'; }
		if (/^(display|banner|cpm|cpv)$/.test(m)) { return 'Display'; }
		if (m === 'paid') { return 'Paid Other'; }
		if (k === 'ai' || /^ai[-_ ]?(assistant|referral)$/.test(m)) { return 'AI Assistant'; }
		if (k === 'email' || /^(e[-_]?mail|newsletter)$/.test(m)) { return 'Email'; }
		if (/^(sms|text)$/.test(m)) { return 'SMS'; }
		if (k === 'marketplace') { return 'Marketplace'; }
		if (m === 'organic' || k === 'search') { return 'Organic Search'; }
		if (k === 'social' || /^(social|social[-_ ]?network|social[-_ ]?media|organic[-_ ]?social|sm)$/.test(m)) { return 'Organic Social'; }
		if (m === 'referral' || m === '(not set)' || m === '') { return 'Referral'; }
		return 'Other';
	}

	function pad(n) { return n < 10 ? '0' + n : String(n); }

	// Site time zone, so values match WordPress admin and form emails.
	function siteTime() {
		var t = new Date(Date.now() + (Number(C.gmt_offset) || 0) * 3600000);
		return t.getUTCFullYear() + '-' + pad(t.getUTCMonth() + 1) + '-' + pad(t.getUTCDate()) + ' ' +
			pad(t.getUTCHours()) + ':' + pad(t.getUTCMinutes()) + ':' + pad(t.getUTCSeconds());
	}

	// Landing page keeps only UTM parameters, so personal data in a URL is never stored.
	function landingPage() {
		var u = parseUrl(w.location.href);
		if (!u) { return ''; }
		var keep = [];
		u.searchParams.forEach(function (v, k) {
			if (/^utm_[a-z]+$/.test(k)) { keep.push(encodeURIComponent(k) + '=' + encodeURIComponent(v)); }
		});
		return clean(u.origin + u.pathname + (keep.length ? '?' + keep.join('&') : ''), 500);
	}

	function pageUrl() {
		var u = parseUrl(w.location.href);
		return u ? clean(u.origin + u.pathname, 500) : '';
	}

	// Referrer keeps origin and path; its query string is dropped.
	function referrerValue(ref) {
		var u = ref ? parseUrl(ref) : null;
		return u && u.hostname ? clean(u.origin === 'null' ? u.protocol + '//' + u.hostname + u.pathname : u.origin + u.pathname, 500) : '';
	}

	// ── Cookies ──

	function getCookie(name) {
		var parts = d.cookie ? d.cookie.split(/;\s*/) : [];
		for (var i = 0; i < parts.length; i++) {
			var eq = parts[i].indexOf('=');
			if (eq > 0 && parts[i].slice(0, eq) === name) {
				try { return decodeURIComponent(parts[i].slice(eq + 1)); } catch (e) { return ''; }
			}
		}
		return '';
	}

	function get(key) { return getCookie(P + key); }

	function setCookie(key, value, days) {
		var expires = new Date(Date.now() + days * DAY);
		var c = P + key + '=' + encodeURIComponent(value) + '; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax';
		if (w.location.protocol === 'https:') { c += '; Secure'; }
		d.cookie = c;
		written[P + key] = Math.floor(expires.getTime() / 1000);
	}

	function delCookie(key) {
		d.cookie = P + key + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Lax';
		delete written[P + key];
	}

	// ── Capture ──

	function readParams() {
		var out = {}, u = parseUrl(w.location.href);
		if (!u) { return out; }
		UTM_KEYS.forEach(function (k) { out['utm_' + k] = clean(u.searchParams.get('utm_' + k), 200); });
		CLICK.forEach(function (k) { out[k] = u.searchParams.get(k) || ''; });
		return out;
	}

	function readClick(p) {
		for (var i = 0; i < CLICK.length; i++) {
			if (p[CLICK[i]] && CLICK_RE.test(p[CLICK[i]])) { return { type: CLICK[i], value: p[CLICK[i]] }; }
		}
		return null;
	}

	function newTouch(source, medium, p, ref, clickType) {
		var t = {
			source: source,
			medium: medium,
			campaign: p ? p.utm_campaign : '',
			term: p ? p.utm_term : '',
			content: p ? p.utm_content : '',
			referrer: ref ? referrerValue(ref) : '(direct)',
			landing_page: landingPage(),
			timestamp: siteTime()
		};
		t.channel = channelFor(t, clickType);
		return t;
	}

	function fromParams(p, click, ref) {
		var source = normSource(p.utm_source);
		var medium = clean(p.utm_medium, 200).toLowerCase();
		if (!source && click) { source = click.type === 'msclkid' ? 'bing' : 'google'; }
		if (!medium) {
			var kind = kindOf(source);
			medium = click ? 'cpc' : (MEDIUM_FOR[kind] || (source ? '(not set)' : ''));
		}
		return newTouch(source || '(not set)', medium || '(not set)', p, ref, click ? click.type : '');
	}

	function fromReferrer(ref, host) {
		var c = classifyHost(host);
		return newTouch(c.source, c.medium, null, ref, '');
	}

	function firstLine(t) {
		var u = parseUrl(t.landing_page);
		var path = u ? u.pathname : '';
		return clean([t.channel, t.source + ' / ' + t.medium, path, t.timestamp.slice(0, 10)].join(' · '), 300);
	}

	function writeTouch(t) {
		TOUCH.forEach(function (k) {
			if (t[k]) { setCookie(k, t[k], C.last_touch_days); } else { delCookie(k); }
		});
		if (!get('first_touch')) { setCookie('first_touch', firstLine(t), C.first_touch_days); }
	}

	function writeClick(click) {
		// A new Google click replaces any earlier Google click ID; Microsoft's is kept separately.
		if (GOOGLE_CLICK.indexOf(click.type) !== -1) {
			GOOGLE_CLICK.forEach(function (k) { if (k !== click.type) { delCookie(k); } });
		}
		setCookie(click.type, click.value, C.click_id_days);
		setCookie('click_time', siteTime(), C.click_id_days);
	}

	/**
	 * Attribution: last non-direct touch, plus the first touch.
	 * Tagged or referred arrivals are new touches and replace the whole last-touch record.
	 * A direct arrival only records direct / none when there is no live record.
	 */
	function capture() {
		var ref = d.referrer || '';
		var host = hostOf(ref);
		var p = readParams();
		var click = readClick(p);
		var tagged = !!(p.utm_source || p.utm_medium || p.utm_campaign || p.utm_term || p.utm_content);
		var touch = null;

		if (host && isOwn(host)) {
			state.reason = 'internal';                      // UTMs on internal links are ignored.
			click = null;
		} else if (tagged || click) {
			touch = fromParams(p, click, ref);
			state.reason = 'tagged';
		} else if (host && anyMatch(host, C.exclude_hosts)) {
			state.reason = 'excluded';
		} else if (host) {
			touch = fromReferrer(ref, host);
			state.reason = 'referrer';
		} else if (!get('source')) {
			touch = newTouch('direct', 'none', null, '', '');
			state.reason = 'direct-first';
		} else {
			state.reason = 'direct-kept';
		}

		state.touch = touch;
		state.click = click;
	}

	// Stores what capture() worked out. Runs only once consent allows it.
	function persist() {
		if (state.touch) { writeTouch(state.touch); }
		if (state.click) { writeClick(state.click); }
		if (state.touch || state.click) { refresh(); }
	}

	// Re-sends the cookies from the server, which Safari keeps longer than script-set cookies.
	function refresh() {
		if (!C.refresh_url || typeof w.fetch !== 'function' || !Object.keys(written).length) { return; }
		try {
			w.fetch(C.refresh_url, {
				method: 'POST',
				credentials: 'same-origin',
				keepalive: true,
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ c: written })
			}).catch(function () {});
		} catch (e) { /* storage still works without the refresh */ }
	}

	// ── Form population ──

	// Without consent every value is empty, so fields are cleared rather than filled.
	function values() {
		var v = {};
		STORED.forEach(function (k) { v[k] = granted ? get(k) : ''; });
		v.source_medium = v.source ? v.source + ' / ' + (v.medium || '(not set)') : '';
		v.form_page = granted ? pageUrl() : '';
		return v;
	}

	function fire(el, type) {
		var e;
		try { e = new w.Event(type, { bubbles: true }); } catch (x) { e = d.createEvent('Event'); e.initEvent(type, true, true); }
		el.dispatchEvent(e);
	}

	function setValue(el, val) {
		if (/^(checkbox|radio|file|submit|button|image|reset)$/.test(el.type || '')) { return false; }
		if (el.value === val) { return false; }
		el.value = val;
		fire(el, 'input');
		fire(el, 'change');
		return true;
	}

	var KEY_RE = /gd_ls_([a-z_]+?)\d*$/;

	function keyFromToken(token) {
		var m = KEY_RE.exec(token || '');
		return m && (STORED.indexOf(m[1]) !== -1 || m[1] === 'source_medium' || m[1] === 'form_page') ? m[1] : '';
	}

	// The key an element asks for through a gd_ls_ name, id, class, or wrapper class.
	function keyFor(el) {
		var k = keyFromToken(el.getAttribute('name')) || keyFromToken(el.id);
		if (k) { return k; }
		var node = el;
		while (node && node.nodeType === 1) {
			var classes = (node.getAttribute('class') || '').split(/\s+/);
			for (var i = 0; i < classes.length; i++) {
				if (classes[i].indexOf('gd_ls_') === 0 && (k = keyFromToken(classes[i]))) { return k; }
			}
			if (node.tagName === 'FORM') { break; }
			node = node.parentNode;
		}
		return '';
	}

	function fillNamed(v) {
		var count = 0;
		// gd_ls_ fields: always set, including to empty, so a value baked into a cached page is replaced.
		var els = d.querySelectorAll('input[name*="gd_ls_"], input[id*="gd_ls_"], input[class*="gd_ls_"], [class*="gd_ls_"] input');
		for (var i = 0; i < els.length; i++) {
			var el = els[i];
			if (el.hasAttribute('data-gd-ls') || (el.type !== 'hidden' && el.type !== 'text')) { continue; }
			var key = keyFor(el);
			if (key && setValue(el, v[key] || '')) { count++; }
		}
		// utm_ fields, as in v1.1.
		UTM_KEYS.forEach(function (k) {
			var list = d.querySelectorAll('input[name="utm_' + k + '"], input#utm_' + k + ', input#field_utm_' + k + ', input.utm_' + k + ', .utm_' + k + ' input');
			for (var j = 0; j < list.length; j++) {
				if ((list[j].type === 'hidden' || list[j].type === 'text') && v[k] && setValue(list[j], v[k])) { count++; }
			}
		});
		// Bare v1.1 names (name="source", id="field_source"): hidden inputs only, and only with a value.
		LEGACY.forEach(function (k) {
			if (!v[k]) { return; }
			var list = d.querySelectorAll('input[type="hidden"][name="' + k + '"], input[type="hidden"]#' + k + ', input[type="hidden"]#field_' + k + ', input[type="hidden"].' + k);
			for (var j = 0; j < list.length; j++) {
				if (setValue(list[j], v[k])) { count++; }
			}
		});
		return count;
	}

	function cssString(s) { return '"' + String(s).replace(/(["\\])/g, '\\$1') + '"'; }

	// Hide the field's own wrapper: the largest ancestor inside the form holding only this control.
	function hideField(el, form) {
		var target = el;
		var node = el.parentNode;
		while (node && node !== form && node.querySelectorAll('input, select, textarea').length === 1) {
			target = node;
			node = node.parentNode;
		}
		target.style.display = 'none';
		target.setAttribute('data-gd-ls-hidden', '1');
	}

	function fillMaps(v) {
		var count = 0;
		(C.field_maps || []).forEach(function (map) {
			var forms;
			try { forms = d.querySelectorAll(map.selector); } catch (e) { return; }
			for (var i = 0; i < forms.length; i++) {
				var form = forms[i];
				Object.keys(map.fields).forEach(function (key) {
					var name = map.fields[key];
					if (/REPLACE_/.test(name)) { return; }
					var el = form.querySelector('[name=' + cssString(name) + ']');
					if (!el) {
						el = d.createElement('input');
						el.type = 'hidden';
						el.name = name;
						el.setAttribute('data-gd-ls', key);
						form.appendChild(el);
					} else if (el.type !== 'hidden' && !el.hasAttribute('data-gd-ls-adopted')) {
						// The embed renders a visible field for it: take it over and hide it.
						el.required = false;
						el.removeAttribute('required');
						el.setAttribute('data-gd-ls-adopted', key);
						hideField(el, form);
					}
					if (setValue(el, v[key] || '')) { count++; }
				});
			}
		});
		return count;
	}

	function populate() {
		var v = values();
		var count = fillNamed(v) + fillMaps(v);
		if (count) { emit('gd_ls:populated', { changed: count }); }
		return count;
	}

	function emit(name, detail) {
		try { w.dispatchEvent(new w.CustomEvent(name, { detail: detail })); } catch (e) { /* old browsers */ }
	}

	// ── Consent ──

	// granted, denied or unknown. Mode "off" means the site stores without asking.
	function consentNow() {
		if (C.consent_mode === 'off') { return 'granted'; }
		if (C.consent_mode === 'wp_consent_api') {
			if (typeof w.wp_has_consent === 'function') {
				try { return w.wp_has_consent(C.consent_category || 'marketing') ? 'granted' : 'unknown'; } catch (e) { return 'unknown'; }
			}
			return 'unknown';
		}
		if (w.gdLeadSourceConsent === true) { return 'granted'; }
		if (w.gdLeadSourceConsent === false) { return 'denied'; }
		return 'unknown';
	}

	function grant() {
		state.consent = 'granted';
		if (granted) { return; }
		granted = true;
		persist();
		emit('gd_ls:captured', { reason: state.reason, touch: state.touch });
		startPopulation();
	}

	function deny() {
		state.consent = 'denied';
		granted = false;
		if (C.delete_on_deny) { STORED.forEach(delCookie); }
		populate();
	}

	function listenForConsent() {
		// Generic event for any consent tool:
		// window.dispatchEvent(new CustomEvent('gd_ls:consent', { detail: { granted: true } }))
		// { marketing: true } is accepted as well.
		w.addEventListener('gd_ls:consent', function (e) {
			var dt = (e && e.detail) || {};
			var yes = dt.granted === true || dt[C.consent_category || 'marketing'] === true;
			var no = dt.granted === false || dt[C.consent_category || 'marketing'] === false;
			if (yes) { grant(); } else if (no) { deny(); }
		});
		// WP Consent API change event, fired by consent plugins that support it.
		d.addEventListener('wp_listen_for_consent_change', function (e) {
			var dt = (e && e.detail) || {};
			var value = dt[C.consent_category || 'marketing'];
			if (value === 'allow') { grant(); } else if (value === 'deny') { deny(); }
		});
	}

	// ── Start ──

	var timer = null;
	function schedule() {
		if (timer) { return; }
		timer = setTimeout(function () { timer = null; populate(); }, 200);
	}

	var populationStarted = false;
	function startPopulation() {
		if (populationStarted) { populate(); return; }
		populationStarted = true;

		if (d.readyState === 'loading') { d.addEventListener('DOMContentLoaded', populate); } else { populate(); }
		setTimeout(populate, 1500);

		// Fill again the moment any form is submitted, before the form's own handlers read it.
		d.addEventListener('submit', function () { populate(); }, true);

		if (typeof w.MutationObserver === 'function' && d.body) {
			new w.MutationObserver(function (mutations) {
				for (var i = 0; i < mutations.length; i++) {
					if (mutations[i].addedNodes.length) { schedule(); return; }
				}
			}).observe(d.body, { childList: true, subtree: true });
		}
	}

	w.gdLeadSource = {
		version: C.version,
		state: function () {
			return { reason: state.reason, consent: state.consent, touch: state.touch, click: state.click, values: values() };
		},
		classify: function (url) { return classifyHost(hostOf(url)); },
		populate: populate,
		_test: { hostMatch: hostMatch, classifyHost: classifyHost, normSource: normSource, channelFor: channelFor }
	};

	// Work out this visit in memory first. Nothing is stored until consent allows it.
	capture();
	listenForConsent();
	if (consentNow() === 'granted') { grant(); } else { state.consent = consentNow(); }

	if (DEBUG && w.console) {
		w.console.log('[gd_ls] ' + C.version + ' capture: ' + state.reason + ', consent: ' + state.consent, state.touch);
		w.console.table(values());
	}
})(window, document);
/* GD_LS_ENGINE_END */
JS;
}


// ──────────────────────────────────────────────
// SERVER: COOKIE REFRESH
// ──────────────────────────────────────────────
// Safari limits cookies written by JavaScript to 7 days. After a new touch, the browser posts the
// names and expiry times it just wrote, and this route sets the same cookies from the server.
// Values are taken from the request's own cookies, never from the request body. POST requests are
// not page-cached, so this works on every host.

add_action( 'rest_api_init', 'gd_ls_register_rest' );

function gd_ls_register_rest() {
	if ( ! gd_ls_config()['server_refresh'] ) {
		return;
	}
	register_rest_route(
		'gd-ls/v1',
		'/refresh',
		array(
			'methods'             => 'POST',
			'callback'            => 'gd_ls_rest_refresh',
			'permission_callback' => '__return_true',
		)
	);
}

function gd_ls_rest_refresh( $request ) {
	$body  = $request->get_json_params();
	$list  = ( is_array( $body ) && isset( $body['c'] ) && is_array( $body['c'] ) ) ? $body['c'] : array();
	$now   = time();
	$max   = $now + ( 400 * DAY_IN_SECONDS );
	$keys  = gd_ls_stored_keys();
	$count = 0;

	foreach ( $list as $name => $expires ) {
		if ( $count >= 20 || ! is_string( $name ) || 0 !== strpos( $name, GD_LS_PREFIX ) ) {
			continue;
		}
		$key     = substr( $name, strlen( GD_LS_PREFIX ) );
		$expires = (int) $expires;
		if ( ! in_array( $key, $keys, true ) || $expires <= $now || $expires > $max ) {
			continue;
		}
		$value = gd_ls_cookie( $key );
		if ( '' === $value || headers_sent() ) {
			continue;
		}
		setrawcookie(
			$name,
			rawurlencode( $value ),
			array(
				'expires'  => $expires,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => false, // The browser script reads these.
				'samesite' => 'Lax',
			)
		);
		$count++;
	}

	$response = new WP_REST_Response( array( 'refreshed' => $count ), 200 );
	$response->header( 'Cache-Control', 'no-store' );
	return $response;
}


// ──────────────────────────────────────────────
// SERVER: READING VALUES
// ──────────────────────────────────────────────

/** A stored value from the request's cookies, sanitized and length-capped. */
function gd_ls_cookie( $key ) {
	$name = GD_LS_PREFIX . $key;
	if ( ! isset( $_COOKIE[ $name ] ) ) {
		return '';
	}
	$max = in_array( $key, array( 'referrer', 'landing_page' ), true ) ? 500 : 300;
	return substr( sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ), 0, $max );
}

/**
 * The value for a mappable key during a request, or null for keys only the browser knows
 * (form_page), so server-side fills leave those as submitted.
 */
function gd_ls_value( $key ) {
	if ( 'source_medium' === $key ) {
		$source = gd_ls_cookie( 'source' );
		if ( '' === $source ) {
			return '';
		}
		$medium = gd_ls_cookie( 'medium' );
		return $source . ' / ' . ( '' !== $medium ? $medium : '(not set)' );
	}
	return in_array( $key, gd_ls_stored_keys(), true ) ? gd_ls_cookie( $key ) : null;
}

/** Key named by a gd_ls_ field key, input name or parameter, allowing a numeric suffix (gd_ls_source2). */
function gd_ls_key_from_name( $name ) {
	if ( ! preg_match( '/gd_ls_([a-z_]+?)\d*$/', (string) $name, $m ) ) {
		return '';
	}
	return in_array( $m[1], gd_ls_mappable_keys(), true ) ? $m[1] : '';
}


// ──────────────────────────────────────────────
// SHORTCODES
// ──────────────────────────────────────────────
// [gd_ls_source], [gd_ls_channel], [gd_ls_source_medium], [gd_ls_first_touch] ... and [gd_ls_summary].
// For email notifications, which run during the submit request. Do not use them as hidden-field
// default values on a cached site: the value is saved into the cached page and shown to others.

add_action( 'init', 'gd_ls_register_shortcodes' );

function gd_ls_register_shortcodes() {
	foreach ( array_merge( gd_ls_stored_keys(), array( 'source_medium' ) ) as $key ) {
		add_shortcode(
			GD_LS_PREFIX . $key,
			function () use ( $key ) {
				return esc_html( (string) gd_ls_value( $key ) );
			}
		);
	}
	add_shortcode( 'gd_ls_summary', 'gd_ls_summary_shortcode' );
}

function gd_ls_summary_shortcode() {
	$lines   = array( '--- Lead Source ---' );
	$lines[] = 'Channel: ' . ( gd_ls_cookie( 'channel' ) ?: '(not set)' );
	$lines[] = 'Source / Medium: ' . ( gd_ls_value( 'source_medium' ) ?: '(not set)' );
	foreach ( array( 'campaign' => 'Campaign', 'term' => 'Term', 'gclid' => 'GCLID', 'gbraid' => 'GBRAID', 'wbraid' => 'WBRAID', 'msclkid' => 'MSCLKID' ) as $key => $label ) {
		$value = gd_ls_cookie( $key );
		if ( '' !== $value ) {
			$lines[] = $label . ': ' . $value;
		}
	}
	$lines[] = 'Referrer: ' . ( gd_ls_cookie( 'referrer' ) ?: '(direct)' );
	$lines[] = 'Landing Page: ' . gd_ls_cookie( 'landing_page' );
	$lines[] = 'Touch Time: ' . gd_ls_cookie( 'timestamp' );
	$lines[] = 'First Touch: ' . ( gd_ls_cookie( 'first_touch' ) ?: '(not set)' );
	return esc_html( implode( "\n", $lines ) );
}


// ──────────────────────────────────────────────
// FORM PLUGINS: FILL AGAIN AT SUBMIT
// ──────────────────────────────────────────────
// Submissions are never page-cached, so the server can set gd_ls_ fields from the visitor's own
// cookies. This overrides any value baked into a cached page and covers visitors whose browser
// script did not run.

// Formidable: hidden fields whose field key is gd_ls_<key> (Formidable may add a number: gd_ls_source2).
add_filter( 'frm_pre_create_entry', 'gd_ls_formidable_fill', 20 );

function gd_ls_formidable_fill( $values ) {
	if ( empty( $values['form_id'] ) || ! class_exists( 'FrmField' ) || ! method_exists( 'FrmField', 'get_all_for_form' ) ) {
		return $values;
	}
	foreach ( (array) FrmField::get_all_for_form( (int) $values['form_id'] ) as $field ) {
		$key   = isset( $field->field_key ) ? gd_ls_key_from_name( $field->field_key ) : '';
		$value = $key ? gd_ls_value( $key ) : null;
		if ( null !== $value ) {
			$values['item_meta'][ $field->id ] = $value;
		}
	}
	return $values;
}

// Gravity Forms: hidden fields with "Allow field to be populated dynamically" and parameter gd_ls_<key>.
add_action( 'init', 'gd_ls_gravity_forms_integration' );

function gd_ls_gravity_forms_integration() {
	if ( ! class_exists( 'GFForms' ) ) {
		return;
	}
	foreach ( array_merge( gd_ls_stored_keys(), array( 'source_medium' ) ) as $key ) {
		add_filter(
			'gform_field_value_' . GD_LS_PREFIX . $key,
			function () use ( $key ) {
				return (string) gd_ls_value( $key );
			}
		);
	}
}

add_action( 'gform_pre_submission', 'gd_ls_gravity_fill' );

function gd_ls_gravity_fill( $form ) {
	if ( empty( $form['fields'] ) ) {
		return;
	}
	foreach ( $form['fields'] as $field ) {
		$key   = isset( $field->inputName ) ? gd_ls_key_from_name( $field->inputName ) : '';
		$value = $key ? gd_ls_value( $key ) : null;
		if ( null !== $value ) {
			$_POST[ 'input_' . $field->id ] = $value; // phpcs:ignore WordPress.Security.NonceVerification
		}
	}
}

// Contact Form 7: [hidden gd_ls_source] and friends.
add_filter( 'wpcf7_form_elements', 'gd_ls_process_shortcodes_in_cf7' );

function gd_ls_process_shortcodes_in_cf7( $content ) {
	return do_shortcode( $content );
}

add_filter( 'wpcf7_posted_data', 'gd_ls_cf7_fill' );

function gd_ls_cf7_fill( $data ) {
	foreach ( (array) $data as $name => $posted ) {
		$key   = gd_ls_key_from_name( $name );
		$value = $key ? gd_ls_value( $key ) : null;
		if ( null !== $value ) {
			$data[ $name ] = $value;
		}
	}
	return $data;
}
