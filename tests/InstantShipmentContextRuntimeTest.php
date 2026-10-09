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
        $this->assertSame(
            [
                'ok' => true,
                'can_process' => true,
                'lookups' => 0,
            ],
            [
                'ok' => $result['ok'],
                'can_process' => $result['can_process'],
                'lookups' => $result['lookups'],
            ],
            __FUNCTION__
        );
        $context = $result['context'];
        $this->assertSame(
            [
                'context.origin.name' => 'Original Store',
                'context.origin.latitude' => 0,
                'context.package.destination.latitude' => 0,
                'context.package.order_id' => 'TEST-000123',
                'context.package.shipping_cost' => 15000,
                'context.package.package_type_id' => 7,
            ],
            [
                'context.origin.name' => $context['origin']['name'],
                'context.origin.latitude' => $context['origin']['latitude'],
                'context.package.destination.latitude' => $context['package']['destination']['latitude'],
                'context.package.order_id' => $context['package']['order_id'],
                'context.package.shipping_cost' => $context['package']['shipping_cost'],
                'context.package.package_type_id' => $context['package']['package_type_id'],
            ],
            __FUNCTION__
        );
        $item = $context['package']['items'][0];
        $this->assertSame(
            [
                'item.price' => 6173,
                'item.weight' => 1500,
                'item.width' => 20,
                'item.height' => 10,
                'item.length' => 30,
                'item.qty' => 2,
                'item.metadata.sku' => 'SKU-1',
                'context.pricing.item_price' => 12346,
                'context.pricing.weight' => 3000,
                'context.pricing.service' => ['gosend'],
                'context.pricing.timezone' => 'WIB',
                'format: \'/^.a-f0-9{64}$/\', context.fingerprint' => 1,
                'fixture.context.fingerprint' => $context['fingerprint'],
            ],
            [
                'item.price' => $item['price'],
                'item.weight' => $item['weight'],
                'item.width' => $item['width'],
                'item.height' => $item['height'],
                'item.length' => $item['length'],
                'item.qty' => $item['qty'],
                'item.metadata.sku' => $item['metadata']['sku'],
                'context.pricing.item_price' => $context['pricing']['item_price'],
                'context.pricing.weight' => $context['pricing']['weight'],
                'context.pricing.service' => $context['pricing']['service'],
                'context.pricing.timezone' => $context['pricing']['timezone'],
                'format: \'/^.a-f0-9{64}$/\', context.fingerprint' => preg_match('/^[a-f0-9]{64}$/', $context['fingerprint']),
                'fixture.context.fingerprint' => $this->runFixture()['context']['fingerprint'],
            ],
            __FUNCTION__
        );
        foreach ([['total'=>9000], ['qty'=>3], ['address'=>['phone'=>'081234560000']], ['row'=>['destination_latitude'=>0.1]], ['origin'=>['origin_latitude'=>0.2]]] as $change) {
            $this->assertNotSame($context['fingerprint'], $this->runFixture($change)['context']['fingerprint']);
        }
    }

    #[Test]
    public function includes_nonempty_origin_and_destination_notes_without_changing_full_addresses(): void {
        $result = $this->runFixture(['origin'=>['origin_address_note'=>'  Ask the pickup guard  ', 'origin_address_2'=>'Building B']]);
        $this->assertTrue($result['ok']);
        $context = $result['context'];
        $this->assertSame(
            [
                'context.origin.address_note' => 'Ask the pickup guard',
                'context.origin.address' => 'Jalan Original Pickup Number 123, Building B',
                'context.package.destination.address_note' => 'Tower A',
                'context.package.destination.address' => 'Jalan Sudirman Number 123, Tower A, Jakarta, DKI, 12345',
                'context.pricing.origin.address' => $context['origin']['address'],
                'context.pricing.destination.address' => $context['package']['destination']['address'],
            ],
            [
                'context.origin.address_note' => $context['origin']['address_note'],
                'context.origin.address' => $context['origin']['address'],
                'context.package.destination.address_note' => $context['package']['destination']['address_note'],
                'context.package.destination.address' => $context['package']['destination']['address'],
                'context.pricing.origin.address' => $context['pricing']['origin']['address'],
                'context.pricing.destination.address' => $context['pricing']['destination']['address'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function origin_note_aliases_use_the_first_nonempty_scalar_value(): void {
        $keys = ['origin_address_note', 'address_note', 'origin_address_2', 'address_2'];
        foreach ($keys as $index=>$key) {
            $origin = array_fill_keys($keys, ' ');
            $origin[$key] = '  Pickup entrance  ';
            foreach (array_slice($keys, $index + 1) as $lower_priority) {
                $origin[$lower_priority] = 'Other entrance';
            }
            $result = $this->runFixture(['origin'=>$origin]);
            $this->assertSame(
                [
                    'ok' => true,
                    'context.origin.address_note' => 'Pickup entrance',
                ],
                [
                    'ok' => $result['ok'],
                    'context.origin.address_note' => $result['context']['origin']['address_note'],
                ],
                $key
            );
        }
    }

    #[Test]
    public function empty_notes_fall_back_to_the_full_validated_addresses(): void {
        $result = $this->runFixture([
            'origin'=>['origin_address_note'=>' ', 'address_note'=>'', 'origin_address_2'=>'', 'address_2'=>''],
            'address'=>['address_2'=>''],
            'snapshot'=>['_shipping_address_2'=>''],
        ]);
        $this->assertTrue($result['ok']);
        foreach ([$result['context']['origin'], $result['context']['package']['destination']] as $address) {
            $this->assertIsString($address['address_note']);
            $this->assertSame(
                [
                    '(\'\' === address.address_note)' => false,
                    'address.address_note' => $address['address'],
                ],
                [
                    '(\'\' === address.address_note)' => ('' === $address['address_note']),
                    'address.address_note' => $address['address_note'],
                ],
                __FUNCTION__
            );
        }
        $default = $this->runFixture()['context'];
        $this->assertSame($default['origin']['address'], $default['origin']['address_note']);
    }

    #[Test]
    public function historical_origin_notes_are_authoritative_and_do_not_change_pricing(): void {
        $baseline = $this->runFixture()['context'];
        $input = ['origin'=>['origin_address_note'=>'Historical entrance'], 'live_origin'=>['origin_address_note'=>'New entrance']];
        $result = $this->runFixture($input);
        $this->assertSame(
            [
                'ok' => true,
                'lookups' => 0,
                'context.origin.address_note' => 'Historical entrance',
                'context.pricing' => $baseline['pricing'],
                '(baseline.fingerprint === result.context.fingerprint)' => false,
            ],
            [
                'ok' => $result['ok'],
                'lookups' => $result['lookups'],
                'context.origin.address_note' => $result['context']['origin']['address_note'],
                'context.pricing' => $result['context']['pricing'],
                '(baseline.fingerprint === result.context.fingerprint)' => ($baseline['fingerprint'] === $result['context']['fingerprint']),
            ],
            __FUNCTION__
        );
        $input['row'] = ['shipment_location_snapshot'=>null];
        $this->assertSame('New entrance', $this->runFixture($input)['context']['origin']['address_note']);
        $empty = $this->runFixture(['live_origin'=>['origin_address_note'=>'New entrance']]);
        $this->assertSame($empty['context']['origin']['address'], $empty['context']['origin']['address_note']);
    }

    #[Test]
    public function rejects_malformed_origin_notes_with_a_fixed_validation_message(): void {
        foreach (['origin_address_note', 'address_note', 'origin_address_2', 'address_2'] as $key) {
            foreach ([[], ['private'=>'Do not expose'], (object) ['private'=>'Do not expose']] as $note) {
                $result = $this->runFixture(['origin'=>[$key=>$note]]);
                $this->assertSame(
                    [
                        'ok' => false,
                        'error' => 'The shipment origin name, phone, address or postcode is invalid.',
                        'lookups' => 0,
                    ],
                    [
                        'ok' => $result['ok'],
                        'error' => $result['error'],
                        'lookups' => $result['lookups'],
                    ],
                    $key
                );
            }
        }
    }

    #[Test]
    public function address_notes_do_not_relax_address_limits_or_destination_pin_binding(): void {
        foreach ([['origin_address'=>'House of KKK', 'origin_address_note'=>'A sufficiently long pickup instruction'], ['origin_name'=>'Short', 'origin_address_note'=>'Pickup entrance']] as $origin) {
            $result = $this->runFixture(['origin'=>$origin]);
            $this->assertSame(
                [
                    'ok' => false,
                    'error' => 'The shipment origin name, phone, address or postcode is invalid.',
                ],
                [
                    'ok' => $result['ok'],
                    'error' => $result['error'],
                ],
                __FUNCTION__
            );
        }
        $result = $this->runFixture(['address'=>['address_2'=>'New tower']]);
        $this->assertSame(
            [
                'ok' => false,
                'error' => 'The recipient address has changed. Update the destination coordinates before processing.',
            ],
            [
                'ok' => $result['ok'],
                'error' => $result['error'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function blocks_duplicates_and_unsupported_couriers_in_the_quick_guard(): void {
        foreach ([['service'=>'borzo'], ['service'=>'jne'], ['status'=>'shipped'], ['awb'=>'ABC'], ['payment_id'=>'pay'], ['instant_payment_id'=>'pay'], ['instant_status_code'=>0], ['instant_status_code'=>100]] as $row) {
            $result = $this->runFixture(['row'=>$row]);
            $this->assertSame(
                [
                    'ok' => false,
                    'can_process' => false,
                ],
                [
                    'ok' => $result['ok'],
                    'can_process' => $result['can_process'],
                ],
                json_encode($row)
            );
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
        $this->assertSame(
            [
                'fixture.ok' => true,
                'fixture.context.pricing.item_price' => 0,
                'fixture.ok' => true,
            ],
            [
                'fixture.ok' => $this->runFixture(['status'=>'processing', 'paid'=>false])['ok'],
                'fixture.context.pricing.item_price' => $this->runFixture(['total'=>-5])['context']['pricing']['item_price'],
                'fixture.ok' => $this->runFixture(['weight'=>20])['ok'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function never_repairs_a_present_incomplete_origin_snapshot_from_live_configuration(): void {
        foreach (['{}', 'null', 'invalid', '{"origin_name":"Old Store"}'] as $snapshot) {
            $result = $this->runFixture(['row'=>['shipment_location_snapshot'=>$snapshot]]);
            $this->assertSame(
                [
                    'ok' => false,
                    'lookups' => 0,
                ],
                [
                    'ok' => $result['ok'],
                    'lookups' => $result['lookups'],
                ],
                __FUNCTION__
            );
        }
        foreach (['origin_latitude'=>'', 'origin_longitude'=>'', 'origin_phone'=>'', 'origin_zip_code'=>''] as $key=>$value) {
            $result = $this->runFixture(['origin'=>[$key=>$value]]);
            $this->assertSame(
                [
                    'ok' => false,
                    'lookups' => 0,
                ],
                [
                    'ok' => $result['ok'],
                    'lookups' => $result['lookups'],
                ],
                __FUNCTION__
            );
        }
        $live = $this->runFixture(['row'=>['shipment_location_snapshot'=>null]]);
        $this->assertSame(
            [
                'live.ok' => true,
                'live.lookups' => 1,
                'live.context.origin.latitude' => 0.1,
                'fixture.ok' => true,
            ],
            [
                'live.ok' => $live['ok'],
                'live.lookups' => $live['lookups'],
                'live.context.origin.latitude' => $live['context']['origin']['latitude'],
                'fixture.ok' => $this->runFixture(['origin'=>['origin_latitude'=>0, 'origin_longitude'=>0]])['ok'],
            ],
            __FUNCTION__
        );
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
        $this->assertSame(
            [
                'fixture.context.pricing.timezone' => 'WIT',
                'fixture.ok' => false,
                'fixture.ok' => false,
            ],
            [
                'fixture.context.pricing.timezone' => $this->runFixture(['zone'=>'UTC', 'origin'=>['timezone'=>'WIT']])['context']['pricing']['timezone'],
                'fixture.ok' => $this->runFixture(['origin'=>['timezone'=>'unknown']])['ok'],
                'fixture.ok' => $this->runFixture(['row'=>['shipping_info'=>'{}']])['ok'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function persisted_cod_and_insurance_cannot_be_hidden_by_a_changed_payment_method(): void {
        foreach (['cod_fee', 'insurance_cost'] as $field) {
            foreach ([1, '1500.00'] as $amount) {
                $result = $this->runFixture(['payment'=>'bacs', 'row'=>[$field=>$amount]]);
                $this->assertSame(
                    [
                        'ok' => false,
                        'message fragment: result.error' => 'cod_fee' === $field ? 'Cash on delivery' : 'Insurance',
                    ],
                    [
                        'ok' => $result['ok'],
                        'message fragment: result.error' => substr($result['error'], strpos($result['error'], 'cod_fee' === $field ? 'Cash on delivery' : 'Insurance') === false ? strlen($result['error']) : strpos($result['error'], 'cod_fee' === $field ? 'Cash on delivery' : 'Insurance'), strlen('cod_fee' === $field ? 'Cash on delivery' : 'Insurance')),
                    ],
                    __FUNCTION__
                );
            }
        }
        $this->assertTrue($this->runFixture(['row'=>['cod_fee'=>0, 'insurance_cost'=>0, 'is_deficit'=>1]])['ok']);
    }

    #[Test]
    public function requires_a_valid_saved_street_associated_with_the_current_address(): void {
        foreach ([null, '', 'invalid', 'null', '[]', '{}', '"street"', '{', '{"_shipping_postcode":"12345"}', '{"_shipping_address_1":""}', '{"_shipping_address_1":{}}'] as $snapshot) {
            $result = $this->runFixture(['row'=>['shipping_info'=>$snapshot]]);
            $this->assertSame(
                [
                    'ok' => false,
                    'message fragment: result.error' => 'saved recipient address',
                ],
                [
                    'ok' => $result['ok'],
                    'message fragment: result.error' => substr($result['error'], strpos($result['error'], 'saved recipient address') === false ? strlen($result['error']) : strpos($result['error'], 'saved recipient address'), strlen('saved recipient address')),
                ],
                json_encode($snapshot)
            );
        }
        // Legacy billing snapshots need a matching street, not newly invented fields.
        foreach (['_billing_address_1', 'billing_address_1', 'address_1', '_shipping_address_1'] as $key) {
            $snapshot = json_encode([$key=>'Jalan Sudirman Number 123']);
            $this->assertSame(
                [
                    'fixture.ok' => true,
                    'fixture.ok' => false,
                    'fixture.ok' => false,
                ],
                [
                    'fixture.ok' => $this->runFixture(['row'=>['shipping_info'=>$snapshot]])['ok'],
                    'fixture.ok' => $this->runFixture(['row'=>['shipping_info'=>$snapshot], 'address'=>['address_1'=>'Other street']])['ok'],
                    'fixture.ok' => $this->runFixture(['row'=>['shipping_info'=>$snapshot], 'address'=>['country'=>'SG']])['ok'],
                ],
                __FUNCTION__
            );
        }
        $this->assertFalse($this->runFixture(['snapshot'=>['_shipping_country'=>'SG']])['ok']);
    }

    #[Test]
    public function radius_uses_the_actual_historical_pickup_and_rejects_outside_destinations(): void {
        foreach ([['row'=>['destination_latitude'=>0.36]], ['origin'=>['origin_latitude'=>0.36]], ['origin'=>['origin_longitude'=>106.8]]] as $input) {
            $r = $this->runFixture($input);
            $this->assertSame(
                [
                    'ok' => false,
                    'error' => 'Instant delivery is available only within 40 km of the pickup origin. You can use Express delivery for this address.',
                    'lookups' => 0,
                ],
                [
                    'ok' => $r['ok'],
                    'error' => $r['error'],
                    'lookups' => $r['lookups'],
                ],
                __FUNCTION__
            );
        }
        $this->assertTrue($this->runFixture(['row'=>['destination_latitude'=>0.359]])['ok']);
        $r = $this->runFixture(['dispatch_review'=>true, 'row'=>['destination_latitude'=>0.36]]);
        $this->assertSame(
            [
                'review.rows.0.eligible' => false,
                'prices' => 0,
                'books' => 0,
                'message fragment: r.review.rows.0.error' => '40 km',
            ],
            [
                'review.rows.0.eligible' => $r['review']['rows'][0]['eligible'],
                'prices' => $r['prices'],
                'books' => $r['books'],
                'message fragment: r.review.rows.0.error' => substr($r['review']['rows'][0]['error'], strpos($r['review']['rows'][0]['error'], '40 km') === false ? strlen($r['review']['rows'][0]['error']) : strpos($r['review']['rows'][0]['error'], '40 km'), strlen('40 km')),
            ],
            __FUNCTION__
        );
        $this->assertNotEmpty($r['error']);
        foreach (['1e-2', ' 0', true, [], INF] as $coordinate) {
            if (is_float($coordinate) && !is_finite($coordinate)) { continue; }
            $this->assertFalse($this->runFixture(['row'=>['destination_latitude'=>$coordinate]])['ok']);
        }
    }

    #[Test]
    public function saved_coordinates_must_match_including_valid_zero_pins(): void {
        foreach (['destination_', '_kiriof_destination_', 'kiriof_destination_'] as $prefix) {
            $this->assertTrue($this->runFixture(['snapshot'=>[$prefix . 'latitude'=>0, $prefix . 'longitude'=>'0']])['ok']);
            foreach (['latitude', 'longitude'] as $axis) {
                $result = $this->runFixture(['snapshot'=>[$prefix . $axis=>1]]);
                $this->assertSame(
                    [
                        'ok' => false,
                        'message fragment: result.error' => 'saved destination coordinates',
                    ],
                    [
                        'ok' => $result['ok'],
                        'message fragment: result.error' => substr($result['error'], strpos($result['error'], 'saved destination coordinates') === false ? strlen($result['error']) : strpos($result['error'], 'saved destination coordinates'), strlen('saved destination coordinates')),
                    ],
                    __FUNCTION__
                );
            }
        }
    }
}
