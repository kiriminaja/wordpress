<?php
class History
{
    function getCountService($status="all"){
        return (new \Inc\Repositories\TransactionRepository())->getCountTabHistory($status);
    }

    function getExpressEkspedisi(){
        $kiriminajaExpedition = (new \Inc\Services\KiriminajaApiService())->get_couriers();
        $expressEkspedisi = array_filter($kiriminajaExpedition->data, function ($item) {
            return $item->type === "Express";
        });
        return $expressEkspedisi;
    }

    function getShippingAddress(){ 
        $shipping_address = (new \Inc\Repositories\TransactionRepository())->getShippingAddress();
        return $shipping_address;
    }
}
$history = new History();

/** Return vars and view*/
include 'view/index.php';
?>