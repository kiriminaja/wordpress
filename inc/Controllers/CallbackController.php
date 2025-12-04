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
            
            /** solve chached route*/
            try {
                flush_rewrite_rules();
            }catch (\Throwable $th){}
        } );
    }
    
    function kiriminAjaCallback()
    {
        try {
            $header = apache_request_headers();
            $raw_body = file_get_contents("php://input");
            $body = json_decode($raw_body);
            
            // Validate and sanitize the decoded body
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error([
                    'status'=>false,
                    'text'=>'Invalid JSON input',
                    'data'=>[]
                ]);
                return;
            }
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis('kiriminAjaCallback',[$body]);
            
            $service = (new \KiriminAjaOfficial\Services\CallbackHandlerService())->header($header)->body($body)->call();
            if ($service->status!==200){
                wp_send_json_error([
                    'status'=>false,
                    'text'=>$service->message,
                    'data'=>[]
                ]);
            }
            wp_send_json_success([
                'status'=>true,
                'text'=>$service->message,
                'data'=>[]
            ]);
        }catch (\Throwable $th){
            wp_send_json_error([
                'status'=>false,
                'text'=>$th->getMessage(),
                'data'=>[]
            ]);
        }
  
    }

}