<?php
namespace KiriminAjaOfficial\Controllers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CallbackController{
    private \KiriminAjaOfficial\Services\CallbackHandlerService $callback_handler;

    public function __construct( \KiriminAjaOfficial\Services\CallbackHandlerService $callback_handler ) {
        $this->callback_handler = $callback_handler;
    }

    public function register(){
        /** Adding New Route*/
        add_action( 'init', function (){
            add_feed( 'kiriminaja-callback', array($this,'kiriminAjaCallback') );
        } );
    }
    
    function kiriminAjaCallback()
    {
        try {
            $request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) : '';
            if ( '' !== $request_method && 'POST' !== $request_method ) {
                kiriof_log(
                    'warning',
                    'KiriminAja webhook request was rejected because it used an unsupported HTTP method.',
                    array(
                        'source'         => 'kiriminaja_webhook',
                        'request_method' => $request_method,
                    )
                );

                wp_send_json_error(
                    array(
                        'status' => false,
                        'text'   => 'Method Not Allowed',
                        'data'   => array(),
                    ),
                    405
                );
                wp_die();
            }

            $header = array();
            if ( function_exists( 'getallheaders' ) ) {
                $header = getallheaders();
            }

            if ( empty( $header ) && ! empty( $_SERVER ) ) {
                foreach ( $_SERVER as $name => $value ) {
                    if ( 0 === strpos( $name, 'HTTP_' ) ) {
                        $normalized = str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) );
                        $header[ $normalized ] = $value;
                    }
                }
            }

            // Read at most 2 MiB plus one byte, irrespective of Content-Length.
            $raw_body = file_get_contents( 'php://input', false, null, 0, 2097153 );
            $body = is_string( $raw_body ) && strlen( $raw_body ) <= 2097152 ? json_decode( $raw_body ) : null;

            // Only JSON objects are callback envelopes. Do not normalize identity values.
            if ( ! is_object( $body ) || JSON_ERROR_NONE !== json_last_error() ) {
                kiriof_log(
                    'warning',
                    'KiriminAja webhook request was rejected because the JSON body was invalid.',
                    array(
                        'source' => 'kiriminaja_webhook',
                    )
                );

                wp_send_json_error(
                    array(
                        'status' => false,
                        'text'   => 'Invalid JSON input',
                        'data'   => array(),
                    ),
                    400
                );
                wp_die();
            }

            // Authentication must compare the original header bytes. Normalize names only;
            // the handler owns Bearer parsing and payload validation after authentication.
            $normalized_header = array();
            foreach ( (array) $header as $h_key => $h_val ) {
                if ( is_string( $h_key ) && preg_match( '/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/D', $h_key ) && is_scalar( $h_val ) ) {
                    $normalized_header[ strtolower( $h_key ) ] = (string) $h_val;
                }
            }
            $header = $normalized_header;

            $service = $this->callback_handler->header($header)->body($body)->call();
            if ($service->status!==200){
                wp_send_json_error(
                    array(
                        'status' => false,
                        'text'   => $service->message,
                        'data'   => $service->data,
                    ),
                    $service->status
                );
                wp_die();
            }
            wp_send_json_success(
                array(
                    'status' => true,
                    'text'   => $service->message,
                    'data'   => array(),
                )
            );
            wp_die();
        }catch (\Throwable $th){
            kiriof_log(
                'error',
                'KiriminAja webhook controller failed before the request could be completed.',
                array(
                    'source'  => 'kiriminaja_webhook',
                    'exception_class' => get_class( $th ),
                )
            );

            wp_send_json_error(
                array(
                    'status' => false,
                    'text'   => 'Unable to process KiriminAja callback. Please retry.',
                    'data'   => array(),
                ),
                500
            );
            wp_die();
        }
  
    }

}
