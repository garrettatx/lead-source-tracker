# QA and E2E Plan

Three layers. The first two run on a laptop before any install; the third runs on a staging site,
then production.

## 1. Automated (Before Every Release)

```sh
php tests/wp-harness.php server
node tests/engine.test.js
node tests/mutate.js
```

All must pass, and `mutate.js` must report every mutation caught. A new guard gets a test and a
mutation in the same change.

## 2. Browser Fixture (Before Every Release)

```sh
tests/browser/build.sh
cd tests/browser && python3 -m http.server 8765
```

Open
`http://localhost:8765/fixture.html?utm_source=google&utm_medium=cpc&utm_campaign=remodels&gclid=Cj0KCQjw_e2etest01`
and wait three seconds. The page lists 28 checks and must end with "All 28 browser checks passed".
It covers baked-value replacement, fields that must never be touched, embedded-form injection,
adopting and hiding a visible field, a form rendered late, and values present at submit.

## 3. Staging Site, Then Production

Use a staging copy with page caching **on**, so the test matches production.

### Install

1. Upload `gd-lead-source-tracker.php` and the site config to `/wp-content/mu-plugins/`.
2. Load the homepage in a private window. View source: `gd-lead-source-tracker-config` and
   `gd-lead-source-tracker` scripts are present.
3. The page loads with no console errors, and the site's own forms and tags still work.

### Scenarios

Each in a **new private window**, so cookies start empty. Add `gd_ls_debug=1` to see the result in
the console. Check the expected values with `gdLeadSource.state().values`.

| # | Do | Expect |
|---|---|---|
| 1 | Search Google for the brand, click the organic result | `google / organic`, Organic Search, referrer `https://www.google.com/` |
| 2 | Open the Business Profile website link | Google Business Profile, campaign matches `gbp_campaign` |
| 3 | Visit `/?gclid=Cj0KCQjw_stagingtest01` | `google / cpc`, Paid Search, `gclid` set |
| 4 | Visit directly, then arrive from Google | Direct first, then `google / organic`; first touch stays Direct |
| 5 | Arrive from Google, then visit directly | Still `google / organic` (`direct-kept`) |
| 6 | Visit `/?utm_source=chatgpt.com` | `chatgpt / ai-assistant`, AI Assistant |
| 7 | Tagged visit with a campaign and term, then a tagged visit with source and medium only | Campaign and term cleared |
| 8 | Click an internal link carrying UTMs | Nothing changes (`internal`) |
| 9 | Land on the form page from Google and submit on that first page | Fields filled on the first page view |
| 10 | Open a page twice from cache (reload) | Hidden fields hold this visitor's values, never another's |
| 11 | iPhone Safari: arrive from Google, then check cookies 8+ days later | Cookies still present (server refresh worked) |
| 12 | If the site uses a consent tool: refuse, then accept | Nothing stored after refusal; stored on accept |

### Forms

For each form the site uses:

1. Inspect the hidden fields: every mapped field is present and filled.
2. For an embedded CRM form: the tracking fields are hidden, and no visible field is missing or
   stuck as required.
3. **Submit one test entry** per form, named so it's easy to find and delete (for example
   `TEST SOURCE <date>`). Confirm every value on the entry or CRM record, then delete it. Warn the
   client first, and keep conversion tags from firing (use GTM Preview, or the site's documented
   test method).

### Sign-Off

Record in the client's workspace: the date, the version, the md5 of the installed core file, each
scenario's result, and the test entries' IDs. Then repeat scenarios 1, 3, 9 and the form checks on
production.

## Regression Watch After Launch

- In the CRM, the share of leads with an empty channel. Above about 10 percent means something is
  blocking the script or the fields.
- The AI list each quarter.
- After any form or embed change, re-run the form checks.
