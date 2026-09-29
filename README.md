# GD Lead Source Tracker

Records where each visitor came from and puts it into hidden form fields, so every lead arrives with
its source: Google search, a Google Ads click, the Business Profile, ChatGPT, Facebook, a referral
site, or direct. One plugin for every host, including WP Engine and other full-page caches.

**Version:** 1.2.0 · **Author:** Garrett Digital · **Requires:** WordPress, PHP 7.4+

## How It Works

1. On every page, a small script reads the page's UTM parameters, ad click IDs and referrer, and
   works out the visit's source, medium and channel.
2. It stores the result in first-party cookies: the **last non-direct touch**, a one-line **first
   touch**, and any **ad click IDs**. A direct visit never overwrites a real source.
3. It fills hidden fields in forms on the page, and fills them again the moment a form is
   submitted. For WordPress form plugins, the server sets the same fields again at submit.

Capture runs in the browser because cached pages are served without running PHP. That's why one
version works on every host.

## Install

1. Copy `gd-lead-source-tracker.php` to `/wp-content/mu-plugins/` (or install it as a regular plugin).
2. Add a site config file beside it if the site needs anything beyond the defaults. See
   [configuration](docs/setup/configuration.md).
3. Add hidden fields to the forms. See [forms](docs/setup/forms.md).
4. Test with the [QA checklist](docs/setup/qa-e2e-plan.md).

Updating means replacing `gd-lead-source-tracker.php` only. The site config file stays.

**From v1.1:** overwrite the old file (same name). Never run two copies. Field names and shortcodes
are unchanged; recorded values change where v1.1 was wrong. See [CHANGELOG](CHANGELOG.md).

## Docs

| Page | Covers |
|---|---|
| [Quick start](docs/setup/quick-start.md) | Install to first test lead |
| [Configuration](docs/setup/configuration.md) | The `gd_ls_config` filter and every setting |
| [Forms](docs/setup/forms.md) | Formidable, Gravity Forms, Contact Form 7, plain HTML, embedded CRM forms |
| [Fields and channels](docs/setup/fields-and-channels.md) | What each field holds, attribution rules, channel rules |
| [Consent](docs/setup/consent.md) | Consent modes and how to connect a consent tool |
| [Troubleshooting](docs/setup/troubleshooting.md) | Empty fields, caching, Safari, debugging |
| [QA and E2E plan](docs/setup/qa-e2e-plan.md) | Staging and production test steps |
| [v2 plan](docs/v2-plan.md) | Roadmap, code review findings, decisions |

## Tests

```sh
php tests/wp-harness.php server   # server-side fills, refresh route, config validation
node tests/engine.test.js         # browser engine: attribution, channels, consent
node tests/mutate.js              # breaks each guard on purpose; every mutation must be caught
tests/browser/build.sh            # builds the browser fixture; see qa-e2e-plan.md
```

No dependencies beyond PHP and Node.

## Repo

One repo, one long-lived branch (`main`). Client settings never go in this repo: each site's config
lives in that site's config file and the client's own workspace.
