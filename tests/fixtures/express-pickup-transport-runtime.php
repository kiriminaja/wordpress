<?php
/** Offline Express repository -> installed SDK -> PSR-18 capture. */
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require ABSPATH . 'vendor/autoload.php';

function wp_json_encode( $value ) {
	return json_encode( $value );
}
function kiriof_log( ...$args ) {
	$GLOBALS['express_transport_logs'][] = $args;
}

$input = json_decode( base64_decode( $argv[1] ), true, 512, JSON_THROW_ON_ERROR );
$GLOBALS['express_transport_logs'] = array();
\KiriminAja\Base\Config\Cache\Cache::setStore( new class() implements \KiriminAja\Contracts\CacheStoreContract {
	private array $values = array();
	public function get( string $key ): mixed { return $this->values[ $key ] ?? null; }
	public function put( string $key, mixed $value, int $expiry ): bool { $this->values[ $key ] = $value; return true; }
	public function remove( string $key ): bool { unset( $this->values[ $key ] ); return true; }
} );
\KiriminAja\Base\Config\KiriminAjaConfig::setBaseUrl( 'https://express-api.invalid' );
\KiriminAja\Base\Config\KiriminAjaConfig::setApiTokenKey( 'fixture-express-token' );

$http = new class( $input ) implements \Psr\Http\Client\ClientInterface {
	public array $requests = array();
	private array $input;
	public function __construct( array $input ) { $this->input = $input; }
	public function sendRequest( \Psr\Http\Message\RequestInterface $request ): \Psr\Http\Message\ResponseInterface {
		$this->requests[] = $request;
		if ( ! empty( $this->input['exception'] ) ) {
			throw new \RuntimeException( 'Offline fixture transport failure.' );
		}
		return new \Nyholm\Psr7\Response( $this->input['http_status'] ?? 200, array(), $this->input['body'] ?? '{"status":true,"pickup_number":"PICKUP-OFFLINE"}' );
	}
};
$client = new \KiriminAjaOfficial\Infrastructure\WordPressApiClient( $http );
$repository = new class( $client ) extends \KiriminAjaOfficial\Repositories\KiriminajaApiRepository {
	private \KiriminAjaOfficial\Infrastructure\WordPressApiClient $client;
	public function __construct( \KiriminAjaOfficial\Infrastructure\WordPressApiClient $client ) { $this->client = $client; }
	protected function create_api_client(): \KiriminAjaOfficial\Infrastructure\WordPressApiClient { return $this->client; }
};
$payload = $input['payload'];
ob_start();
$result = ! empty( $input['v2'] ) ? $repository->sendPickupRequestV2( $payload ) : $repository->sendPickupRequest( $payload );
$output = ob_get_clean();
$requests = array();
foreach ( $http->requests as $request ) {
	$body = json_decode( (string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR );
	// Report PIN comparisons as booleans, never include its value in test output.
	$pin_present = array_key_exists( 'pin', $body );
	$pin_unchanged = $pin_present && is_string( $body['pin'] ) && $body['pin'] === ( $payload['pin'] ?? null );
	unset( $body['pin'] );
	$requests[] = array(
		'method' => $request->getMethod(),
		'url' => (string) $request->getUri(),
		'query' => $request->getUri()->getQuery(),
		'user_agent' => $request->getHeaderLine( 'User-Agent' ),
		'content_type' => $request->getHeaderLine( 'Content-Type' ),
		'accept' => $request->getHeaderLine( 'Accept' ),
		'authorized' => 'Bearer fixture-express-token' === $request->getHeaderLine( 'Authorization' ),
		'cookie' => $request->getHeaderLine( 'Cookie' ),
		'payload' => $body,
		'pin_present' => $pin_present,
		'pin_unchanged' => $pin_unchanged,
	);
}
$logs = json_encode( $GLOBALS['express_transport_logs'] );
echo json_encode( array(
	'result' => $result,
	'result_data_is_object' => is_object( $result['data'] ?? null ),
	'requests' => $requests,
	'payload_unchanged' => $payload === $input['payload'],
	'logs_contain_pin' => isset( $payload['pin'] ) && str_contains( $logs, (string) $payload['pin'] ),
	'output_empty' => '' === $output,
), JSON_THROW_ON_ERROR );
