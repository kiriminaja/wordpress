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
        global $wp_version;
        $this->base_url = $this->resolve_base_url();
        
        $dbApiToken = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('api_key')->value ?? '';
        
        $this->default_args = array(
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.0',
            'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url'),
            'blocking' => true,
            'headers' => array(
                'Authorization' => 'Bearer ' . $dbApiToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'wordpress'
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

    private function populate_output($response)
    {
        if (is_wp_error($response)) {
            return array(
                'status' => false,
                'data' => $response->get_error_message()
            );
        }
        $body = wp_remote_retrieve_body($response);
        $decodedBody = json_decode($body);

        if (200 !== wp_remote_retrieve_response_code($response)) {
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis('api_call_err',$response);

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
    
    public function get($endpoint, $body = array())
    {
        $args = wp_parse_args(array('body' => $body), $this->default_args);
        $response = wp_remote_get($this->base_url . $endpoint, $args);
        if (class_exists('WPMonolog')) {
            global $logger;
            $logger->addDebug('[GET] ' . $this->base_url . $endpoint . ' | ' . serialize($args) . ' | ' . serialize($this->populate_output($response)));
        }
        return $this->populate_output($response);
    }
    public function post($endpoint, $body = array())
    {
        $requestBody = $body;
        if ( is_array( $body ) && empty( $body ) ) {
            $requestBody = (object) array();
        }

        $args = wp_parse_args(array('body' => wp_json_encode($requestBody)), $this->default_args);
        $response = wp_remote_post($this->base_url . $endpoint, $args);
        if (class_exists('WPMonolog')) {
            global $logger;
            $logger->addDebug('[POST] ' . $this->base_url . $endpoint . ' | ' . serialize($args) . ' | ' . serialize($this->populate_output($response)));
        }
        return $this->populate_output($response);
    }
}