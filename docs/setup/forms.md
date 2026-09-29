# Forms

The plugin fills any field that asks for a value by name, id or CSS class starting `gd_ls_`, then
fills it again when the form is submitted. For WordPress form plugins it also sets the value on the
server at submit, from the visitor's own cookies.

**Leave default values empty on every host.** A default like `[gd_ls_source]` is rendered when the
page is built, and a cached page then shows that visitor's value to everyone. The plugin replaces
such values, but empty defaults keep the page source clean.

## Field Names

Use `gd_ls_` plus a key. Full definitions are in [fields and channels](fields-and-channels.md).

| Key | Example value |
|---|---|
| `channel` | Organic Search |
| `source_medium` | google / organic |
| `source`, `medium`, `campaign`, `term`, `content` | google, cpc, remodels |
| `landing_page` | https://example.com/bathrooms/?utm_source=google |
| `referrer` | https://www.google.com/ |
| `first_touch` | Organic Search · google / organic · /bathrooms/ · 2026-09-12 |
| `gclid`, `gbraid`, `wbraid`, `msclkid` | ad click IDs |
| `timestamp`, `click_time` | 2026-09-29 14:05:11 (site time zone) |
| `form_page` | the page the form was submitted from |

A useful set for most CRMs: `channel`, `source_medium`, `campaign`, `landing_page`, `referrer`,
`first_touch`, `gclid`, `form_page`.

## Formidable Forms

1. Add a **Hidden** field for each value.
2. In the field's advanced settings, set the **Field Key** to `gd_ls_<key>`, for example
   `gd_ls_channel`. If Formidable adds a number because the key is used on another form
   (`gd_ls_channel2`), leave it; the plugin reads past the number.
3. Leave **Default Value** empty.
4. For email notifications, add `[gd_ls_summary]` to the message, or reference the fields.

## Gravity Forms

1. Add a **Hidden** field for each value.
2. Under **Advanced**, tick **Allow field to be populated dynamically** and set the parameter name
   to `gd_ls_<key>`.
3. Optionally add the CSS class `gd_ls_<key>` to the field, so the browser fills it too.

## Contact Form 7

Add hidden tags named for the keys: `[hidden gd_ls_channel]`, `[hidden gd_ls_source_medium]`.

## Plain HTML Forms

`<input type="hidden" name="gd_ls_channel">`. An element with class `gd_ls_channel` that wraps an
input works too.

v1.1 names (`utm_source`, and hidden inputs named `source`, `medium` and so on) still work. The v1.1
rule that filled any input inside an element with class `source`, `content` or `term` is gone,
because it overwrote visible fields.

## Embedded CRM Forms

For a CRM form rendered as HTML on the page (not inside an iframe) whose fields have the CRM's own
names, add a field map to the site config:

```php
$config['field_maps'][] = array(
	'label'    => 'CRM form on /start/',
	'selector' => 'form[data-crm-form]',         // CSS selector for the form
	'fields'   => array(
		'channel'       => 'account.custom.ABC123',  // plugin key => the CRM's input name
		'source_medium' => 'account.custom.DEF456',
		'form_page'     => 'account.custom.GHI789',
	),
);
```

In each matching form, the plugin fills the named field if the embed already shows it (and hides it
and removes `required`), or adds a hidden input if not. Nothing in the embed code is edited, so
regenerating the embed doesn't undo it.

**On the CRM side:** create each custom field as **text** and add it to the web form, so the CRM
accepts it. The input names appear in the embed code afterward. A name containing `REPLACE_` is
skipped, so a config can go live before every ID is known.

**Iframe embeds** can't be filled from the page. Use a WordPress form that sends to the CRM instead.
