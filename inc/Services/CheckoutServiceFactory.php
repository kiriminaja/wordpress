<?php

namespace KiriminAjaOfficial\Services;

use KiriminAjaOfficial\Repositories\CodFeeApiRepository;
use KiriminAjaOfficial\Repositories\KiriminajaApiRepository;
use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Repositories\ShipmentLocationRepository;
use KiriminAjaOfficial\Repositories\TransactionRepository;
use KiriminAjaOfficial\Repositories\WpPostMetaRepository;
use KiriminAjaOfficial\Services\CheckoutServices\CheckoutCalculationService;
use KiriminAjaOfficial\Services\CheckoutServices\CodDeficitService;
use KiriminAjaOfficial\Services\CheckoutServices\CreateTransactionService;
use KiriminAjaOfficial\Services\CheckoutServices\OngkirPricingService;
use KiriminAjaOfficial\Services\KiriminAja\GenerateOrderId;
use KiriminAjaOfficial\Services\UtilServices\GetWCCartAttributeService;
use KiriminAjaOfficial\Utils\ServiceResponse;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CheckoutServiceFactory
{
    private SettingRepository $setting_repository;
    private TransactionRepository $transaction_repository;
    private WpPostMetaRepository $post_meta_repository;
    private KiriminajaApiRepository $api_repository;
    private CodFeeApiRepository $cod_fee_repository;
    private ShipmentLocationService $shipment_location_service;

    public function __construct(
        SettingRepository $setting_repository,
        TransactionRepository $transaction_repository,
        WpPostMetaRepository $post_meta_repository,
        KiriminajaApiRepository $api_repository,
        CodFeeApiRepository $cod_fee_repository,
        ShipmentLocationService $shipment_location_service
    ) {
        $this->setting_repository          = $setting_repository;
        $this->transaction_repository      = $transaction_repository;
        $this->post_meta_repository        = $post_meta_repository;
        $this->api_repository              = $api_repository;
        $this->cod_fee_repository          = $cod_fee_repository;
        $this->shipment_location_service   = $shipment_location_service;
    }

    public function districtSearch( string $search ): ServiceResponse
    {
        // Bound shared lookups to exact postcodes; free-text searches stay live.
        $cache_key = preg_match( '/^\d{5}$/D', $search ) === 1
            ? 'kiriof_district_search_v3_' . md5( $search )
            : null;
        if ( null !== $cache_key && function_exists( 'get_transient' ) ) {
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) ) {
                return new ServiceResponse( $cached, 'success', 200 );
            }
        }

        $response = ( new KiriminajaApiService( $this->api_repository ) )->sub_district_search( $search );
        if ( null !== $cache_key && 200 === $response->status && is_array( $response->data ) && function_exists( 'set_transient' ) ) {
            set_transient( $cache_key, $response->data, 300 );
        }

        return $response;
    }

    public function calculation( array $payload ): CheckoutCalculationService
    {
        return new CheckoutCalculationService(
            $payload,
            $this->setting_repository,
            $this->api_repository,
            $this->post_meta_repository
        );
    }

    public function pricing( array $payload ): OngkirPricingService
    {
        return new OngkirPricingService(
            $payload,
            $this->setting_repository,
            $this->api_repository,
            $this->post_meta_repository
        );
    }

    public function cartAttributes( array $payload ): GetWCCartAttributeService
    {
        return new GetWCCartAttributeService( $payload, $this->post_meta_repository );
    }

    public function codDeficit(): CodDeficitService
    {
        return new CodDeficitService( $this->cod_fee_repository, $this->setting_repository );
    }

    public function createTransaction( array $payload ): CreateTransactionService
    {
        return new CreateTransactionService(
            $payload,
            $this->transaction_repository,
            $this->setting_repository,
            $this->post_meta_repository,
            $this->codDeficit(),
            $this->shipment_location_service,
            new GenerateOrderId( $this->setting_repository, $this->transaction_repository ),
            $this
        );
    }
}
