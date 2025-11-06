<?php
namespace Inc\Controllers;

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
            // Get headers in a server-agnostic way (works on Apache, Nginx, etc.)
            $header = array();
            if (function_exists('getallheaders')) {
                $header = getallheaders();
            } else {
                // Fallback for servers without getallheaders()
                foreach ($_SERVER as $key => $value) {
                    if (strpos($key, 'HTTP_') === 0) {
                        $header_key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                        $header[$header_key] = $value;
                    } elseif (in_array($key, array('CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'), true)) {
                        $header_key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                        $header[$header_key] = $value;
                    }
                }
            }
            
            $body = json_decode(file_get_contents("php://input"));

            (new \Inc\Base\BaseInit())->logThis('kiriminAjaCallback',[$body]);
            
            $service = (new \Inc\Services\CallbackHandlerService())->header($header)->body($body)->call();
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