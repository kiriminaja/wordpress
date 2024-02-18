<?php
namespace Inc\Controllers;

class CallbackController{

    public function register(){
        add_action( 'init', function (){
            add_feed( 'kiriminaja-callback', array($this,'kiriminAjaCallback') );
        } );
    }
//    function kiriminAjaCallbackRegister()
//    {
//        add_feed( 'kiriminaja-callback', array($this,'kiriminAjaCallback') );
//    }
    function kiriminAjaCallback()
    {
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
    }

}