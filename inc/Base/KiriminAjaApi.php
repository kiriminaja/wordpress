<?php
namespace KiriminAjaOfficial\Base;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KiriminAjaApi
{
    protected $base_url;
    protected $default_args;
    
    public function __construct()
    {
        $this->base_url = $this->resolve_base_url();
        $userAgent = $this->build_user_agent();
        
        $dbApiToken = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('api_key')->value ?? '';
        
        $this->default_args = array(
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.0',
            'user-agent' => $userAgent,
            'blocking' => true,
            'headers' => array(
                'Authorization' => 'Bearer ' . $dbApiToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => $userAgent
            ),
            'cookies' => array(),
            'body' => null,
            'compress' => false,
            'decompress' => true,
            'sslverify' => false,
            'stream' => false,
            'filename' => null,
        );
    }

    private function build_user_agent(): string
    {
        global $wp_version;

        $pluginVersion = defined('KIRIOF_VERSION') ? KIRIOF_VERSION : 'unknown';
        $wooCommerceVersion = defined('WC_VERSION') ? WC_VERSION : 'unknown';
        $siteUrl = get_bloginfo('url');

        return sprintf(
            'KiriminAjaOfficial/%s WordPress/%s WooCommerce/%s PHP/%s; %s',
            $pluginVersion,
            $wp_version,
            $wooCommerceVersion,
            PHP_VERSION,
            $siteUrl
        );
    }

    private function resolve_base_url(): string
    {
        $defaultBaseUrl = 'https://client.kiriminaja.com';

        $settingRow = ( new \KiriminAjaOfficial\Repositories\SettingRepository() )->getSettingByKey( 'api_base_url' );
        $settingBaseUrl = ( is_object( $settingRow ) && ! empty( $settingRow->value ) ) ? trim( (string) $settingRow->value ) : '';

        $constantBaseUrl = '';
        if ( defined( 'KIRIOF_API_BASE_URL' ) ) {
            $constantValue = constant( 'KIRIOF_API_BASE_URL' );
            if ( is_string( $constantValue ) ) {
                $constantBaseUrl = trim( $constantValue );
            }
        }

        $candidate = $constantBaseUrl ?: ( $settingBaseUrl ?: $defaultBaseUrl );

        /**
         * Filters the base URL used for KiriminAja API requests.
         *
         * @param string $candidate Current base URL candidate.
         */
        $candidate = (string) apply_filters( 'kiriof_api_base_url', $candidate );

        if ( '' === $candidate || ! preg_match( '#^https?://#i', $candidate ) ) {
            return $defaultBaseUrl;
        }

        return untrailingslashit( $candidate );
    }

    private function populate_output($response, array $request_meta = array())
    {
        if (is_wp_error($response)) {
            kiriof_log(
                'error',
                'KiriminAja API request failed because WordPress returned an HTTP transport error.',
                array_merge(
                    $request_meta,
                    array(
                        'wp_error_code'    => $response->get_error_code(),
                        'wp_error_message' => $response->get_error_message(),
                    )
                )
            );

            return array(
                'status' => false,
                'data' => $response->get_error_message()
            );
        }
        $body = wp_remote_retrieve_body($response);
        $decodedBody = json_decode($body);

        if (200 !== wp_remote_retrieve_response_code($response)) {
            kiriof_log(
                'error',
                'KiriminAja API request returned a non-success HTTP status.',
                array_merge(
                    $request_meta,
                    array(
                        'response_code' => wp_remote_retrieve_response_code($response),
                        'response_body' => is_string( $body ) ? substr( trim( $body ), 0, 500 ) : '',
                    )
                )
            );

            $message = 'Error ' . wp_remote_retrieve_response_code($response);
            if (is_object($decodedBody) && !empty($decodedBody->text)) {
                $message = (string) $decodedBody->text;
            } elseif (is_string($body) && '' !== trim($body)) {
                $message = trim($body);
            }

            return array(
                'status' => false,
                'data' => $message
            );
        }

        if (null === $decodedBody && JSON_ERROR_NONE !== json_last_error()) {
            kiriof_log(
                'error',
                'KiriminAja API request returned invalid JSON.',
                array_merge(
                    $request_meta,
                    array(
                        'json_error'    => json_last_error_msg(),
                        'response_body' => is_string( $body ) ? substr( trim( $body ), 0, 500 ) : '',
                    )
                )
            );

            return array(
                'status' => false,
                'data' => 'Invalid API response'
            );
        }

        if (is_object($decodedBody) && isset($decodedBody->status) && false === $decodedBody->status) {
            if (!empty($decodedBody->errors)) {
                $errorMessages = [];
                foreach ($decodedBody->errors as $field => $messages) {
                    if (is_array($messages)) {
                        foreach ($messages as $msg) {
                            $errorMessages[] = $msg;
                        }
                    } else {
                        $errorMessages[] = $messages;
                    }
                }
                $finalMessage = "Terdapat beberapa kesalahan pada data yang dikirim: " . implode(" ", $errorMessages);
            } else {
                $finalMessage = isset($decodedBody->text) ? $decodedBody->text : 'Unknown error';
            }

            kiriof_log(
                'warning',
                'KiriminAja API request returned a business validation error.',
                array_merge(
                    $request_meta,
                    array(
                        'response_message' => $finalMessage,
                    )
                )
            );

            return array(
                'status' => false,
                'data' => $finalMessage
            );
        }
        return array(
            'status' => true,
            'data' => $decodedBody
        );
    }
    
    public function get($endpoint, $body = array(), $log_context = array())
    {
        $args = wp_parse_args(array('body' => $body), $this->default_args);
        $request_meta = $this->build_request_log_context( 'GET', $endpoint, $body, $log_context );
        $response = wp_remote_get($this->base_url . $endpoint, $args);

        return $this->finalize_response( $response, $request_meta );
    }
    public function post($endpoint, $body = array(), $log_context = array(), $request_args = array())
    {
        $requestBody = $body;
        if ( is_array( $body ) && empty( $body ) ) {
            $requestBody = (object) array();
        }

        $args = wp_parse_args(
            array_merge( $request_args, array( 'body' => wp_json_encode( $requestBody ) ) ),
            $this->default_args
        );
        $request_meta = $this->build_request_log_context( 'POST', $endpoint, $body, $log_context );
        $response = wp_remote_post($this->base_url . $endpoint, $args);

        return $this->finalize_response( $response, $request_meta );
    }

    private function finalize_response( $response, array $request_meta ) {
        $output = $this->populate_output( $response, $request_meta );

        if ( ! empty( $output['status'] ) && apply_filters( 'kiriof_api_debug_logging', false, $request_meta, $output ) ) {
            kiriof_log(
                'debug',
                'KiriminAja API request completed successfully.',
                array_merge(
                    $request_meta,
                    array(
                        'response_code' => is_wp_error( $response ) ? null : wp_remote_retrieve_response_code( $response ),
                    )
                )
            );
        }

        return $output;
    }

    private function build_request_log_context( string $method, string $endpoint, $body, array $log_context = array() ): array {
        $request_keys = array();
        if ( is_array( $body ) ) {
            $request_keys = array_values( array_keys( $body ) );
        }

        return array_merge(
            array(
                'source'       => 'kiriminaja_api',
                'method'       => $method,
                'endpoint'     => $endpoint,
                'request_keys' => $request_keys,
            ),
            $log_context
        );
    }
}
