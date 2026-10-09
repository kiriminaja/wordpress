<?php
class RecipientCustomer {
    public array $shipping = [];
    public array $billing = [];
    public function __call($method, $args) {
        if (str_starts_with($method, 'get_shipping_')) { return $this->shipping[substr($method, 13)] ?? ''; }
        if (str_starts_with($method, 'get_billing_')) { return $this->billing[substr($method, 12)] ?? ''; }
    }
}
$customer = new RecipientCustomer();
$customer->billing = $address + ['first_name' => 'Buyer', 'last_name' => 'Full Name', 'phone' => '081234567890'];
$customer->shipping = $address;
$package['destination'] = $address;
switch ($scenario) {
    case 'shipping_names': $customer->shipping += ['first_name' => 'Shipping', 'last_name' => 'Recipient']; $customer->billing['city'] = 'Bandung'; break;
    case 'other_address': $customer->billing['city'] = 'Bandung'; break;
    case 'empty_phone': $package['destination']['phone'] = ''; break;
    case 'array_phone': $package['destination']['phone'] = ['081234567890']; break;
    case 'number_phone': $package['destination']['phone'] = 81234567890; break;
    case 'empty_names': $package['destination'] += ['first_name' => '', 'last_name' => '']; break;
    case 'package_names': $package['destination'] += ['first_name' => 'Actual', 'last_name' => 'Recipient']; break;
    case 'package_empty_city': $package['destination']['city'] = ''; break;
    case 'package_array_city': $package['destination']['city'] = []; break;
    case 'no_phone_getter':
        $customer = new class($customer->billing) {
            public function __construct(private array $billing) {}
            public function get_billing_phone() { return $this->billing['phone']; }
            public function get_shipping_first_name() { return 'Buyer'; }
            public function get_shipping_last_name() { return 'Full Name'; }
        };
        break;
    case 'zero_coordinates': $destination['destination_latitude'] = '0'; $destination['destination_longitude'] = '0'; break;
    case 'cod': $payment = 'cod'; break;
}
$package['destination'] = \KiriminAjaOfficial\Services\InstantCheckoutRecipient::resolve($package, $customer);
