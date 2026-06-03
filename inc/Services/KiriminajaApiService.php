<?php
namespace KiriminAjaOfficial\Services;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use \KiriminAjaOfficial\Base\BaseService;
use KiriminAjaOfficial\Init;
class KiriminajaApiService extends BaseService{
    private const KIRIOF_PROFILE_CACHE_KEY = 'kiriof_profile_cache';
    private const KIRIOF_PROFILE_LAST_SUCCESS_CACHE_KEY = 'kiriof_profile_last_success_cache';
    private const KIRIOF_PROFILE_CACHE_TTL = 60;

    public function sub_district_search($search)
    {
        $repo = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->sub_district_search($search);
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$repo',[$repo]);
        if (!@$repo['status'] || !@$repo['data']->status){
            return self::error([],@$repo['data']->text ?? 'Something is wrong');
        }
        return self::success($repo['data']->result);
    }
    public function getPayment($payment_id)
    {
        $repo = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->getPayment([
            'payment_id'=>$payment_id
        ]);
        if (!@$repo['status'] || !@$repo['data']->status){
            return self::error([],@$repo['data']->text ?? 'Something is wrong');
        }
        return self::success($repo['data']->data);
    }
    public function getTracking($order_id)
    {
        $repo = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->getTracking([
            'order_id'=>$order_id
        ]);
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$repo',[$repo]);
                
        if (!@$repo['status'] || !@$repo['data']->status){
            return self::error([],@$repo['data']->text ?? 'Something is wrong');
        }
        return self::success($repo['data']);
    }
    private const KIRIOF_COURIERS_CACHE_KEY = 'kiriof_couriers_list_v2';
    private const KIRIOF_COURIERS_CACHE_TTL = DAY_IN_SECONDS;

    public function get_couriers(){
        $cached = get_transient( self::KIRIOF_COURIERS_CACHE_KEY );
        if ( false !== $cached ) {
            return self::success( $cached );
        }

        $repo = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->get_couriers();
        if (!@$repo['status'] || !@$repo['data']->status){
            return self::error([],@$repo['data']->text ?? 'Something is wrong');
        }

        $excluded_types = array( 'instant', 'international' );
        $data           = array_values(
            array_filter(
                (array) $repo['data']->datas,
                function ( $courier ) use ( $excluded_types ) {
                    $type = strtolower( (string) ( ( (object) $courier )->type ?? '' ) );
                    return ! in_array( $type, $excluded_types, true );
                }
            )
        );
        set_transient( self::KIRIOF_COURIERS_CACHE_KEY, $data, self::KIRIOF_COURIERS_CACHE_TTL );
        return self::success( $data );
    }

    public function invalidateCouriersCache(): void {
        delete_transient( self::KIRIOF_COURIERS_CACHE_KEY );
    }

    /**
     * Returns a map of courier_code => label built from the API list.
     *
     * @return array<string, string>
     */
    public function getCourierNameMap(): array {
        $service = $this->get_couriers();
        if ( 200 !== $service->status || empty( $service->data ) ) {
            return array();
        }

        $map = array();
        foreach ( (array) $service->data as $courier ) {
            $courier = (object) $courier;
            if ( empty( $courier->code ) ) {
                continue;
            }
            $label = ! empty( $courier->name ) ? (string) $courier->name : strtoupper( (string) $courier->code );
            if ( ! empty( $courier->type ) ) {
                $label .= ' (' . (string) $courier->type . ')';
            }
            $map[ strtolower( (string) $courier->code ) ] = $label;
        }

        return $map;
    }
    public function getProvinces(){
        $repo = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->getProvinces();
        $rows = $this->extractListRows($repo);
        if (!@$repo['status'] || empty($rows)){
            return self::error([], $this->extractErrorMessage($repo, __('Failed to load provinces.', 'kiriminaja-official')));
        }

        return self::success($rows);
    }
    public function getCitiesByProvinceId($provinceId){
        $repo = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->getCitiesByProvinceId($provinceId);
        $rows = $this->extractListRows($repo);
        if (!@$repo['status'] || (!@$repo['data']->status && empty($rows))){
            return self::error([], $this->extractErrorMessage($repo, __('Failed to load cities.', 'kiriminaja-official')));
        }

        return self::success($rows);
    }
    public function getProfile(){
        $cachedProfile = get_transient(self::KIRIOF_PROFILE_CACHE_KEY);
        if (false !== $cachedProfile) {
            return self::success($cachedProfile);
        }

        $repo = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->getProfile();
        if (!@$repo['status'] || !@$repo['data']->status){
            $cachedProfile = get_transient(self::KIRIOF_PROFILE_LAST_SUCCESS_CACHE_KEY);
            if (false !== $cachedProfile) {
                set_transient(self::KIRIOF_PROFILE_CACHE_KEY, $cachedProfile, self::KIRIOF_PROFILE_CACHE_TTL);
                return self::success($cachedProfile);
            }

            return self::error([],@$repo['data']->text ?? 'Failed to load profile');
        }

        $profile = $repo['data']->results;
        set_transient(self::KIRIOF_PROFILE_CACHE_KEY, $profile, self::KIRIOF_PROFILE_CACHE_TTL);
        set_transient(self::KIRIOF_PROFILE_LAST_SUCCESS_CACHE_KEY, $profile, DAY_IN_SECONDS);

        return self::success($profile);
    }

    private function extractListRows($repo){
        if (empty($repo['data'])) {
            return array();
        }

        $data = $repo['data'];

        if (is_array($data)) {
            return $data;
        }

        $candidates = array(
            $data->result ?? null,
            $data->results ?? null,
            $data->datas ?? null,
            $data->data ?? null,
        );

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                return $candidate;
            }

            if ($candidate instanceof \Traversable) {
                return iterator_to_array($candidate);
            }
        }

        return array();
    }

    private function extractErrorMessage($repo, string $fallback): string {
        if (!isset($repo['data'])) {
            return $fallback;
        }

        $data = $repo['data'];
        if (is_string($data) && '' !== trim($data)) {
            return trim($data);
        }

        if (is_object($data)) {
            $candidates = array(
                $data->text ?? null,
                $data->message ?? null,
                $data->error ?? null,
                $data->errors->message ?? null,
                $data->data->message ?? null,
            );

            foreach ( $candidates as $candidate ) {
                if ( is_string( $candidate ) && '' !== trim( $candidate ) ) {
                    return trim( $candidate );
                }
            }

            if (isset($data->success) && false === $data->success) {
                return $fallback;
            }
        }

        return $fallback;
    }
}