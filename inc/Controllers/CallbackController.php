<?php
namespace KiriminAjaOfficial\Controllers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CallbackController{
    public function register(){
        /** Adding New Route*/
        add_action( 'init', function (){
            add_feed( 'kiriminaja-callback', array($this,'kiriminAjaCallback') );
        } );
    }
    
    function kiriminAjaCallback()
    {
        try {
            if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
                kiriof_log(
                    'warning',
                    'KiriminAja webhook request was rejected because it used an unsupported HTTP method.',
                    array(
                        'source'         => 'kiriminaja_webhook',
                        'request_method' => sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ),
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

            $raw_body = file_get_contents("php://input");
            $body = json_decode($raw_body);
            
            // Validate and sanitize the decoded body
            if (json_last_error() !== JSON_ERROR_NONE) {
                kiriof_log(
                    'warning',
                    'KiriminAja webhook request was rejected because the JSON body was invalid.',
                    array(
                        'source'     => 'kiriminaja_webhook',
                        'json_error' => json_last_error_msg(),
                    )
                );

                wp_send_json_error([
                    'status'=>false,
                    'text'=>'Invalid JSON input',
                    'data'=>[]
                ]);
                wp_die();
            }

            // Recursively sanitize all decoded values before passing them downstream.
            $body = kiriof_sanitize_recursive( $body );

            // Sanitize header values as well; they are forwarded into downstream services.
            $sanitized_header = array();
            foreach ( $header as $h_key => $h_val ) {
                $sanitized_header[ sanitize_text_field( (string) $h_key ) ] = is_scalar( $h_val ) ? sanitize_text_field( (string) $h_val ) : '';
            }
            $header = $sanitized_header;

            $service = (new \KiriminAjaOfficial\Services\CallbackHandlerService())->header($header)->body($body)->call();
            if ($service->status!==200){
                kiriof_log(
                    'warning',
                    'KiriminAja webhook dispatch completed with an application error.',
                    array(
                        'source'         => 'kiriminaja_webhook',
                        'callback_method' => is_object( $body ) ? (string) ( $body->method ?? '' ) : '',
                        'message'        => $service->message,
                    )
                );

                wp_send_json_error([
                    'status'=>false,
                    'text'=>$service->message,
                    'data'=>[]
                ]);
                wp_die();
            }
            wp_send_json_success([
                'status'=>true,
                'text'=>$service->message,
                'data'=>[]
            ]);
            wp_die();
        }catch (\Throwable $th){
            kiriof_log(
                'error',
                'KiriminAja webhook controller failed before the request could be completed.',
                array(
                    'source'  => 'kiriminaja_webhook',
                    'message' => $th->getMessage(),
                )
            );

            wp_send_json_error([
                'status'=>false,
                'text'=>$th->getMessage(),
                'data'=>[]
            ]);
            wp_die();
        }
  
    }

}
