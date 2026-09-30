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
