<?php

namespace Inc;

/** final : not extandable */
final class Init {
    

    /**
     * store all the classes inside array
     * @return string[]
     */
    public static function get_services(){
        return [
            Base\Enqueue::class,
            Pages\Admin::class,
            Controllers\SettingController::class,
            Controllers\CallbackController::class,
            Controllers\GeneralAjaxController::class,
            Controllers\ShippingProcessController::class,
            Controllers\TransactionProcessController::class,
            Controllers\CheckoutController::class,
            Controllers\TrackingFrontPageController::class,
        ];
    }

    /**
     * loop through the classes, initialize and call register if exist
     * @return void
     */
    public static function register_services(){
//        error_log("register"."\n", 3, plugin_dir_path(__DIR__)."debug.log");
        foreach (self::get_services() as $class){
            $service = self::instantiate($class);
            if (method_exists($service,'register')){
                $service->register();
            }
            
        }
    }

    /**
     * return new instance
     * @param $class
     * @return mixed
     */
    private static function instantiate($class ){
        return new $class();
    }
}