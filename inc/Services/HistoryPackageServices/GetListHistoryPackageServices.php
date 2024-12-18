<?php 

namespace Inc\Services\HistoryPackageServices;

use \Inc\Base\BaseService;

class GetListHistoryPackageServices extends BaseService
{
    private string $search_value;
    private int $start;
    private int $length;
    private int $draw;
    private string $status;
    private $advancedsearch;

    public function __construct($payloads) {
        $this->search_value = $payloads['search_value'] ?? '';
        $this->start = $payloads['start'] ?? 0;
        $this->length = $payloads['length'] ?? -1;
        $this->draw = $payloads['draw'] ?? 1;
        $this->status = $payloads['status'] ?? 'all';
        $this->advancedsearch = $payloads['advancedsearch']?? [];
        return $this;
    }

    public function call(){
        try {
            $query = (new \Inc\Repositories\TransactionRepository())->getHistoryPackageDatatable([
                'search_value' => $this->search_value,
                'start' => $this->start,
                'length' => $this->length,
                'status' => $this->status,
                'advancedsearch' => $this->advancedsearch,
            ]);
            
            /** get setting origin wooocmmerce KA */
            $getSetting = self::getSettingOrigin();
    
            $data = array();
            foreach ($query['results'] as $row) {
    
                /** get woocommerce order */
                $order = wc_get_order($row['wp_wc_order_stat_order_id']);
                
                /** shipping method name and cost */
                $shipping_methods = $order->get_shipping_methods();
    
                $shipping_method = ''; $shipping_cost = 0;
                foreach ( $shipping_methods as $method ) {
                    $shipping_method .= $method->get_name();
                    $shipping_cost += (float)$method->get_total();
                }
    
                /** decode Shipping Info */
                $decode_shipping_info = json_decode( $row['shipping_info'] );               
                $billing_name = isset( $decode_shipping_info->_billing_first_name,$decode_shipping_info->_billing_last_name ) ? $decode_shipping_info->_billing_first_name.' '.$decode_shipping_info->_billing_last_name : '';
                $shipping_name = isset( $decode_shipping_info->_shipping_first_name,$decode_shipping_info->_shipping_last_name ) ? $decode_shipping_info->_shipping_first_name.' '.$decode_shipping_info->_shipping_last_name : '';
                $destination_phone = isset( $decode_shipping_info->_billing_phone ) ? $decode_shipping_info->_billing_phone : 0;
                $shipping_name_destination = $shipping_name ?? $billing_name; 
    
                /** dimension unit woocommerce */
                $row['dimension_unit'] = get_option('woocommerce_dimension_unit');
                
                $row['products_name'] = self::getProductsName( $order->get_items() );
                $row['subtotal_order'] = $order->get_subtotal();
    
                $row['payment_method'] = $decode_shipping_info->_payment_method ?? '';
                $row['status'] = kjHelper()->getTransactionStatus($row['status']);
                $row['created_at'] = kjHelper()->changeDateFormat( $row['created_at'],'d M Y H:i');
                $row['shipping_method'] = $shipping_method;
                $row['shipping_cost'] = $shipping_cost;
    
                $row['origin_name'] = $getSetting['origin_name'];
                $row['origin_phone'] = $getSetting['origin_phone'];
                $row['origin_sub_district_name'] = $getSetting['origin_sub_district_name'];
                
                $row['destination_name'] = $shipping_name_destination;
                $row['destination_phone'] = $shipping_name_destination;
    
                $data[] = $row; 
            
            }
            
            return [
                'draw' => $this->draw,
                'recordsTotal' => (int)$query['total_count'],
                'recordsFiltered' => (int)$query['total_count'],
                'data' => $data
            ];
        } catch (\Throwable $th) {
            return [
                'error' => $th->getMessage()
            ];
        }

    }

    private function getSettingOrigin(){
        $origin_name = (new \Inc\Repositories\SettingRepository())->getSettingByKey('origin_name')->value;
        $origin_phone = (new \Inc\Repositories\SettingRepository())->getSettingByKey('origin_phone')->value;
        $origin_sub_district_name = (new \Inc\Repositories\SettingRepository())->getSettingByKey('origin_sub_district_name')->value;
        
        return compact(
            'origin_name', 
            'origin_phone', 
            'origin_sub_district_name'
        );
    }

    private function getProductsName( array $order_items ){
        $product_names = array();
        if( $order_items ){
            foreach( $order_items as $item_id => $item ){
                $product = $item->get_product();
                if ($product) {
                    $product_names[] = $product->get_name();
                }
            }
        }
        return implode(", ", $product_names);
    }
}
?>