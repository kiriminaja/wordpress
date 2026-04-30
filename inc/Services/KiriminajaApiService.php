<?php
namespace KiriminAjaOfficial\Services;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use \KiriminAjaOfficial\Base\BaseService;
use KiriminAjaOfficial\Init;
class KiriminajaApiService extends BaseService{
    public function sub_district_search($search)
    {
        $repo = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->sub_district_search($search);
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$repo',[$repo]);
        if (empty($repo['status'])){
            $errorMsg = isset($repo['data']) && is_object($repo['data']) ? ($repo['data']->text ?? '') : ($repo['data'] ?? '');
            return self::error([], $errorMsg ?: 'Something is wrong');
        }
        return self::success($repo['data']);
    }
    public function getPayment($payment_id)
    {
        $repo = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->getPayment([
            'payment_id'=>$payment_id
        ]);
        if (empty($repo['status'])){
            $errorMsg = isset($repo['data']) && is_object($repo['data']) ? ($repo['data']->text ?? '') : ($repo['data'] ?? '');
            return self::error([], $errorMsg ?: 'Something is wrong');
        }
        return self::success($repo['data']);
    }
    public function getTracking($order_id)
    {
        $repo = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->getTracking([
            'order_id'=>$order_id
        ]);
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$repo',[$repo]);
                
        if (empty($repo['status'])){
            $errorMsg = isset($repo['data']) && is_object($repo['data']) ? ($repo['data']->text ?? '') : ($repo['data'] ?? '');
            return self::error([], $errorMsg ?: 'Something is wrong');
        }
        return self::success($repo['data']);
    }
    public function get_couriers(){
        $repo = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->get_couriers();
        if (empty($repo['status'])){
            $errorMsg = isset($repo['data']) && is_object($repo['data']) ? ($repo['data']->text ?? '') : ($repo['data'] ?? '');
            return self::error([], $errorMsg ?: 'Something is wrong');
        }
        return self::success($repo['data']);
    }
}