<?php
namespace KiriminAjaOfficial\Repositories;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\KiriminAjaApi;
use KiriminAja\Services\KiriminAja;
use KiriminAja\Models\ShippingPriceData;
use KiriminAja\Models\RequestPickupData;
use KiriminAja\Models\PackageData;
use KiriminAja\Responses\ServiceResponse;

class KiriminajaApiRepository extends KiriminAjaApi {

    /**
     * Convert SDK ServiceResponse to the legacy array format
     * used by all existing callers: ['status' => bool, 'data' => object]
     *
     * The old API returned a nested structure where callers checked both
     * $result['status'] and $result['data']->status. This adapter
     * reconstructs that shape from the SDK's ServiceResponse.
     *
     * @param ServiceResponse $response
     * @return array{status: bool, data: mixed}
     */
    private function toLegacy(ServiceResponse $response): array
    {
        $data = $response->data;
        // Convert nested arrays to stdClass objects recursively for backward compatibility
        $dataObj = is_array($data) ? json_decode(wp_json_encode($data)) : $data;

        // Wrap in an outer object that includes status/text fields callers expect
        $wrapper = new \stdClass();
        $wrapper->status = $response->status;
        $wrapper->text   = $response->message;

        // Merge data properties into the wrapper object
        if (is_object($dataObj)) {
            foreach (get_object_vars($dataObj) as $key => $value) {
                $wrapper->$key = $value;
            }
        } elseif (!is_null($dataObj)) {
            $wrapper->data = $dataObj;
        }

        return [
            'status' => $response->status,
            'data'   => $wrapper,
        ];
    }

    /**
     * Search sub-districts by name via SDK.
     *
     * @param string $search
     * @return array{status: bool, data: mixed}
     */
    public function sub_district_search($search)
    {
        return $this->toLegacy(KiriminAja::getDistrictByName($search));
    }

    /**
     * Set callback URL via SDK.
     *
     * @param string $callbackUrl
     * @return array{status: bool, data: mixed}
     */
    public function setCallback($callbackUrl)
    {
        return $this->toLegacy(KiriminAja::setCallback($callbackUrl));
    }

    /**
     * Process setup key — no SDK equivalent, uses legacy HTTP client.
     *
     * @param array $payload
     * @return array{status: bool, data: mixed}
     */
    public function processSetupKey($payload){
        return $this->post('/api/service/api-request/integrate',[
            'setup_key'     => $payload['setup_key'],
            'callback_url'  => $payload['callback_url']
        ]);
    }

    /**
     * Get payment details via SDK.
     *
     * @param array $payload ['payment_id' => string]
     * @return array{status: bool, data: mixed}
     */
    public function getPayment($payload)
    {
        return $this->toLegacy(KiriminAja::getPayment($payload['payment_id']));
    }

    /**
     * Get tracking info via SDK.
     *
     * @param array $payload ['order_id' => string]
     * @return array{status: bool, data: mixed}
     */
    public function getTracking($payload)
    {
        return $this->toLegacy(KiriminAja::getTracking($payload['order_id']));
    }

    /**
     * Get shipping price via SDK.
     *
     * @param array $payload
     * @return array{status: bool, data: mixed}
     */
    public function getPricing($payload)
    {
        $data = new ShippingPriceData();
        $data->origin      = (int) $payload['subdistrict_origin'];
        $data->destination  = (int) $payload['subdistrict_destination'];
        $data->weight       = (int) $payload['weight'];
        $data->length       = (int) ($payload['length'] ?? 0);
        $data->width        = (int) ($payload['width'] ?? 0);
        $data->height       = (int) ($payload['height'] ?? 0);
        $data->insurance    = isset($payload['insurance']) ? (int) $payload['insurance'] : null;
        $data->item_value   = isset($payload['item_value']) ? (int) $payload['item_value'] : null;
        $data->courier      = $payload['courier'] ?? null;

        return $this->toLegacy(KiriminAja::getPrice($data));
    }

    /**
     * Get pickup schedules via SDK.
     *
     * @return array{status: bool, data: mixed}
     */
    public function getRequestPickupSchedule()
    {
        return $this->toLegacy(KiriminAja::getSchedules());
    }

    /**
     * Send pickup request via SDK.
     *
     * @param array $payload
     * @return array{status: bool, data: mixed}
     */
    public function sendPickupRequest($payload)
    {
        $data = new RequestPickupData();
        $data->address      = $payload['address'] ?? '';
        $data->phone        = $payload['phone'] ?? '';
        $data->name         = $payload['name'] ?? '';
        $data->zipcode      = $payload['zipcode'] ?? '';
        $data->kecamatan_id = (int) ($payload['kelurahan_id'] ?? 0);
        $data->schedule     = $payload['schedule'] ?? '';
        $data->latitude     = isset($payload['latitude']) ? (float) $payload['latitude'] : null;
        $data->longitude    = isset($payload['longitude']) ? (float) $payload['longitude'] : null;
        $data->platform_name = 'wordpress';

        foreach ($payload['packages'] ?? [] as $pkg) {
            $package = new PackageData();
            $package->order_id               = $pkg['order_id'] ?? '';
            $package->destination_name        = $pkg['destination_name'] ?? '';
            $package->destination_phone       = $pkg['destination_phone'] ?? '';
            $package->destination_address     = $pkg['destination_address'] ?? '';
            $package->destination_kecamatan_id = (int) ($pkg['destination_kecamatan_id'] ?? 0);
            $package->destination_zipcode     = $pkg['destination_zipcode'] ?? '';
            $package->weight                  = (int) ($pkg['weight'] ?? 1);
            $package->width                   = (int) ($pkg['width'] ?? 1);
            $package->length                  = (int) ($pkg['length'] ?? 1);
            $package->height                  = (int) ($pkg['height'] ?? 1);
            $package->qty                     = (int) ($pkg['qty'] ?? 1);
            $package->item_value              = (int) ($pkg['item_value'] ?? 0);
            $package->shipping_cost           = (int) ($pkg['shipping_cost'] ?? 0);
            $package->service                 = $pkg['service'] ?? '';
            $package->service_type            = $pkg['service_type'] ?? '';
            $package->cod                     = (int) ($pkg['cod'] ?? 0);
            $package->package_type_id         = (int) ($pkg['package_type_id'] ?? 1);
            $package->item_name               = $pkg['item_name'] ?? '';
            $package->insurance_amount        = isset($pkg['insurance_amount']) ? (int) $pkg['insurance_amount'] : 0;
            $package->drop                    = (bool) ($pkg['drop'] ?? false);
            $package->note                    = $pkg['note'] ?? '';
            $data->packages->add($package);
        }

        return $this->toLegacy(KiriminAja::requestPickup($data));
    }

    /**
     * Get available couriers via SDK.
     *
     * @return array{status: bool, data: mixed}
     */
    public function get_couriers()
    {
        return $this->toLegacy(KiriminAja::getCouriers());
    }

    /**
     * Print AWB label — no SDK equivalent, uses legacy HTTP client.
     *
     * @param array|string $awb
     * @return array{status: bool, data: mixed}
     */
    public function getPrintAwb($awb){
        return $this->post('/api/mitra/v6.1/awb/print',[
            'awb' => $awb,
        ]);
    }
}