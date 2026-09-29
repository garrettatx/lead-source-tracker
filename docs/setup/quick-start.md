# Quick Start

About 20 minutes on a site with one form.

## 1. Install

Copy `gd-lead-source-tracker.php` to `/wp-content/mu-plugins/`. mu-plugins load automatically and
can't be deactivated from the dashboard. On WP Engine, upload by SFTP; its PHP sandbox can't write
`.php` files.

If v1.1 is installed, overwrite it. Two copies must never run at once.

## 2. Add a Site Config (Optional)

Only needed for a Business Profile campaign value, marketplace sites, extra own domains, or an
embedded CRM form. Create `gd-ls-config-<site>.php` in the same folder:

```php
<?php
add_filter( 'gd_ls_config', function ( $config ) {
	$config['gbp_campaign'] = 'gbp-listing';
	return $config;
} );
```

Every setting is in [configuration](configuration.md). Keep the file in the client's workspace
under version control.

## 3. Add Hidden Fields

Add hidden fields named for the values you want, for example `gd_ls_channel`,
`gd_ls_source_medium`, `gd_ls_landing_page`, `gd_ls_first_touch`, `gd_ls_gclid`. Leave their
default values empty. Per-plugin steps are in [forms](forms.md).

## 4. Test

1. Open a private window and visit
   `https://<site>/?utm_source=lst-test&utm_medium=test&utm_campaign=onboarding&gd_ls_debug=1`.
2. Open the browser console: it prints the capture result and a table of values.
3. Go to the form's page and check the hidden fields in the page inspector.
4. Submit one test entry and confirm the values on the entry or CRM record.

The full checklist is in the [QA and E2E plan](qa-e2e-plan.md).
