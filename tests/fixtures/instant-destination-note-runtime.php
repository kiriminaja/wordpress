<?php
/** Offline integration: real repository, WordPress client, installed SDK and transport. */
namespace {
	define( 'ABSPATH', dirname( __DIR__, 2 ) );
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function kiriof_log( ...$args ) { $GLOBALS['logs'][] = $args; }
	function kiriof_setting_repository() {
		return new class() {
			public function getSettingByKey( $key ) { return (object) array( 'value' => 'api_key' === $key ? 'private-token' : 'https://example.invalid' ); }
		};
	}
	function apply_filters( $hook, $value ) { return $value; }
	function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
	function trailingslashit( $value ) { return untrailingslashit( $value ) . '/'; }
	function get_temp_dir() { return sys_get_temp_dir(); }
	require ABSPATH . '/vendor/autoload.php';
	// As in InstantBookingTransportRuntimeTest, rename only the production class
	// to interpose its createClient seam; request/JSON/response logic is unchanged.
	$source = file_get_contents( ABSPATH . '/inc/Infrastructure/InstantApiTransport.php' );
	eval( str_replace( array( '<?php', 'class InstantApiTransport extends WordPressApiClient' ), array( '', 'class DestinationNoteDirectTransport extends WordPressApiClient' ), $source ) );
}
namespace KiriminAjaOfficial\Infrastructure {
	class InstantApiTransport extends DestinationNoteDirectTransport {
		protected static function createClient( array $options ): \Psr\Http\Client\ClientInterface {
			++$GLOBALS['clients'];
			return new class() implements \Psr\Http\Client\ClientInterface {
				public function sendRequest( \Psr\Http\Message\RequestInterface $request ): \Psr\Http\Message\ResponseInterface {
					$GLOBALS['requests'][] = array( 'method' => $request->getMethod(), 'url' => (string) $request->getUri(), 'agent' => $request->getHeaderLine( 'User-Agent' ), 'body' => (string) $request->getBody() );
					if ( count( $GLOBALS['requests'] ) > 1 ) { throw new \RuntimeException( 'Unexpected retry.' ); }
					return new \Nyholm\Psr7\Response( 200, array(), '{"status":true,"result":{"id":"offline-booking"}}' );
				}
			};
		}
	}
}
namespace {
	\KiriminAja\Base\Config\Cache\Cache::setStore( new class() implements \KiriminAja\Contracts\CacheStoreContract {
		private array $values = array();
		public function get( string $key ): mixed { return $this->values[ $key ] ?? null; }
		public function put( string $key, mixed $value, int $expiry ): bool { $this->values[ $key ] = $value; return true; }
		public function remove( string $key ): bool { unset( $this->values[ $key ] ); return true; }
	} );
	$payload = unserialize( base64_decode( $argv[1] ), array( 'allowed_classes' => false ) );
	$before = serialize( $payload );
	$GLOBALS['requests'] = $GLOBALS['logs'] = array();
	$GLOBALS['clients'] = 0;
	$repository = new \KiriminAjaOfficial\Repositories\InstantDeliveryApiRepository();
	ob_start();
	$response = $repository->book( $payload );
	$output = ob_get_clean();
	echo json_encode( array( 'response' => $response, 'diagnostics' => $repository->bookingDiagnostics(), 'requests' => $GLOBALS['requests'], 'clients' => $GLOBALS['clients'], 'logs' => $GLOBALS['logs'], 'output' => $output, 'input_unchanged' => $before === serialize( $payload ) ), JSON_THROW_ON_ERROR );
}
