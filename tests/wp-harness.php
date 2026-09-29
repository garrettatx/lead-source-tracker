<?php
/**
 * Minimal WordPress stand-in so the plugin's PHP can run from the command line.
 *
 *   php tests/wp-harness.php config [site-config.php]   public config as JSON
 *   php tests/wp-harness.php footer [site-config.php]   footer HTML the plugin prints
 *   php tests/wp-harness.php server                     server-side assertions, exit 1 on failure
 *
 * A site config file is loaded the way an mu-plugin would be, after the core.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['gd_test_filters'] = array();
$GLOBALS['gd_test_cookies'] = array();

function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['gd_test_filters'][ $hook ][] = $cb; return true; }
function add_action( $hook, $cb, $priority = 10, $args = 1 ) { return add_filter( $hook, $cb, $priority, $args ); }
function apply_filters( $hook, $value ) {
	$args = array_slice( func_get_args(), 1 );
	foreach ( isset( $GLOBALS['gd_test_filters'][ $hook ] ) ? $GLOBALS['gd_test_filters'][ $hook ] : array() as $cb ) {
		$args[0] = call_user_func_array( $cb, $args );
	}
	return $args[0];
}
function home_url() { return 'https://www.example-site.com'; }
function site_url() { return 'https://www.example-site.com'; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function get_option( $name ) { return 'gmt_offset' === $name ? -5 : false; }
function rest_url( $path ) { return 'https://www.example-site.com/wp-json/' . $path; }
function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
function is_admin() { return false; }
function is_user_logged_in() { return false; }
function current_user_can( $cap ) { return false; }
function is_ssl() { return true; }
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $s ) ) ); }
function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function add_shortcode( $tag, $cb ) { $GLOBALS['gd_test_shortcodes'][ $tag ] = $cb; }
function do_shortcode( $c ) { return $c; }
function register_rest_route( $ns, $route, $args ) { $GLOBALS['gd_test_routes'][ $ns . $route ] = $args; }
function __return_true() { return true; }

class WP_REST_Response {
	public $data;
	public $status;
	public $headers = array();
	public function __construct( $data, $status ) { $this->data = $data; $this->status = $status; }
	public function header( $k, $v ) { $this->headers[ $k ] = $v; }
}

require dirname( __DIR__ ) . '/gd-lead-source-tracker.php';

$mode = isset( $argv[1] ) ? $argv[1] : 'config';
if ( ! empty( $argv[2] ) ) {
	require $argv[2];
}

if ( 'config' === $mode ) {
	echo wp_json_encode( gd_ls_public_config(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	exit( 0 );
}

if ( 'footer' === $mode ) {
	gd_ls_footer();
	exit( 0 );
}

// ── Server-side assertions ──

// Buffer output: in CLI any echo counts as headers sent, which a real REST request never has.
ob_start();

$failures = 0;
function check( $label, $actual, $expected ) {
	global $failures;
	if ( $actual === $expected ) {
		echo "  ok   $label\n";
	} else {
		$failures++;
		echo "  FAIL $label\n       expected " . var_export( $expected, true ) . "\n       got      " . var_export( $actual, true ) . "\n";
	}
}

$_COOKIE = array(
	'gd_ls_source'      => 'google',
	'gd_ls_medium'      => 'organic',
	'gd_ls_channel'     => 'Organic Search',
	'gd_ls_landing_page' => 'https://www.example-site.com/bathroom-remodeling/',
	'gd_ls_gclid'       => 'Cj0KCQjw_test-123',
	'gd_ls_bogus'       => 'x',
);

echo "Values\n";
check( 'source_medium computed', gd_ls_value( 'source_medium' ), 'google / organic' );
check( 'stored key read', gd_ls_value( 'channel' ), 'Organic Search' );
check( 'missing stored key is empty', gd_ls_value( 'campaign' ), '' );
check( 'form_page is browser-only (null)', gd_ls_value( 'form_page' ), null );
check( 'unknown key is null', gd_ls_value( 'bogus' ), null );

echo "Names\n";
check( 'plain key', gd_ls_key_from_name( 'gd_ls_source' ), 'source' );
check( 'Formidable numbered key', gd_ls_key_from_name( 'gd_ls_source2' ), 'source' );
check( 'multi-word key', gd_ls_key_from_name( 'gd_ls_landing_page' ), 'landing_page' );
check( 'Formidable id form', gd_ls_key_from_name( 'field_gd_ls_first_touch' ), 'first_touch' );
check( 'unknown key rejected', gd_ls_key_from_name( 'gd_ls_bogus' ), '' );
check( 'unrelated name rejected', gd_ls_key_from_name( 'email' ), '' );

echo "Formidable fill at submit\n";
class FrmField {
	public static function get_all_for_form( $id ) {
		return array(
			(object) array( 'id' => 21, 'field_key' => 'gd_ls_source' ),
			(object) array( 'id' => 22, 'field_key' => 'gd_ls_campaign3' ),
			(object) array( 'id' => 23, 'field_key' => 'gd_ls_form_page' ),
			(object) array( 'id' => 24, 'field_key' => 'name' ),
		);
	}
}
$out = gd_ls_formidable_fill( array( 'form_id' => 3, 'item_meta' => array( 21 => 'baked-from-cache', 22 => 'stale', 23 => 'https://x/start/', 24 => 'Pat' ) ) );
check( 'baked source replaced from cookie', $out['item_meta'][21], 'google' );
check( 'stale value cleared when no cookie', $out['item_meta'][22], '' );
check( 'form_page left as submitted', $out['item_meta'][23], 'https://x/start/' );
check( 'other fields untouched', $out['item_meta'][24], 'Pat' );

echo "Gravity fill at submit\n";
$_POST = array( 'input_5' => 'baked', 'input_6' => 'Pat' );
gd_ls_gravity_fill( array( 'fields' => array( (object) array( 'id' => 5, 'inputName' => 'gd_ls_channel' ), (object) array( 'id' => 6, 'inputName' => '' ) ) ) );
check( 'Gravity hidden field set', $_POST['input_5'], 'Organic Search' );
check( 'Gravity other field untouched', $_POST['input_6'], 'Pat' );

echo "CF7 fill at submit\n";
$data = gd_ls_cf7_fill( array( 'gd_ls_source' => 'baked', 'your-name' => 'Pat' ) );
check( 'CF7 field set', $data['gd_ls_source'], 'google' );
check( 'CF7 other field untouched', $data['your-name'], 'Pat' );

echo "Cookie refresh route\n";
class GD_Test_Request {
	private $b;
	public function __construct( $b ) { $this->b = $b; }
	public function get_json_params() { return $this->b; }
}
$in90 = time() + 90 * DAY_IN_SECONDS;
$res  = gd_ls_rest_refresh(
	new GD_Test_Request(
		array(
			'c' => array(
				'gd_ls_source'   => $in90,          // valid
				'gd_ls_gclid'    => $in90,          // valid
				'gd_ls_campaign' => $in90,          // no cookie value, skipped
				'gd_ls_bogus'    => $in90,          // not a plugin key
				'other_cookie'   => $in90,          // not ours
				'gd_ls_medium'   => time() - 10,    // expiry in the past
				'gd_ls_channel'  => time() + 500 * DAY_IN_SECONDS, // expiry too far out
			),
		)
	)
);
check( 'only valid cookies refreshed', $res->data['refreshed'], 2 );
check( 'response not cacheable', $res->headers['Cache-Control'], 'no-store' );
$res = gd_ls_rest_refresh( new GD_Test_Request( 'garbage' ) );
check( 'malformed body refreshes nothing', $res->data['refreshed'], 0 );

echo "Config\n";
$GLOBALS['gd_test_filters']['gd_ls_config'][] = function ( $c ) {
	$c['field_maps'][] = array( 'selector' => 'form.x', 'fields' => array( 'source' => 'crm.custom.1', 'not_a_key' => 'crm.custom.2', 'gclid' => '' ) );
	$c['last_touch_days'] = 9999;
	$c['consent_mode'] = 'nonsense';
	return $c;
};
$config = gd_ls_default_config(); // gd_ls_config() is cached; rebuild by calling the pieces
$maps   = gd_ls_clean_field_maps( apply_filters( 'gd_ls_config', $config )['field_maps'] );
check( 'field map keeps valid keys only', $maps[0]['fields'], array( 'source' => 'crm.custom.1' ) );
check( 'host cleaning strips scheme, www and path', gd_ls_clean_host( 'https://www.Angi.com/path' ), 'angi.com' );
check( 'host cleaning rejects junk', gd_ls_clean_host( 'a b<c' ), '' );

echo $failures ? "\n$failures failure(s)\n" : "\nAll server checks passed\n";
ob_end_flush();
exit( $failures ? 1 : 0 );
