<?php
namespace KiriminAjaOfficial;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** final : not extandable */
final class Init {
    
    /**
     * store all the classes inside array
     * @return string[]
     */
    public static function get_services(){
        return [
            Base\Enqueue::class,
            Pages\Onboarding::class,
            Pages\Admin::class,
            Services\PluginUpdateNoticeService::class,
            Services\ShipmentLocationService::class,
            Controllers\ProductController::class,
            Controllers\SettingController::class,
            Controllers\CallbackController::class,
            Controllers\GeneralAjaxController::class,
            Controllers\ShippingProcessController::class,
            Controllers\TransactionProcessController::class,
            Controllers\ShippingDiscountCouponController::class,
            Controllers\CheckoutController::class,
            Controllers\AccountAddressController::class,
            Controllers\TrackingFrontPageController::class,
            Controllers\EditOrderController::class,
            Controllers\CodAdjustmentController::class,
        ];
    }
    /**
     * loop through the classes, initialize and call register if exist
     * @return void
     */
    public static function register_services(){
        if ( class_exists( '\KiriminAjaOfficial\Migration\SetupMigration' ) ) {
            ( new \KiriminAjaOfficial\Migration\SetupMigration() )->register();
        }

        if ( Controllers\GeneralAjaxController::class === $class ) {
            return new $class( $checkout_service_factory );
        }

        if ( Controllers\CallbackController::class === $class ) {
            $setting_repository = new Repositories\SettingRepository();
            $api_key            = $setting_repository->getSettingByKey( 'api_key' );

            return new $class(
                new Services\CallbackHandlerService(
                    new Repositories\TransactionRepository(),
                    new Repositories\PaymentRepository(),
                    (string) ( $api_key->value ?? '' )
                )
            );
        }
        (new Services\ShipmentLocationService())->seedDefaultFromGlobalOrigin();
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
        $checkout_service_factory = kiriof_checkout_service_factory();

        if ( Pages\Admin::class === $class ) {
            return new $class(
                new Repositories\ProductVolumetricReadinessRepository(),
                new Repositories\TrackingPageRepository(),
                new Repositories\SettingRepository(),
                new Services\WooCommerceShippingMethodRegistrationService()
            );
        }

        if ( Controllers\SettingController::class === $class ) {
            return new $class(
                new Repositories\TrackingPageRepository(),
                new Repositories\SettingRepository()
            );
        }

        if ( Controllers\ShippingProcessController::class === $class ) {
            $transaction_repository = new Repositories\TransactionRepository();
            $api_repository         = new Repositories\KiriminajaApiRepository();

            return new $class(
                $transaction_repository,
                new Services\ShippingProcessServices\GetShippingProcessPayment(
                    $api_repository,
                    new Repositories\PaymentRepository(),
                    $transaction_repository
                ),
                $transaction_repository,
                $api_repository,
                new Services\TransactionProcessServices\GetRequestPickupScheduleService(
                    $api_repository,
                    $transaction_repository
                ),
                $checkout_service_factory
            );
        }

        if ( Controllers\CheckoutController::class === $class ) {
            return new $class(
                new Repositories\SettingRepository(),
                new Repositories\TransactionRepository(),
                new Repositories\WpPostMetaRepository(),
                $checkout_service_factory
            );
        }

        if ( Controllers\TransactionProcessController::class === $class ) {
            $transaction_repository = new Repositories\TransactionRepository();
            $setting_repository     = new Repositories\SettingRepository();
            $api_repository         = new Repositories\KiriminajaApiRepository();

            return new $class(
                $transaction_repository,
                new Infrastructure\WordPressDatabaseTransactionManager(),
                new Services\TransactionProcessServices\SendRequestPickupTransactionService(
                    $transaction_repository,
                    new Repositories\PaymentRepository(),
                    $setting_repository,
                    $api_repository,
                    new Services\ShipmentLocationService(),
                    new Services\SettingService( $setting_repository, $api_repository ),
                    new Services\KiriminajaApiService( $api_repository ),
                    new Services\TransactionProcessServices\RecipientDataResolver()
                ),
                new Services\TransactionProcessServices\CancelTransactionService(
                    $transaction_repository,
                    $api_repository
                ),
                new Services\TransactionProcessServices\GetRequestPickupScheduleService(
                    $api_repository,
                    $transaction_repository
                )
            );
        }

        if ( Controllers\CodAdjustmentController::class === $class ) {
            return new $class(
                new Repositories\TransactionRepository(),
                new Repositories\CodFeeApiRepository(),
                new Repositories\KiriminajaApiRepository()
            );
        }

        return new $class();
    }
}
