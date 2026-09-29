# Configuration

Settings come from the `gd_ls_config` filter, in a small per-site file that loads beside the core.
Change only the keys you need; everything else keeps its default. Values are validated, so a typo
falls back to the default instead of breaking the page.

```php
<?php
add_filter( 'gd_ls_config', function ( $config ) {
	$config['gbp_campaign'] = 'gbp-listing';
	$config['marketplaces'] = array( 'angi.com', 'houzz.com', 'yelp.com' );
	return $config;
} );
```

## Settings

| Key | Default | What it does |
|---|---|---|
| `last_touch_days` | `30` (or `GD_LS_COOKIE_DAYS`) | How long the last touch lasts after the most recent touch. 1 to 400 |
| `first_touch_days` | `90` | How long the first-touch line lasts |
| `click_id_days` | `90` | How long ad click IDs last. Google Ads imports accept clicks up to 90 days old |
| `gbp_campaign` | `''` | The `utm_campaign` on the Business Profile website link. Visits with source `google` and this campaign get channel **Google Business Profile** |
| `marketplaces` | `[]` | Domains whose referrals count as channel **Marketplace**, for example `angi.com` |
| `own_hosts` | `[]` | Extra domains that count as the site itself. The home and site URL hosts are always included |
| `exclude_hosts` | sign-in and payment hosts | Referrers that are ignored entirely |
| `field_maps` | `[]` | Embedded forms that name fields their own way. See [forms](forms.md#embedded-crm-forms) |
| `server_refresh` | `true` | After a new touch, re-sends the cookies from the server so Safari keeps them longer |
| `consent_mode` | `'auto'` | `auto`, `wp_consent_api`, `event` or `off`. See [consent](consent.md) |
| `consent_category` | `'marketing'` | The consent category that allows storage |
| `delete_on_deny` | `true` | Deletes the plugin's cookies when a visitor refuses consent |
| `lists` | built in | Domain lists for search, AI, social, email and referral overrides |
| `source_aliases` | built in | `utm_source` shorthands, for example `fb` to `facebook` |

## Adding a Domain to a List

```php
$config['lists']['ai']['newassistant.com'] = 'newassistant';
```

`name.*` matches every TLD (`google.*` covers google.com and google.co.uk). Other patterns match the
host and its subdomains, never a partial name, so `t.co` doesn't match `target.com`.

## Other Hooks

| Hook | Type | Use |
|---|---|---|
| `gd_ls_can_store` | filter, bool | Return `false` to stop the plugin on a page entirely |
| `gd_ls:captured` | browser event | Fires after a visit is stored; `detail` has `reason` and `touch` |
| `gd_ls:populated` | browser event | Fires after fields change; `detail.changed` is the count |
| `gd_ls:consent` | browser event, listened for | A consent tool grants or refuses storage. See [consent](consent.md) |
