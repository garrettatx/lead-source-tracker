# Troubleshooting

## See What the Plugin Recorded

Add `?gd_ls_debug=1` to any URL. The console prints the capture result, the consent state and a
table of values. Logged-in editors are skipped by default; with this parameter they get the script
too.

On any page, `window.gdLeadSource.state()` in the console returns the same, and
`window.gdLeadSource.classify('https://example.com/')` shows how a referrer would be classified.

| `reason` | Meaning |
|---|---|
| `tagged` | UTMs or a click ID started a new touch |
| `referrer` | An external referrer started a new touch |
| `direct-first` | Direct visit with no earlier record; recorded as direct |
| `direct-kept` | Direct visit; the earlier source was kept |
| `internal` | Came from another page on the site; nothing changed |
| `excluded` | Referrer is on the exclude list; nothing changed |

## Fields Are Empty

1. **Consent.** `state().consent` must be `granted`. See [consent](consent.md).
2. **Name.** The field must use a `gd_ls_` name, id or class, or appear in a field map.
3. **Editor account.** Logged-in editors don't get the script without `?gd_ls_debug=1`. Test in a
   private window.
4. **Iframe.** Forms inside an iframe can't be filled from the page.
5. **Script missing.** View source and search for `gd-lead-source-tracker`. If it's absent, a
   `gd_ls_can_store` filter returned false, or the theme doesn't call `wp_footer()`.

## Values Look Wrong

- **Everything says direct:** the site may send `Referrer-Policy: no-referrer`. Use
  `strict-origin-when-cross-origin`, the browser default.
- **A domain is classified wrongly:** add it to a list in the site config. See
  [configuration](configuration.md).
- **Source is the site's own other domain:** add that domain to `own_hosts`.

## Caching

The plugin doesn't need cache exclusions. Capture and filling run in the browser, and form
submissions are never cached. The one caching risk is a hidden-field default value that uses a
shortcode, which bakes one visitor's value into the cached page. Leave default values empty.

## Safari

Safari keeps cookies set by JavaScript for 7 days at most. With `server_refresh` on, the plugin
re-sends the cookies from the server after each new touch, which Safari should keep for the full
window. Confirm on an iPhone during QA; see the [QA plan](qa-e2e-plan.md).
