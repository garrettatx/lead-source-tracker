#!/usr/bin/env node
/*
 * Mutation check: breaks each guard in the engine on purpose and confirms the engine suite fails.
 * A guard whose mutation still passes has no test protecting it.
 *
 *   node tests/mutate.js
 */
'use strict';

const { engineSource, loadConfig, runSuite } = require('./engine.test.js');

const MUTATIONS = [
	['B1 suffix matching becomes substring matching',
		"return host === pattern || host.slice(-(pattern.length + 1)) === '.' + pattern;",
		'return host.indexOf(pattern) !== -1;'],
	['B2 search checked before AI and email',
		"var ORDER = ['ai', 'email', 'search', 'social', 'marketplace'];",
		"var ORDER = ['search', 'ai', 'email', 'social', 'marketplace'];"],
	['B3 referrer only recorded when no source exists',
		'touch = fromReferrer(ref, host);',
		"if (!get('source')) { touch = fromReferrer(ref, host); }"],
	['direct visit overwrites a real source',
		"} else if (!get('source')) {",
		'} else if (true) {'],
	['B4 missing fields kept from an older touch',
		'if (t[k]) { setCookie(k, t[k], C.last_touch_days); } else { delCookie(k); }',
		'if (t[k]) { setCookie(k, t[k], C.last_touch_days); }'],
	['B5 medium not inferred from a known source',
		"medium = click ? 'cpc' : (MEDIUM_FOR[kind] || (source ? '(not set)' : ''));",
		"medium = click ? 'cpc' : '(not set)';"],
	['click ID format not validated',
		'if (p[CLICK[i]] && CLICK_RE.test(p[CLICK[i]])) {',
		'if (p[CLICK[i]]) {'],
	['internal referrer UTMs accepted',
		'if (host && isOwn(host)) {',
		'if (false) {'],
	['excluded referrers recorded',
		'} else if (host && anyMatch(host, C.exclude_hosts)) {',
		'} else if (false) {'],
	['first touch overwritten on every touch',
		"if (!get('first_touch')) { setCookie('first_touch'",
		"if (true) { setCookie('first_touch'"],
	['earlier Google click ID not replaced',
		'if (k !== click.type) { delCookie(k); }',
		''],
	['GBP campaign rule ignored',
		'(C.gbp_campaign && camp === C.gbp_campaign)',
		'false'],
	['consent gate removed',
		"if (consentNow() === 'granted') { grant(); } else { state.consent = consentNow(); }",
		'grant();'],
	['cookies kept after consent is refused',
		'if (C.delete_on_deny) { STORED.forEach(delCookie); }',
		''],
	['landing page keeps every query parameter',
		'if (/^utm_[a-z]+$/.test(k)) {',
		'if (true) {'],
	['angle brackets not stripped',
		'/[\\u0000-\\u001F\\u007F<>"]/g',
		'/[\\u0000-\\u001F\\u007F]/g'],
	['values filled without consent',
		"STORED.forEach(function (k) { v[k] = granted ? get(k) : ''; });",
		'STORED.forEach(function (k) { v[k] = get(k); });'],
	['no refresh call after a new touch',
		'if (state.touch || state.click) { refresh(); }',
		''],
];

const engine = engineSource();
const config = loadConfig();
let survived = 0;

if (runSuite(engine, config, true)) {
	console.log('The unmutated engine fails its own suite. Fix that first.');
	process.exit(1);
}

MUTATIONS.forEach(([label, from, to]) => {
	const count = engine.split(from).length - 1;
	if (count !== 1) {
		survived++;
		console.log('  STALE  ' + label + ' (pattern found ' + count + ' times; update tests/mutate.js)');
		return;
	}
	const failures = runSuite(engine.replace(from, to), config, true);
	if (failures) {
		console.log('  caught ' + label + ' (' + failures + ' test' + (failures > 1 ? 's' : '') + ' failed)');
	} else {
		survived++;
		console.log('  MISSED ' + label + ' (suite still passes)');
	}
});

console.log(survived ? '\n' + survived + ' guard(s) unprotected' : '\nEvery mutation was caught (' + MUTATIONS.length + ')');
process.exit(survived ? 1 : 0);
