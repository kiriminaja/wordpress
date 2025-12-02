<?php

namespace Inc\Services;

use \Inc\Base\BaseService;

class SettingService extends BaseService
{

    public function getIntegrationData()
    {
        try {
            $repo = (new \Inc\Repositories\SettingRepository())->getIntegrationData();
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

            $validate = (new \Inc\Base\Validator())->validateMultiple([[$setupKey, 'setup key', ['required']]]);
            if (!$validate['status']) {
                return self::error([], $validate['msg']);
            }

            //custom url validation when local set to dev kj only development test
            // TODO: Bad development experience since local testing needs to use localhost url
            $setupPayload = [
                'setup_key' => $setupKey,
                'callback_url' => "https://yan.ad/coba-lagi"
            ];

            $repo = (new \Inc\Repositories\KiriminajaApiRepository())->processSetupKey($setupPayload);

            $arrayRepo = (array) $repo;
            $arrayRepoData = (array) $arrayRepo['data'];

            if (!@$arrayRepo['status'] || !@$arrayRepoData['status']) {
                return self::error($arrayRepo, 'Invalid Setup Key');
            }

            /** Storing result to DB*/
            $arrayRepoDataData = (array) $arrayRepoData['result'];
            (new \Inc\Repositories\SettingRepository())->storeIntegrationData([
                'api_key' => sanitize_text_field($arrayRepoDataData['api_key']),
                'oid_prefix' => sanitize_text_field($arrayRepoDataData['oid_prefix']),
                'setup_key' => sanitize_text_field($setupPayload['setup_key']),
                'callback_url' => $setupPayload['callback_url'],
            ]);
        } catch (\Throwable $th) {
            (new \Inc\Base\BaseInit())->logThis('processingSetupKey errr', $th->getMessage());
            return self::error([], $th->getMessage());
        }
        return self::success([]);
    }

    public function disconnectIntegration()
    {
        try {
            $repo = (new \Inc\Repositories\SettingRepository())->disconnectIntegration();
        } catch (\Throwable $th) {
            return self::error([], $th->getMessage());
        }
        return self::success([]);
    }

    public function getOriginData()
    {
        try {
            $repo = (new \Inc\Repositories\SettingRepository())->getOriginData();
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
            $validate = (new \Inc\Base\Validator())->validateMultiple([
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
            (new \Inc\Repositories\SettingRepository())->storeOriginData([
                'origin_name'               =>  sanitize_text_field($payloads['origin_name']),
                'origin_phone'              =>  sanitize_text_field($payloads['origin_phone']),
                'origin_address'            =>  sanitize_text_field($payloads['origin_address']),
                'origin_latitude'           =>  sanitize_text_field($payloads['origin_latitude']),
                'origin_longitude'          =>  sanitize_text_field($payloads['origin_longitude']),
                'origin_sub_district_id'    =>  sanitize_text_field($payloads['origin_sub_district_id']),
                'origin_sub_district_name'  =>  sanitize_text_field($payloads['origin_sub_district_name']),
                'origin_zip_code'            =>  sanitize_text_field($payloads['origin_zip_code']),
                'origin_whitelist_expedition_id'    =>  !empty($payloads['origin_whitelist_expedition_id']) ? implode(',', $payloads['origin_whitelist_expedition_id']) : null,
                'origin_whitelist_expedition_name'  =>  !empty($payloads['origin_whitelist_expedition_name']) ? implode(',', $payloads['origin_whitelist_expedition_name']) : null,
            ]);
        } catch (\Throwable $th) {
            (new \Inc\Base\BaseInit())->logThis('storeOriginData errr', $th->getMessage());
            return self::error([], $th->getMessage());
        }
        return self::success([]);
    }

    public function getCallbackData()
    {
        try {
            $repo = (new \Inc\Repositories\SettingRepository())->getCallbackData();
            (new \Inc\Base\BaseInit())->logThis('$repo', [$repo]);

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
            $validate = (new \Inc\Base\Validator())->validateMultiple([
                [$payloads['callback_url'], 'Link Callback', ['required']],
            ]);
            if (!$validate['status']) {
                return self::error([], $validate['msg']);
            }

            /** Store to KJ*/
            $repo = (new \Inc\Repositories\KiriminajaApiRepository())->setCallback($payloads['callback_url']);
            if (!@$repo['status'] || !@$repo['data']->status) {
                (new \Inc\Base\BaseInit())->logThis('storeCallbackData errr', $repo);
                return self::error([], @$repo['data'] ?? 'Something is wrong');
            }

            /** Storing to DB*/
            (new \Inc\Repositories\SettingRepository())->storeCallbackData([
                'callback_url' => sanitize_text_field($payloads['callback_url']),
            ]);
        } catch (\Throwable $th) {
            (new \Inc\Base\BaseInit())->logThis('storeCallbackData errr', $th->getMessage());
            return self::error([], $th->getMessage());
        }
        return self::success([]);
    }
}
