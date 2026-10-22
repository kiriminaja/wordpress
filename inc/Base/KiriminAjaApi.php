<?php
namespace KiriminAjaOfficial\Base;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Infrastructure\WordPressApiClient;
use KiriminAja\Base\Config\Cache\Mode;
use KiriminAja\Base\Config\KiriminAjaConfig;
use KiriminAja\Responses\ServiceResponse;
use KiriminAjaOfficial\Utils\CreditBalance;

class KiriminAjaApi {
    protected string $base_url;
    public function __construct() {
        $this->base_url = $this->resolve_base_url();
        $this->configure_sdk();
    }

    protected function call_sdk( callable $callback, callable $success_formatter ): array {
        try {
            $response = $callback();
            if ( ! $response instanceof ServiceResponse ) {
                return $this->error_response( 'Invalid SDK response' );
            }

            if ( ! $response->status ) {
                return $this->error_response( $response->message );
            }

            $body = $success_formatter( $response->data, $response->message );

            return array(
                'status' => true,
                'data'   => $this->objectify( $body ),
            );
        } catch ( \Throwable $throwable ) {
            return $this->error_response( $throwable->getMessage() );
        }
    }

    public function get( $endpoint, $body = array(), $log_context = array() ) {
        return $this->request_with_sdk( 'GET', $endpoint, $body, $log_context );
    }

    public function post( $endpoint, $body = array(), $log_context = array(), $request_args = array() ) {
        unset( $request_args );

        return $this->request_with_sdk( 'POST', $endpoint, $body, $log_context );
    }

    protected function create_api_client(): WordPressApiClient {
        return new WordPressApiClient();
    }

	/**
	 * Fetch a utility envelope through the SDK client with WordPress identity.
	 *
	 * @param string                $method         GET or POST request method.
	 * @param string                $endpoint       API path relative to the configured base URL.
	 * @param array                 $body           Query parameters or JSON request payload.
	 * @param array<string, string> $fields         Required fields mapped to list, object, or scalar.
	 * @param bool                  $require_status Whether an absent API status is an error. Explicit non-true statuses always fail.
	 * @return array{status: bool, data: object|string} Validated envelope or a fixed, secret-safe error.
	 */
	protected function fetch_utility( string $method, string $endpoint, array $body = array(), array $fields = array(), bool $require_status = true ): array {
		try {
			$client = $this->create_api_client();
			[ $transport, $response ] = 'GET' === $method ? $client->get( $endpoint, $body ) : $client->post( $endpoint, $body );
			if ( true !== $transport || ! is_array( $response ) || ( array_key_exists( 'status', $response ) ? true !== $response['status'] : $require_status ) ) {
				return $this->error_response( 'Could not load utility data from KiriminAja.' );
			}
			foreach ( $fields as $field => $type ) {
				$value = $response[ $field ] ?? null;
				if ( 'scalar' === $type ) {
					if ( ! is_scalar( $value ) ) {
						return $this->error_response( 'Invalid KiriminAja utility response.' );
					}
					continue;
				}
				if ( ! is_array( $value ) || ( 'list' === $type ? ! array_is_list( $value ) : array_is_list( $value ) ) ) {
					return $this->error_response( 'Invalid KiriminAja utility response.' );
				}
				if ( 'list' === $type ) {
					foreach ( $value as $row ) {
						if ( ! is_array( $row ) || array_is_list( $row ) ) {
							return $this->error_response( 'Invalid KiriminAja utility response.' );
						}
					}
				}
			}
			$response['status'] = true;
			return array( 'status' => true, 'data' => $this->objectify( $response ) );
		} catch ( \Throwable $throwable ) {
			// Utility errors can echo credentials or payment details.
			return $this->error_response( 'Could not load utility data from KiriminAja.' );
		}
	}

	/**
	 * Normalize supported SDK profile wrappers into the results field.
	 *
	 * @return array{status: bool, data: object|string} Profile envelope or a secret-safe error.
	 */
	protected function fetch_profile(): array {
		$response = $this->fetch_utility( 'GET', 'api/mitra/v6.2/profile', array(), array(), false );
		if ( ! $response['status'] ) {
			return $response;
		}
		$body = $response['data'];
		foreach ( array( 'results', 'result', 'data' ) as $field ) {
			if ( isset( $body->$field ) && is_object( $body->$field ) && array() !== get_object_vars( $body->$field ) ) {
				$body->results = $body->$field;
				return $response;
			}
		}
		if ( isset( $body->id ) || isset( $body->name ) || isset( $body->email ) || isset( $body->metadata ) ) {
			$body->results = clone $body;
			return $response;
		}
		return $this->error_response( 'Invalid KiriminAja profile response.' );
	}

	/**
	 * Preserve supported balance wrappers only when the shared parser accepts the amount.
	 *
	 * @return array{status: bool, data: object|string} Balance envelope or a secret-safe error.
	 */
	protected function fetch_credit_balance(): array {
		$response = $this->fetch_utility( 'GET', 'api/mitra/v6.2/credit/balance', array(), array(), false );
		if ( $response['status'] && null !== CreditBalance::parse( $response['data'] ) ) {
			return $response;
		}
		return $this->error_response( 'Unable to verify credit balance.' );
	}

    private function request_with_sdk( string $method, string $endpoint, $body, array $log_context ): array {
        $endpoint     = ltrim( (string) $endpoint, '/' );
        $request_meta = $this->build_request_log_context( $method, $endpoint, $body, $log_context );

        try {
            $client = $this->create_api_client();
            if ( 'GET' === $method ) {
                [ $transport_status, $response ] = $client->get( $endpoint, $body );
            } else {
                [ $transport_status, $response ] = $client->post( $endpoint, $body );
            }

            if ( ! $transport_status ) {
                kiriof_log(
                    'error',
                    'KiriminAja SDK request failed.',
                    array_merge(
                        $request_meta,
                        array( 'sdk_error' => is_scalar( $response ) ? (string) $response : 'Unknown SDK error' )
                    )
                );

                return $this->error_response( is_scalar( $response ) ? (string) $response : 'KiriminAja SDK request failed' );
            }

            if ( ! is_array( $response ) ) {
                return $this->error_response( 'Invalid SDK response' );
            }

            if ( isset( $response['status'] ) && false === $response['status'] ) {
                return $this->error_response( $this->extract_api_error( $response ) );
            }

            return array(
                'status' => true,
                'data'   => $this->objectify( $response ),
            );
        } catch ( \Throwable $throwable ) {
            kiriof_log(
                'error',
                'KiriminAja SDK request threw an exception.',
                array_merge(
                    $request_meta,
                    array( 'sdk_error' => $throwable->getMessage() )
                )
            );

            return $this->error_response( $throwable->getMessage() );
        }
    }

    private function configure_sdk(): void {
        $api_token_row = kiriof_setting_repository()->getSettingByKey( 'api_key' );
        $api_token     = is_object( $api_token_row ) ? (string) ( $api_token_row->value ?? '' ) : '';
        try {
            KiriminAjaConfig::setCacheDirectory( trailingslashit( get_temp_dir() ) . 'kiriminaja-sdk' );
            KiriminAjaConfig::setMode( Mode::Production );
            KiriminAjaConfig::setBaseUrl( $this->base_url );
            KiriminAjaConfig::setApiTokenKey( $api_token );
        } catch ( \Throwable $throwable ) {
            kiriof_log(
                'error',
                'KiriminAja SDK configuration failed.',
                array(
                    'source'    => 'kiriminaja_api',
                    'operation' => 'configure_sdk',
                    'sdk_error' => $throwable->getMessage(),
                )
            );
        }
    }

    private function resolve_base_url(): string {
        $default_base_url = 'https://client.kiriminaja.com';
        $setting_row      = kiriof_setting_repository()->getSettingByKey( 'api_base_url' );
        $setting_base_url = is_object( $setting_row ) ? trim( (string) ( $setting_row->value ?? '' ) ) : '';
        $constant_url     = '';

        if ( defined( 'KIRIOF_API_BASE_URL' ) && is_string( constant( 'KIRIOF_API_BASE_URL' ) ) ) {
            $constant_url = trim( (string) constant( 'KIRIOF_API_BASE_URL' ) );
        }

        $candidate = $constant_url ?: ( $setting_base_url ?: $default_base_url );

        /**
         * Filters the base URL used by the KiriminAja SDK.
         *
         * @param string $candidate Current base URL candidate.
         */
        $candidate = (string) apply_filters( 'kiriof_api_base_url', $candidate );

        if ( '' === $candidate || ! preg_match( '#^https?://#i', $candidate ) ) {
            return $default_base_url;
        }

        return untrailingslashit( $candidate );
    }

    private function objectify( $value ) {
        if ( is_object( $value ) ) {
            return $value;
        }

        return json_decode( wp_json_encode( $value ) );
    }

    private function error_response( string $message ): array {
        return array(
            'status' => false,
            'data'   => '' !== trim( $message ) ? $message : 'Unknown error',
        );
    }

    private function extract_api_error( array $response ): string {
        if ( ! empty( $response['errors'] ) && is_array( $response['errors'] ) ) {
            $messages = array();
            foreach ( $response['errors'] as $error ) {
                foreach ( (array) $error as $message ) {
                    $messages[] = (string) $message;
                }
            }

            if ( ! empty( $messages ) ) {
                return 'Terdapat beberapa kesalahan pada data yang dikirim: ' . implode( ' ', $messages );
            }
        }

        return (string) ( $response['text'] ?? $response['message'] ?? 'Unknown error' );
    }

    private function build_request_log_context( string $method, string $endpoint, $body, array $log_context = array() ): array {
        return array_merge(
            array(
                'source'       => 'kiriminaja_api',
                'method'       => $method,
                'endpoint'     => $endpoint,
                'request_keys' => is_array( $body ) ? array_values( array_keys( $body ) ) : array(),
            ),
            $log_context
        );
    }
}
