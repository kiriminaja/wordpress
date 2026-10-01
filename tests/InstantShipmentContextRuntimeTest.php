<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantShipmentContextRuntimeTest extends TestCase {
    private function runFixture(array $input = []): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/instant-shipment-context-runtime.php') . ' ' . escapeshellarg(json_encode($input, JSON_THROW_ON_ERROR)));
        return json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function builds_v62_and_sdk_pricing_with_discounted_physical_items_and_zero_pins(): void {
        $result = $this->runFixture();
        $this->assertTrue($result['ok']);
        $this->assertTrue($result['can_process']);
        $this->assertSame(0, $result['lookups']);
        $context = $result['context'];
        $this->assertSame('Original Store', $context['origin']['name']);
        $this->assertSame(-6.2, $context['origin']['latitude']);
        $this->assertSame(0, $context['package']['destination']['latitude']);
        $this->assertSame('TEST-000123', $context['package']['order_id']);
        $this->assertSame(15000, $context['package']['shipping_cost']);
        $this->assertSame(7, $context['package']['package_type_id']);
        $item = $context['package']['items'][0];
        $this->assertSame(6173, $item['price']);
        $this->assertSame(1500, $item['weight']);
        $this->assertSame(20, $item['width']);
        $this->assertSame(10, $item['height']);
        $this->assertSame(30, $item['length']);
        $this->assertSame(2, $item['qty']);
        $this->assertSame('SKU-1', $item['metadata']['sku']);
        $this->assertSame(12346, $context['pricing']['item_price']);
        $this->assertSame(3000, $context['pricing']['weight']);
        $this->assertSame(['gosend'], $context['pricing']['service']);
        $this->assertSame('WIB', $context['pricing']['timezone']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $context['fingerprint']);
        $this->assertSame($context['fingerprint'], $this->runFixture()['context']['fingerprint']);
        foreach ([['total'=>9000], ['qty'=>3], ['address'=>['phone'=>'081234560000']], ['row'=>['destination_latitude'=>1]], ['origin'=>['origin_latitude'=>-6.3]]] as $change) {
            $this->assertNotSame($context['fingerprint'], $this->runFixture($change)['context']['fingerprint']);
        }
    }

    #[Test]
    public function blocks_duplicates_and_unsupported_couriers_in_the_quick_guard(): void {
        foreach ([['service'=>'borzo'], ['service'=>'jne'], ['status'=>'shipped'], ['awb'=>'ABC'], ['payment_id'=>'pay'], ['instant_payment_id'=>'pay'], ['instant_status_code'=>0], ['instant_status_code'=>100]] as $row) {
            $result = $this->runFixture(['row'=>$row]);
            $this->assertFalse($result['ok'], json_encode($row));
            $this->assertFalse($result['can_process']);
        }
        $this->assertTrue($this->runFixture(['row'=>['service'=>'grab_express', 'delivery_type'=>'express']])['ok']);
    }

    #[Test]
    public function validates_current_order_service_items_and_coordinates_before_dispatch(): void {
        foreach ([['missing_order'=>true], ['status'=>'completed'], ['status'=>'cancelled'], ['status'=>'pending', 'paid'=>false], ['payment'=>'cod'], ['disabled'=>true], ['row'=>['order_id'=>'']], ['row'=>['order_id'=>'bad id']], ['row'=>['wp_wc_order_stat_order_id'=>0]], ['row'=>['vehicle'=>'mobil']], ['row'=>['service_name'=>'']], ['row'=>['cod'=>10]], ['row'=>['is_cod'=>'1']], ['row'=>['shipping_info'=>'{"destination_latitude":1}']], ['row'=>['destination_latitude'=>91]], ['row'=>['destination_longitude'=>null]], ['row'=>['destination_longitude'=>'NaN']], ['qty'=>0], ['qty'=>1.5], ['weight'=>0], ['weight'=>21], ['dimension'=>0], ['total'=>'bad'], ['missing_product'=>true], ['virtual'=>true], ['no_items'=>true], ['address'=>['first_name'=>'', 'last_name'=>'']], ['address'=>['phone'=>'']], ['address'=>['city'=>'']], ['row'=>['shipping_cost'=>-1]], ['package_type'=>0]] as $input) {
            $result = $this->runFixture($input);
            $this->assertFalse($result['ok'], json_encode($input));
            $this->assertNotEmpty($result['error']);
        }
        $this->assertTrue($this->runFixture(['status'=>'processing', 'paid'=>false])['ok']);
        $this->assertSame(0, $this->runFixture(['total'=>-5])['context']['pricing']['item_price']);
        $this->assertTrue($this->runFixture(['weight'=>20])['ok']);
    }

    #[Test]
    public function never_repairs_a_present_incomplete_origin_snapshot_from_live_configuration(): void {
        foreach (['{}', 'null', 'invalid', '{"origin_name":"Old Store"}'] as $snapshot) {
            $result = $this->runFixture(['row'=>['shipment_location_snapshot'=>$snapshot]]);
            $this->assertFalse($result['ok']);
            $this->assertSame(0, $result['lookups']);
        }
        foreach (['origin_latitude'=>'', 'origin_longitude'=>'', 'origin_phone'=>'', 'origin_zip_code'=>''] as $key=>$value) {
            $result = $this->runFixture(['origin'=>[$key=>$value]]);
            $this->assertFalse($result['ok']);
            $this->assertSame(0, $result['lookups']);
        }
        $live = $this->runFixture(['row'=>['shipment_location_snapshot'=>null]]);
        $this->assertTrue($live['ok']);
        $this->assertSame(1, $live['lookups']);
        $this->assertSame(-7, $live['context']['origin']['latitude']);
        $this->assertTrue($this->runFixture(['origin'=>['origin_latitude'=>0, 'origin_longitude'=>0]])['ok']);
    }

    #[Test]
    public function stale_destination_addresses_fail_and_timezones_are_never_guessed(): void {
        foreach ([['address_1'=>'Changed street'], ['address_2'=>''], ['postcode'=>'54321'], ['city'=>'Bandung'], ['state'=>'JB'], ['country'=>'SG'], ['country'=>'']] as $address) {
            $this->assertFalse($this->runFixture(['address'=>$address])['ok']);
        }
        foreach (['Asia/Jakarta'=>'WIB', 'Asia/Pontianak'=>'WIB', 'Asia/Makassar'=>'WITA', 'Asia/Jayapura'=>'WIT'] as $zone=>$expected) {
            $this->assertSame($expected, $this->runFixture(['zone'=>$zone])['context']['pricing']['timezone']);
        }
        foreach (['UTC', '+07:00', 'Asia/Singapore'] as $zone) {
            $this->assertFalse($this->runFixture(['zone'=>$zone])['ok']);
        }
        $this->assertSame('WIT', $this->runFixture(['zone'=>'UTC', 'origin'=>['timezone'=>'WIT']])['context']['pricing']['timezone']);
        $this->assertFalse($this->runFixture(['origin'=>['timezone'=>'unknown']])['ok']);
        $this->assertFalse($this->runFixture(['row'=>['shipping_info'=>'{}']])['ok']);
    }

    #[Test]
    public function persisted_cod_and_insurance_cannot_be_hidden_by_a_changed_payment_method(): void {
        foreach (['cod_fee', 'insurance_cost'] as $field) {
            foreach ([1, '1500.00'] as $amount) {
                $result = $this->runFixture(['payment'=>'bacs', 'row'=>[$field=>$amount]]);
                $this->assertFalse($result['ok']);
                $this->assertStringContainsString('cod_fee' === $field ? 'Cash on delivery' : 'Insurance', $result['error']);
            }
        }
        $this->assertTrue($this->runFixture(['row'=>['cod_fee'=>0, 'insurance_cost'=>0, 'is_deficit'=>1]])['ok']);
    }

    #[Test]
    public function requires_a_valid_saved_street_associated_with_the_current_address(): void {
        foreach ([null, '', 'invalid', 'null', '[]', '{}', '"street"', '{', '{"_shipping_postcode":"12345"}', '{"_shipping_address_1":""}', '{"_shipping_address_1":{}}'] as $snapshot) {
            $result = $this->runFixture(['row'=>['shipping_info'=>$snapshot]]);
            $this->assertFalse($result['ok'], json_encode($snapshot));
            $this->assertStringContainsString('saved recipient address', $result['error']);
        }
        // Legacy billing snapshots need a matching street, not newly invented fields.
        foreach (['_billing_address_1', 'billing_address_1', 'address_1', '_shipping_address_1'] as $key) {
            $snapshot = json_encode([$key=>'Jalan Sudirman Number 123']);
            $this->assertTrue($this->runFixture(['row'=>['shipping_info'=>$snapshot]])['ok']);
            $this->assertFalse($this->runFixture(['row'=>['shipping_info'=>$snapshot], 'address'=>['address_1'=>'Other street']])['ok']);
            $this->assertFalse($this->runFixture(['row'=>['shipping_info'=>$snapshot], 'address'=>['country'=>'SG']])['ok']);
        }
        $this->assertFalse($this->runFixture(['snapshot'=>['_shipping_country'=>'SG']])['ok']);
    }

    #[Test]
    public function saved_coordinates_must_match_including_valid_zero_pins(): void {
        foreach (['destination_', '_kiriof_destination_', 'kiriof_destination_'] as $prefix) {
            $this->assertTrue($this->runFixture(['snapshot'=>[$prefix . 'latitude'=>0, $prefix . 'longitude'=>'0']])['ok']);
            foreach (['latitude', 'longitude'] as $axis) {
                $result = $this->runFixture(['snapshot'=>[$prefix . $axis=>1]]);
                $this->assertFalse($result['ok']);
                $this->assertStringContainsString('saved destination coordinates', $result['error']);
            }
        }
    }
}
