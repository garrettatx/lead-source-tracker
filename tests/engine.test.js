#!/usr/bin/env node
/*
 * Browser engine tests. Runs the real engine code (extracted from gd-lead-source-tracker.php) with
 * the real config (printed by tests/wp-harness.php) against a fake window, document and cookie jar.
 *
 *   node tests/engine.test.js          run the suite
 *   node tests/mutate.js               break each guard on purpose and confirm the suite notices
 *
 * DOM behavior (field population, embedded-form injection, hiding) is covered in the browser by
 * tests/browser/, not here.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { execFileSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');

function engineSource() {
	const php = fs.readFileSync(path.join(ROOT, 'gd-lead-source-tracker.php'), 'utf8');
	const start = php.indexOf('/* GD_LS_ENGINE_START */');
	const end = php.indexOf('/* GD_LS_ENGINE_END */');
	if (start < 0 || end < 0) { throw new Error('engine markers not found'); }
	return php.slice(start, end);
}

function loadConfig() {
	const out = execFileSync('php', [path.join(__dirname, 'wp-harness.php'), 'config', path.join(__dirname, 'fixtures', 'site-config.php')], { encoding: 'utf8' });
	return JSON.parse(out);
}

// ── Fake browser ──

function makeJar() { return {}; }

function cookieApi(jar) {
	return {
		get() { return Object.keys(jar).map((k) => k + '=' + jar[k]).join('; '); },
		set(str) {
			const parts = str.split(';').map((s) => s.trim());
			const eq = parts[0].indexOf('=');
			const name = parts[0].slice(0, eq);
			const value = parts[0].slice(eq + 1);
			const exp = parts.find((p) => /^expires=/i.test(p));
			if (exp && new Date(exp.slice(8)).getTime() < Date.now()) { delete jar[name]; return; }
			jar[name] = value;
		},
	};
}

function listeners() {
	const map = {};
	return {
		add(type, fn) { (map[type] = map[type] || []).push(fn); },
		remove(type, fn) { map[type] = (map[type] || []).filter((f) => f !== fn); },
		fire(type, detail) { (map[type] || []).slice().forEach((fn) => fn({ type, detail })); },
	};
}

/**
 * One page view. opts: url, referrer, config overrides, window extras (consent functions).
 * Returns the page's window so a test can fire events and read state.
 */
function visit(engine, baseConfig, jar, opts) {
	const u = new URL(opts.url || 'https://www.example-site.com/');
	const config = Object.assign({}, baseConfig, opts.config || {});
	const wl = listeners();
	const dl = listeners();
	const fetches = [];
	const cookies = cookieApi(jar);
	const document = {
		referrer: opts.referrer || '',
		readyState: 'complete',
		body: null,
		querySelectorAll() { return []; },
		addEventListener: dl.add,
		createEvent() { return { initEvent() {} }; },
	};
	Object.defineProperty(document, 'cookie', { get: cookies.get, set: cookies.set });
	const window = Object.assign({
		GD_LS_CONFIG: config,
		location: { href: u.href, search: u.search, protocol: u.protocol },
		addEventListener: wl.add,
		removeEventListener: wl.remove,
		dispatchEvent() { return true; },
		fetch(url, init) { fetches.push({ url, body: JSON.parse(init.body) }); return { catch() {} }; },
		console: { log() {}, table() {} },
	}, opts.window || {});
	vm.runInNewContext(engine, { window, document, URL, setTimeout: () => 0, Object, JSON, Math, Date, String, Number, RegExp });
	return { window, fireWindow: wl.fire, fireDocument: dl.fire, fetches, api: window.gdLeadSource };
}

function cookie(jar, key) {
	const v = jar['gd_ls_' + key];
	return v === undefined ? undefined : decodeURIComponent(v);
}

// ── Suite ──

function runSuite(engine, config, quiet) {
	const results = [];
	function test(name, fn) {
		try { fn(); results.push({ name, ok: true }); } catch (e) { results.push({ name, ok: false, error: e.message }); }
	}
	function eq(actual, expected, label) {
		if (actual !== expected) { throw new Error((label || '') + ' expected ' + JSON.stringify(expected) + ', got ' + JSON.stringify(actual)); }
	}
	const OFF = { consent_mode: 'off' };
	const go = (jar, opts) => visit(engine, config, jar, Object.assign({ config: OFF }, opts));

	// Classification (B1, B2 and the expanded lists)
	test('classification: known hosts', () => {
		const api = go(makeJar(), {}).api;
		const cases = {
			'https://gemini.google.com/app': 'gemini / ai-assistant',
			'https://mail.google.com/mail/u/0/': 'gmail / email',
			'https://docs.google.com/document/d/1': 'docs.google.com / referral',
			'https://www.target.com/': 'target.com / referral',
			'https://www.dropbox.com/': 'dropbox.com / referral',
			'https://www.homedepot.com/': 'homedepot.com / referral',
			'https://copilot.microsoft.com/': 'copilot / ai-assistant',
			'https://t.co/abc': 'twitter / social',
			'https://www.google.co.uk/': 'google / organic',
			'https://www.google.com/': 'google / organic',
			'https://search.brave.com/search?q=x': 'brave / organic',
			'android-app://com.google.android.googlequicksearchbox/https/www.google.com': 'google / organic',
			'https://chat.deepseek.com/': 'deepseek / ai-assistant',
			'https://chatgpt.com/': 'chatgpt / ai-assistant',
			'https://www.perplexity.ai/search/x': 'perplexity / ai-assistant',
			'https://m.facebook.com/': 'facebook / social',
			'https://l.instagram.com/': 'instagram / social',
			'https://www.angi.com/companylist/x': 'angi / referral',
			'https://www.bing.com/search?q=x': 'bing / organic',
			'https://duckduckgo.com/': 'duckduckgo / organic',
			'https://www.nextdoor.com/': 'nextdoor / social',
		};
		Object.keys(cases).forEach((url) => {
			const c = api.classify(url);
			eq(c && c.source + ' / ' + c.medium, cases[url], url);
		});
	});

	test('Google organic first visit', () => {
		const jar = makeJar();
		const p = go(jar, { url: 'https://www.example-site.com/bathroom-remodeling/?email=pat@x.com', referrer: 'https://www.google.com/' });
		eq(p.api.state().reason, 'referrer');
		eq(cookie(jar, 'source'), 'google');
		eq(cookie(jar, 'medium'), 'organic');
		eq(cookie(jar, 'channel'), 'Organic Search');
		eq(cookie(jar, 'referrer'), 'https://www.google.com/');
		eq(cookie(jar, 'landing_page'), 'https://www.example-site.com/bathroom-remodeling/', 'personal data stripped from landing page;');
		if (!/^Organic Search · google \/ organic · \/bathroom-remodeling\/ · \d{4}-\d{2}-\d{2}$/.test(cookie(jar, 'first_touch'))) { throw new Error('first_touch format: ' + cookie(jar, 'first_touch')); }
	});

	test('B3: direct first, then Google, records Google', () => {
		const jar = makeJar();
		go(jar, { url: 'https://www.example-site.com/' });
		eq(cookie(jar, 'source'), 'direct');
		eq(cookie(jar, 'channel'), 'Direct');
		go(jar, { url: 'https://www.example-site.com/', referrer: 'https://www.google.com/' });
		eq(cookie(jar, 'source'), 'google');
		eq(cookie(jar, 'first_touch').indexOf('Direct'), 0, 'first touch stays direct;');
	});

	test('direct never overwrites a real source', () => {
		const jar = makeJar();
		go(jar, { referrer: 'https://www.google.com/' });
		const p = go(jar, { url: 'https://www.example-site.com/start/' });
		eq(p.api.state().reason, 'direct-kept');
		eq(cookie(jar, 'source'), 'google');
		eq(p.fetches.length, 0, 'no refresh call on a kept record;');
	});

	test('B4: a new tagged visit replaces the whole record', () => {
		const jar = makeJar();
		go(jar, { url: 'https://www.example-site.com/?utm_source=google&utm_medium=cpc&utm_campaign=remodels&utm_term=kitchen+remodel&utm_content=rsa1' });
		eq(cookie(jar, 'term'), 'kitchen remodel');
		go(jar, { url: 'https://www.example-site.com/decks/?utm_source=newsletter&utm_medium=email' });
		eq(cookie(jar, 'source'), 'newsletter');
		eq(cookie(jar, 'campaign'), undefined, 'old campaign cleared;');
		eq(cookie(jar, 'term'), undefined, 'old term cleared;');
		eq(cookie(jar, 'content'), undefined, 'old content cleared;');
		eq(cookie(jar, 'channel'), 'Email');
		eq(cookie(jar, 'landing_page'), 'https://www.example-site.com/decks/?utm_source=newsletter&utm_medium=email');
	});

	test('B5: ChatGPT link with utm_source only is AI', () => {
		const jar = makeJar();
		go(jar, { url: 'https://www.example-site.com/?utm_source=chatgpt.com', referrer: 'https://chatgpt.com/' });
		eq(cookie(jar, 'source'), 'chatgpt');
		eq(cookie(jar, 'medium'), 'ai-assistant');
		eq(cookie(jar, 'channel'), 'AI Assistant');
	});

	test('GBP link is Google Business Profile', () => {
		const jar = makeJar();
		go(jar, { url: 'https://www.example-site.com/?utm_source=google&utm_medium=organic&utm_campaign=gbp-listing', referrer: 'https://www.google.com/' });
		eq(cookie(jar, 'channel'), 'Google Business Profile');
		eq(cookie(jar, 'campaign'), 'gbp-listing');
	});

	test('B6: gclid is Paid Search and survives a later organic visit', () => {
		const jar = makeJar();
		go(jar, { url: 'https://www.example-site.com/?gclid=Cj0KCQjw_abc-123XYZ' });
		eq(cookie(jar, 'source'), 'google');
		eq(cookie(jar, 'medium'), 'cpc');
		eq(cookie(jar, 'channel'), 'Paid Search');
		eq(cookie(jar, 'gclid'), 'Cj0KCQjw_abc-123XYZ');
		go(jar, { referrer: 'https://www.bing.com/' });
		eq(cookie(jar, 'source'), 'bing');
		eq(cookie(jar, 'gclid'), 'Cj0KCQjw_abc-123XYZ', 'click ID kept;');
	});

	test('B6: gbraid, then gclid replaces it; msclkid is Microsoft', () => {
		const jar = makeJar();
		go(jar, { url: 'https://www.example-site.com/?gbraid=0AAAAAC_test12345' });
		eq(cookie(jar, 'gbraid'), '0AAAAAC_test12345');
		eq(cookie(jar, 'channel'), 'Paid Search');
		go(jar, { url: 'https://www.example-site.com/?gclid=Cj0KCQjw_newclick' });
		eq(cookie(jar, 'gbraid'), undefined, 'earlier Google click ID replaced;');
		const jar2 = makeJar();
		go(jar2, { url: 'https://www.example-site.com/?msclkid=abcdef0123456789abcdef0123456789' });
		eq(cookie(jar2, 'source'), 'bing');
		eq(cookie(jar2, 'channel'), 'Paid Search');
	});

	test('B6: malformed click ID is ignored', () => {
		const jar = makeJar();
		go(jar, { url: 'https://www.example-site.com/?gclid=%3Cscript%3E' });
		eq(cookie(jar, 'gclid'), undefined);
		eq(cookie(jar, 'source'), 'direct');
	});

	test('internal referrer ignores UTMs', () => {
		const jar = makeJar();
		go(jar, { referrer: 'https://www.google.com/' });
		const p = go(jar, { url: 'https://www.example-site.com/start/?utm_source=banner&utm_medium=internal', referrer: 'https://www.example-site.com/' });
		eq(p.api.state().reason, 'internal');
		eq(cookie(jar, 'source'), 'google');
	});

	test('excluded referrer changes nothing', () => {
		const jar = makeJar();
		go(jar, { referrer: 'https://www.google.com/' });
		const p = go(jar, { referrer: 'https://accounts.google.com/signin' });
		eq(p.api.state().reason, 'excluded');
		eq(cookie(jar, 'source'), 'google');
	});

	test('first touch is written once', () => {
		const jar = makeJar();
		go(jar, { referrer: 'https://www.google.com/' });
		const first = cookie(jar, 'first_touch');
		go(jar, { referrer: 'https://www.facebook.com/' });
		eq(cookie(jar, 'source'), 'facebook');
		eq(cookie(jar, 'first_touch'), first);
	});

	test('channels: paid social, display, email, SMS, marketplace, referral', () => {
		const cases = [
			['?utm_source=facebook&utm_medium=paid-social', '', 'Paid Social'],
			['?utm_source=fb&utm_medium=cpc', '', 'Paid Social'],
			['?utm_source=google&utm_medium=display', '', 'Display'],
			['?utm_source=mailchimp&utm_medium=email', '', 'Email'],
			['?utm_source=twilio&utm_medium=sms', '', 'SMS'],
			['', 'https://www.houzz.com/pro/x', 'Marketplace'],
			['', 'https://www.austinchronicle.com/best-of', 'Referral'],
			['?utm_source=google&utm_medium=local', '', 'Google Business Profile'],
		];
		cases.forEach(([qs, ref, want]) => {
			const jar = makeJar();
			go(jar, { url: 'https://www.example-site.com/' + qs, referrer: ref });
			eq(cookie(jar, 'channel'), want, qs + ref + ';');
		});
	});

	test('values are cleaned and capped', () => {
		const jar = makeJar();
		go(jar, { url: 'https://www.example-site.com/?utm_source=%3Cscript%3Ealert(1)%3C/script%3E&utm_medium=cpc&utm_campaign=' + 'x'.repeat(500) });
		if (/[<>]/.test(cookie(jar, 'source'))) { throw new Error('angle brackets stored: ' + cookie(jar, 'source')); }
		eq(cookie(jar, 'campaign').length, 200);
	});

	test('legacy v1.1 cookie is respected on a direct visit', () => {
		const jar = { gd_ls_source: 'Google', gd_ls_medium: 'organic' };
		const p = go(jar, {});
		eq(p.api.state().reason, 'direct-kept');
		eq(cookie(jar, 'source'), 'Google');
	});

	test('refresh posts exactly the cookies just written', () => {
		const jar = makeJar();
		const p = go(jar, { url: 'https://www.example-site.com/?gclid=Cj0KCQjw_refresh01' });
		eq(p.fetches.length, 1);
		const names = Object.keys(p.fetches[0].body.c).sort().join(',');
		eq(names, 'gd_ls_campaign,gd_ls_channel,gd_ls_click_time,gd_ls_first_touch,gd_ls_gclid,gd_ls_landing_page,gd_ls_medium,gd_ls_referrer,gd_ls_source,gd_ls_timestamp'
			.split(',').filter((n) => jar[n] !== undefined).join(','));
	});

	// Consent
	test('consent event mode: nothing stored until granted, then stored', () => {
		const jar = makeJar();
		const p = visit(engine, config, jar, { url: 'https://www.example-site.com/?utm_source=google&utm_medium=cpc', config: { consent_mode: 'event' } });
		eq(p.api.state().consent, 'unknown');
		eq(Object.keys(jar).length, 0, 'no cookies before consent;');
		eq(p.api.state().values.source, '', 'fields empty before consent;');
		p.fireWindow('gd_ls:consent', { granted: true });
		eq(p.api.state().consent, 'granted');
		eq(cookie(jar, 'source'), 'google');
		eq(p.api.state().values.source, 'google');
	});

	test('returning visitor without consent on this page gets no filled values', () => {
		const jar = { gd_ls_source: 'google', gd_ls_medium: 'organic', gd_ls_channel: 'Organic%20Search' };
		const p = visit(engine, config, jar, { config: { consent_mode: 'event' } });
		eq(p.api.state().values.source, '');
		eq(p.api.state().values.channel, '');
		eq(p.api.state().values.source_medium, '');
		eq(p.api.state().values.form_page, '');
		eq(cookie(jar, 'source'), 'google', 'existing cookies untouched without a deny;');
	});

	test('consent event mode: { marketing: true } also grants', () => {
		const jar = makeJar();
		const p = visit(engine, config, jar, { referrer: 'https://www.google.com/', config: { consent_mode: 'event' } });
		p.fireWindow('gd_ls:consent', { marketing: true });
		eq(cookie(jar, 'source'), 'google');
	});

	test('consent preset on the page grants immediately', () => {
		const jar = makeJar();
		visit(engine, config, jar, { referrer: 'https://www.google.com/', config: { consent_mode: 'event' }, window: { gdLeadSourceConsent: true } });
		eq(cookie(jar, 'source'), 'google');
	});

	test('WP Consent API: allow stores, deny deletes', () => {
		const jar = makeJar();
		const p = visit(engine, config, jar, { referrer: 'https://www.google.com/', config: { consent_mode: 'wp_consent_api' }, window: { wp_has_consent: () => false } });
		eq(Object.keys(jar).length, 0);
		p.fireDocument('wp_listen_for_consent_change', { marketing: 'allow' });
		eq(cookie(jar, 'source'), 'google');
		p.fireDocument('wp_listen_for_consent_change', { marketing: 'deny' });
		eq(Object.keys(jar).filter((k) => k.indexOf('gd_ls_') === 0).length, 0, 'cookies deleted on deny;');
		eq(p.api.state().values.source, '');
	});

	test('WP Consent API: prior consent stores on load', () => {
		const jar = makeJar();
		visit(engine, config, jar, { referrer: 'https://www.google.com/', config: { consent_mode: 'wp_consent_api' }, window: { wp_has_consent: (c) => c === 'marketing' } });
		eq(cookie(jar, 'source'), 'google');
	});

	const failed = results.filter((r) => !r.ok);
	if (!quiet) {
		results.forEach((r) => console.log((r.ok ? '  ok   ' : '  FAIL ') + r.name + (r.ok ? '' : '\n       ' + r.error)));
		console.log(failed.length ? '\n' + failed.length + ' failure(s) of ' + results.length : '\nAll ' + results.length + ' engine tests passed');
	}
	return failed.length;
}

module.exports = { engineSource, loadConfig, runSuite };

if (require.main === module) {
	process.exit(runSuite(engineSource(), loadConfig()) ? 1 : 0);
}
