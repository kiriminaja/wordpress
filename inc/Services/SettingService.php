<?php
namespace KiriminAjaOfficial\Services;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use \KiriminAjaOfficial\Base\BaseService;
class SettingService extends BaseService
{
    public function getIntegrationData()
    {
        try {
            $repo = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getIntegrationData();
            if (!$repo) {
                return self::error([], 'Server Error');
            }
            $response = [];
            foreach ($repo as $repoItem) {
                $response[$repoItem->key] = sanitize_text_field($repoItem->value);
            }
        } catch (\Throwable $th) {
            return self::error([], $th->getMessage());
        }
        return self::success($response);
    }
    public function processingSetupKey($setupKey)
    {
        try {
            $validate = (new \KiriminAjaOfficial\Base\Validator())->validateMultiple([[$setupKey, 'setup key', ['required']]]);
            if (!$validate['status']) {
                return self::error([], $validate['msg']);
            }
            //custom url validation when local set to dev kj only development test
            $setupPayload = [
                'setup_key' => $setupKey,
                'callback_url' => @home_url() . '/kiriminaja-callback'
            ];
            $repo = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->processSetupKey($setupPayload);
            $arrayRepo = (array) $repo;
            $arrayRepoData = (array) $arrayRepo['data'];
            if (!@$arrayRepo['status'] || !@$arrayRepoData['status']) {
                return self::error([], 'Invalid Setup Key');
            }
            /** Storing result to DB*/
            $arrayRepoDataData = (array) $arrayRepoData['result'];
            (new \KiriminAjaOfficial\Repositories\SettingRepository())->storeIntegrationData([
                'api_key' => sanitize_text_field($arrayRepoDataData['api_key']),
                'oid_prefix' => sanitize_text_field($arrayRepoDataData['oid_prefix']),
                'setup_key' => sanitize_text_field($setupPayload['setup_key']),
                'callback_url' => $setupPayload['callback_url'],
            ]);
        } catch (\Throwable $th) {
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis('processingSetupKey errr', $th->getMessage());
            return self::error([], $th->getMessage());
        }
        return self::success([]);
    }
    public function disconnectIntegration()
    {
        try {
            $repo = (new \KiriminAjaOfficial\Repositories\SettingRepository())->disconnectIntegration();
        } catch (\Throwable $th) {
            return self::error([], $th->getMessage());
        }
        return self::success([]);
    }
    public function getOriginData()
    {
        try {
            $repo = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getOriginData();
            if (!$repo) {
                return self::error([], 'Server Error');
            }
            $response = [];
            foreach ($repo as $repoItem) {
                $response[$repoItem->key] = sanitize_text_field($repoItem->value);
            }
        } catch (\Throwable $th) {
            return self::error([], $th->getMessage());
        }
        return self::success($response);
    }
    public function storeOriginData(array $payloads)
    {
        try {
            $validate = (new \KiriminAjaOfficial\Base\Validator())->validateMultiple([
                [$payloads['origin_name'] ?? '', 'Nama Toko / Pengirim', ['required', 'max:250']],
                [$payloads['origin_phone'] ?? '', 'No. Hp', ['required', 'max:15']],
                [$payloads['origin_address'] ?? '', 'Alamat', ['required', 'max:250']],
                [$payloads['origin_latitude'] ?? '', 'Latitude', ['required', 'max:250']],
                [$payloads['origin_longitude'] ?? '', 'Longitude', ['required', 'max:250']],
                [$payloads['origin_sub_district_id'] ?? '', 'Area Pengirim', ['required']],
                [$payloads['origin_sub_district_name'], 'Area Pengirim', ['required', 'max:250']],
                [$payloads['origin_zip_code'] ?? '', 'Zipcode', ['required', 'max:10']],
                [$payloads['origin_whitelist_expedition_id'], 'Whitelist Expedition', []],
                [$payloads['origin_whitelist_expedition_name'], 'Whitelist Expedition', []],
            ]);
            if (!$validate['status']) {
                return self::error([], $validate['msg']);
            }
            /** Storing to DB*/
            $whitelist_ids = array();
            if ( ! empty( $payloads['origin_whitelist_expedition_id'] ) && is_array( $payloads['origin_whitelist_expedition_id'] ) ) {
                foreach ( $payloads['origin_whitelist_expedition_id'] as $expedition_id ) {
                    $expedition_id = sanitize_key( (string) $expedition_id );
                    if ( '' !== $expedition_id ) {
                        $whitelist_ids[] = $expedition_id;
                    }
                }
            }

            $whitelist_names = array();
            if ( ! empty( $payloads['origin_whitelist_expedition_name'] ) && is_array( $payloads['origin_whitelist_expedition_name'] ) ) {
                foreach ( $payloads['origin_whitelist_expedition_name'] as $expedition_name ) {
                    $expedition_name = sanitize_text_field( (string) $expedition_name );
                    if ( '' !== $expedition_name ) {
                        $whitelist_names[] = $expedition_name;
                    }
                }
            }

            (new \KiriminAjaOfficial\Repositories\SettingRepository())->storeOriginData([
                'origin_name'                       => sanitize_text_field( (string) $payloads['origin_name'] ),
                'origin_phone'                      => sanitize_text_field( (string) $payloads['origin_phone'] ),
                'origin_address'                    => sanitize_textarea_field( (string) $payloads['origin_address'] ),
                'origin_latitude'                   => sanitize_text_field( (string) $payloads['origin_latitude'] ),
                'origin_longitude'                  => sanitize_text_field( (string) $payloads['origin_longitude'] ),
                'origin_sub_district_id'            => sanitize_text_field( (string) $payloads['origin_sub_district_id'] ),
                'origin_sub_district_name'          => sanitize_text_field( (string) $payloads['origin_sub_district_name'] ),
                'origin_zip_code'                   => sanitize_text_field( (string) $payloads['origin_zip_code'] ),
                'origin_whitelist_expedition_id'    => ! empty( $whitelist_ids ) ? implode( ',', $whitelist_ids ) : null,
                'origin_whitelist_expedition_name'  => ! empty( $whitelist_names ) ? implode( ',', $whitelist_names ) : null,
            ]);
        } catch (\Throwable $th) {
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis('storeOriginData errr', $th->getMessage());
            return self::error([], $th->getMessage());
        }
        return self::success([]);
    }
    public function getCallbackData()
    {
        try {
            $repo = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getCallbackData();
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$repo', [$repo]);
            if (!$repo) {
                return self::error([], 'Server Error');
            }
            $response = [];
            foreach ($repo as $repoItem) {
                $response[$repoItem->key] = sanitize_text_field($repoItem->value);
            }
        } catch (\Throwable $th) {
            return self::error([], $th->getMessage());
        }
        return self::success($response);
    }
    public function storeCallbackData(array $payloads)
    {
        try {
            $callback_url = isset( $payloads['callback_url'] ) ? esc_url_raw( $payloads['callback_url'] ) : '';
            $validate = (new \KiriminAjaOfficial\Base\Validator())->validateMultiple([
                [$callback_url, 'Link Callback', ['required']],
            ]);
            if (!$validate['status']) {
                return self::error([], $validate['msg']);
            }
            /** Store to KJ*/
            $repo = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->setCallback($callback_url);
            if (!@$repo['status'] || !@$repo['data']->status) {
                (new \KiriminAjaOfficial\Base\BaseInit())->logThis('storeCallbackData errr', $repo);
                return self::error([], @$repo['data'] ?? 'Something is wrong');
            }
            /** Storing to DB*/
            (new \KiriminAjaOfficial\Repositories\SettingRepository())->storeCallbackData([
                'callback_url' => $callback_url,
            ]);
        } catch (\Throwable $th) {
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis('storeCallbackData errr', $th->getMessage());
            return self::error([], $th->getMessage());
        }
        return self::success([]);
    }
}