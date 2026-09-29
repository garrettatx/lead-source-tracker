# Consent

Until consent allows it, the plugin works out the visit in memory but stores nothing and fills no
fields. When consent arrives on the page, it stores that visit at once. When consent is refused, it
deletes its cookies (unless `delete_on_deny` is `false`) and clears the fields.

A visible "How did you hear about us?" field is the visitor's own answer, not tracking, and works
without consent.

## Modes

Set with `consent_mode` in the site config.

| Mode | Behavior | Use when |
|---|---|---|
| `auto` (default) | Uses the WP Consent API if a consent plugin provides it; otherwise stores without asking | Most sites |
| `wp_consent_api` | Stores only when `wp_has_consent( 'marketing' )` is true, and follows its change events | The consent plugin supports the WP Consent API (Complianz and CookieYes say they do; check the plugin's docs) |
| `event` | Stores only after the site's consent tool says yes. No signal means no storage | A consent tool without WP Consent API support, or a site that wants storage off until a clear yes |
| `off` | Always stores | Sites that have decided they need no consent step |

The category is `marketing` by default (`consent_category`).

## Connecting a Consent Tool in Event Mode

When the visitor accepts, and on every page load after that, the consent tool (or a GTM tag
triggered by it) runs:

```js
window.dispatchEvent(new CustomEvent('gd_ls:consent', { detail: { granted: true } }));
```

On refusal, send `{ granted: false }`. `{ marketing: true }` also works. If the page already knows the
answer before the plugin loads, set `window.gdLeadSourceConsent = true` (or `false`).

## What Consent Doesn't Cover

- The server-side fill at submit reads only cookies that exist, and cookies exist only after
  consent, so it follows the same decision.
- The plugin doesn't record the consent decision itself; the consent tool does.
- This is a mechanism, not legal advice. Which mode a site needs is the client's decision.
