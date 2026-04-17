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
    public function get_couriers(){
        $repo = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->get_couriers();
        if (!@$repo['status'] || !@$repo['data']->status){
            return self::error([],@$repo['data']->text ?? 'Something is wrong');
        }
        return self::success($repo['data']->datas);
    }
}