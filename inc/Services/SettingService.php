<?php

namespace Inc\Services;

use \Inc\Base\BaseService;

class SettingService extends BaseService{

    public function getIntegrationData()
    {
        try {
            $repo = (new \Inc\Repositories\SettingRepository())->getIntegrationData();
            if (!$repo) {
                return self::error([],'Server Error');
            }
            $response = [];
            foreach ($repo as $repoItem){
                $response[$repoItem->key]=sanitize_text_field($repoItem->value);
            }
        } catch (\Throwable $th){
        return self::error([],$th->getMessage());
        }
        return self::success($response);
    }
    
    public function processingSetupKey($setupKey)
    {
        try {

            $validate = (new \Inc\Base\Validator())->validateMultiple([[$setupKey,'setup key',['required']]]);
            if (!$validate['status']){ return self::error([],$validate['msg']);}
            
            $repo = (new \Inc\Repositories\KiriminajaApiRepository())->processSetupKey($setupKey);
            
            $arrayRepo = (array) $repo;
            $arrayRepoData = (array) $arrayRepo['data'];

            if (!@$arrayRepo['status'] || !@$arrayRepoData['status']){
                return self::error([],@$arrayRepoData['text'] ?? 'Something is wrong');
            }
            
            /** Storing result to DB*/
            $arrayRepoDataData = (array) $arrayRepoData['result'];
            (new \Inc\Repositories\SettingRepository())->storeIntegrationData([
                'api_key'=>sanitize_text_field($arrayRepoDataData['api_key']),
                'oid_prefix'=>sanitize_text_field($arrayRepoDataData['oid_prefix']),
                'setup_key'=>sanitize_text_field($setupKey),
            ]);
            
        } catch (\Throwable $th){
            (new \Inc\Base\BaseInit())->logThis('processingSetupKey errr',$th->getMessage());
            return self::error([],$th->getMessage());
        }
        return self::success([]);
    }
    
    public function getOriginData(){
        try {
            $repo = (new \Inc\Repositories\SettingRepository())->getOriginData();
            if (!$repo) {
                return self::error([],'Server Error');
            }
            $response = [];
            foreach ($repo as $repoItem){
                $response[$repoItem->key]=sanitize_text_field($repoItem->value);
            }
        } catch (\Throwable $th){
            return self::error([],$th->getMessage());
        }
        return self::success($response);
    }
    
    public function storeOriginData(array $payloads){
        try {
            $validate = (new \Inc\Base\Validator())->validateMultiple([
                [$payloads['origin_name'],'Nama Toko / Pengirim',['required']],
                [$payloads['origin_phone'],'No. Hp',['required']],
                [$payloads['origin_address'],'Alamat',['required']],
                [$payloads['origin_sub_district_id'],'Area Pengirim',['required']],
                [$payloads['origin_sub_district_name'],'Area Pengirim',['required']],
            ]);
            if (!$validate['status']){ return self::error([],$validate['msg']);}
            
            /** Storing to DB*/
            (new \Inc\Repositories\SettingRepository())->storeOriginData([
                'origin_name'=>sanitize_text_field($payloads['origin_name']),
                'origin_phone'=>sanitize_text_field($payloads['origin_phone']),
                'origin_address'=>sanitize_text_field($payloads['origin_address']),
                'origin_sub_district_id'=>sanitize_text_field($payloads['origin_sub_district_id']),
                'origin_sub_district_name'=>sanitize_text_field($payloads['origin_sub_district_name']),
            ]);
            
        } catch (\Throwable $th){
            (new \Inc\Base\BaseInit())->logThis('storeOriginData errr',$th->getMessage());
            return self::error([],$th->getMessage());
        }
        return self::success([]);
    }
    
    public function getCallbackData(){
        try {
            $repo = (new \Inc\Repositories\SettingRepository())->getCallbackData();
            if (!$repo) {
                return self::error([],'Server Error');
            }
            $response = [];
            foreach ($repo as $repoItem){
                $response[$repoItem->key]=sanitize_text_field($repoItem->value);
            }
        } catch (\Throwable $th){
            return self::error([],$th->getMessage());
        }
        return self::success($response);
    }

    public function storeCallbackData(array $payloads){
        try {
            $validate = (new \Inc\Base\Validator())->validateMultiple([
                [$payloads['link_callback'],'Link Callback',['required']],
            ]);
            if (!$validate['status']){ return self::error([],$validate['msg']);}
            
            /** Store to KJ*/
            $repo = (new \Inc\Repositories\KiriminajaApiRepository())->setCallback($payloads['link_callback']);
            if (!@$repo['status'] || !@$repo['data']->status){
                (new \Inc\Base\BaseInit())->logThis('storeCallbackData errr',$repo);
                return self::error([],@$repo['data'] ?? 'Something is wrong');
            }

            /** Storing to DB*/
            (new \Inc\Repositories\SettingRepository())->storeCallbackData([
                'link_callback'=>sanitize_text_field($payloads['link_callback']),
            ]);

        } catch (\Throwable $th){
            (new \Inc\Base\BaseInit())->logThis('storeCallbackData errr',$th->getMessage());
            return self::error([],$th->getMessage());
        }
        return self::success([]);
    }

}