<?php
// Test site config, loaded after the core the way a per-site mu-plugin would be.
add_filter( 'gd_ls_config', function ( $config ) {
	$config['gbp_campaign']   = 'gbp-listing';
	$config['marketplaces']   = array( 'angi.com', 'houzz.com', 'yelp.com' );
	$config['own_hosts'][]    = 'localhost';
	$config['server_refresh'] = getenv( 'GD_LS_REFRESH' ) !== '0';
	$config['field_maps'][]   = array(
		'label'    => 'Test CRM embed',
		'selector' => 'form[data-crm-form]',
		'fields'   => array(
			'channel'       => 'account.custom.TESTCHANNEL',
			'source_medium' => 'account.custom.TESTSM',
			'landing_page'  => 'account.custom.TESTLP',
			'first_touch'   => 'account.custom.TESTFT',
			'gclid'         => 'account.custom.TESTGCLID',
			'form_page'     => 'account.custom.TESTFP',
			'campaign'      => 'account.custom.REPLACE_CAMPAIGN',
		),
	);
	return $config;
} );
