<?php
namespace KiriminAjaOfficial\Base;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAja\Base\Api\Api;
use KiriminAja\Base\Config\Cache\Mode;
use KiriminAja\Base\Config\KiriminAjaConfig;
use KiriminAja\Responses\ServiceResponse;

class KiriminAjaApi {
    protected string $base_url;
    protected string $api_token = '';

    public function __construct() {
        $this->base_url = $this->resolve_base_url();
        $this->configure_sdk();
    }

    private function build_wordpress_agent(): string {
        global $wp_version;

        return sprintf(
            'KiriminAjaOfficial/%s WordPress/%s WooCommerce/%s PHP/%s; %s',
            defined( 'KIRIOF_VERSION' ) ? KIRIOF_VERSION : 'unknown',
            isset( $wp_version ) ? (string) $wp_version : 'unknown',
            defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
            PHP_VERSION,
            function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'url' ) : ''
        );
    }

    /**
     * Use WordPress HTTP for endpoints whose SDK normalizer is not compatible
     * with all currently deployed response envelopes.
     */
    protected function get_with_wordpress( string $endpoint, array $body = array(), array $log_context = array() ): array {
        $request_meta = $this->build_request_log_context( 'GET', $endpoint, $body, $log_context );
        $url          = $this->base_url . '/' . ltrim( $endpoint, '/' );

        $response = wp_remote_get(
            $url,
            array(
                'timeout'     => 30,
                'redirection' => 5,
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $this->api_token,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'User-Agent'    => 'wordpress',
                    'X-WP-Agent'    => $this->build_wordpress_agent(),
                ),
                'body'        => $body,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $this->error_response( $response->get_error_message() );
        }

        $status_code = (int) wp_remote_retrieve_response_code( $response );
        $raw_body    = wp_remote_retrieve_body( $response );
        $decoded     = json_decode( $raw_body, true );
        if ( $status_code < 200 || $status_code >= 300 ) {
            return $this->error_response(
                is_array( $decoded ) ? $this->extract_api_error( $decoded ) : 'Error ' . $status_code
            );
        }
        if ( ! is_array( $decoded ) ) {
            return $this->error_response( 'Invalid API response' );
        }
        if ( isset( $decoded['status'] ) && false === $decoded['status'] ) {
            return $this->error_response( $this->extract_api_error( $decoded ) );
        }

        if ( apply_filters( 'kiriof_api_debug_logging', false, $request_meta, $decoded ) ) {
            kiriof_log( 'debug', 'KiriminAja WordPress HTTP request completed successfully.', $request_meta );
        }

        return array(
            'status' => true,
            'data'   => $this->objectify( $decoded ),
        );
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

    private function request_with_sdk( string $method, string $endpoint, $body, array $log_context ): array {
        $endpoint     = ltrim( (string) $endpoint, '/' );
        $request_meta = $this->build_request_log_context( $method, $endpoint, $body, $log_context );

        try {
            $client = new Api();
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
        $this->api_token = $api_token;

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
