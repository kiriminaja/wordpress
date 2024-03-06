<?php
namespace Inc\Controllers;

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
            $header = apache_request_headers();
            $body = json_decode(file_get_contents("php://input"));
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