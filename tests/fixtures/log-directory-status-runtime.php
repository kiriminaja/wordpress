<?php
// Isolated WP HTTP/cache doubles; no network or WordPress bootstrap.
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require ABSPATH . 'inc/Services/LogDirectoryStatus.php';

$scenario = $argv[1] ?? '';
$cache = $requests = $writes = array();
function get_transient( $key ) { global $cache; return $cache[ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { global $cache, $writes; $cache[ $key ] = $value; $writes[] = $ttl; }
function is_wp_error( $value ) { return $value instanceof RuntimeException; }
function wp_remote_retrieve_response_code( $response ) { return $response['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function wp_remote_get( $url, $args ) {
	global $scenario, $requests;
	$requests[] = array( 'url' => $url, 'args' => $args );
	parse_str( parse_url( $url, PHP_URL_QUERY ), $query );
	$slug = $query['request']['slug'];
	$kind = str_contains( $url, '/plugins/' ) ? 'Plugin' : 'Theme';
	if ( 'errors' === $scenario ) {
		$responses = array(
			new RuntimeException( 'timeout with private details' ),
			array( 'code' => 429, 'body' => '{"error":"Plugin not found."}' ),
			array( 'code' => 503, 'body' => '{"error":"Plugin not found."}' ),
			array( 'code' => 404, 'body' => '<html>not found</html>' ),
			array( 'code' => 200, 'body' => '{"slug":"other","name":"Other","version":"1"}' ),
			array( 'code' => 200, 'body' => '{"slug":"invalid-json"' ),
			array( 'code' => 200, 'body' => '{"slug":"empty-response"}' ),
		);
		return $responses[ count( $requests ) - 1 ];
	}
	if ( 'deadline' === $scenario ) { usleep( 1100000 ); }
	if ( 'absent' === $scenario ) { return array( 'code' => 404, 'body' => json_encode( array( 'error' => $kind . ' not found' . ( 'Plugin' === $kind ? '.' : '' ) ) ) ); }
	return array( 'code' => 200, 'body' => json_encode( array( 'slug' => $slug, 'name' => 'Directory entry', 'version' => '1' ) ) );
}

$service = new \KiriminAjaOfficial\Services\LogDirectoryStatus();
$statuses = array();
if ( 'paths' === $scenario ) {
	$statuses[] = $service->describe_plugin( 'folder/main.php' );
	$statuses[] = $service->describe_plugin( 'single.php' );
	$statuses[] = $service->describe_theme( 'child-theme' );
	$statuses[] = $service->describe_theme( 'parent-theme' );
} elseif ( 'skip' === $scenario ) {
	$statuses[] = $service->describe_plugin( 'folder/main.php', 'https://private.invalid/update?token=SECRET' );
	$statuses[] = $service->describe_theme( 'child', 'false' );
	$statuses[] = $service->describe_plugin( 'helper.php', '', true );
	$statuses[] = $service->describe_plugin( '../bad.php' );
	$statuses[] = $service->describe_theme( 'Name with spaces' );
} elseif ( 'cache' === $scenario || 'absent' === $scenario ) {
	$statuses[] = $service->describe_plugin( 'folder/main.php' );
	$statuses[] = $service->describe_plugin( 'folder/other.php' );
	$next = new \KiriminAjaOfficial\Services\LogDirectoryStatus();
	$statuses[] = $next->describe_plugin( 'folder/main.php' );
	if ( 'absent' === $scenario ) { $statuses[] = $next->describe_theme( 'absent-theme' ); }
} else {
	$count = 'errors' === $scenario ? 7 : 12;
	for ( $i = 0; $i < $count; ++$i ) { $statuses[] = $service->describe_plugin( 'slug-' . $i . '/main.php' ); }
}
echo json_encode( compact( 'statuses', 'requests', 'writes' ) );
