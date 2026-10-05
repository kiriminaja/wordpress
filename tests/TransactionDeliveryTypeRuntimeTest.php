<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) { define('ABSPATH', PLUGIN_DIR . '/'); }
require_once PLUGIN_DIR . '/inc/Services/TransactionDeliveryType.php';

final class TransactionDeliveryTypeRuntimeTest extends TestCase {
    private function runFixture(array $input): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/transaction-delivery-runtime.php') . ' ' . escapeshellarg(json_encode($input, JSON_THROW_ON_ERROR)));
        return json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function strict_helper_classifies_only_documented_codes_and_exact_partition_values(): void {
        $helper = \KiriminAjaOfficial\Services\TransactionDeliveryType::class;
        foreach (['instant'=>'instant', 'express'=>'express', 'Instant'=>'express', ' instant '=>'express', 'unknown'=>'express'] as $value=>$expected) {
            $this->assertSame($expected, $helper::normalize($value));
        }
        foreach ([null, [], true, 1, (object) []] as $value) { $this->assertSame('express', $helper::normalize($value)); }
        foreach (['gosend','grab_express','borzo',' GOSEND '] as $service) {
            $this->assertSame('instant', $helper::resolve(['service'=>$service,'delivery_type'=>'express']));
            $this->assertSame('instant', $helper::resolve((object) ['service'=>$service]));
        }
        foreach (['gojek','grab','gosend_fake','jne',''] as $service) { $this->assertSame('express', $helper::resolve(['service'=>$service])); }
        $this->assertSame('instant', $helper::resolve(['delivery_type'=>'instant','service'=>'jne']));
        $this->assertSame('express', $helper::resolve(null));
    }

    #[Test]
    public function every_list_and_badge_branch_partitions_legacy_and_hpos_without_losing_pagination(): void {
        foreach ([false,true] as $hpos) {
            foreach (['all','order-issue','processed','wc-cancelled','wc-processing','wc-processing,processed,wc-cancelled'] as $status) {
                foreach (['express','instant'] as $type) {
                    $result = $this->runFixture(['hpos'=>$hpos,'filters'=>['status'=>$status,'delivery_type'=>$type]]);
                    $scope = 'order-issue' === $status ? 'express' : $type;
                    foreach ($result['queries'] as $sql) {
                        $this->assertStringContainsString("delivery_type = '", $sql);
                        if (str_contains($sql, 'is_deficit = 1')) {
                            $this->assertStringContainsString("delivery_type = 'express'", $sql);
                        } else {
                            $this->assertStringContainsString("delivery_type = '$scope'", $sql);
                        }
                    }
                    $sql = implode("\n", $result['queries']);
                    $this->assertStringContainsString($hpos ? 'wp_wc_orders' : 'wp_posts', $sql);
                    if ('instant' === $scope) {
                        $this->assertSame(55, $result['counts']['processed']);
                        $this->assertStringContainsString('instant_status_code IS NOT NULL', $sql);
                        $this->assertStringContainsString("instant_payment_id != ''", $sql);
                        $this->assertStringNotContainsString('kiriminaja_payments', $sql);
                    }
                    $this->assertSame(55, $result['page']['total']);
                    $this->assertSame(3, $result['page']['page']);
                    $this->assertStringContainsString('LIMIT 25 OFFSET 50', $sql);

                }
            }
        }
    }

    #[Test]
    public function instant_payment_identity_filter_is_exact_and_stays_in_instant_partition(): void {
        foreach ([false, true] as $hpos) {
            $result = $this->runFixture(['hpos'=>$hpos, 'filters'=>['delivery_type'=>'instant', 'status'=>'all', 'key'=>"ipid:pay'100%_id"]]);
            $sql = implode("\n", $result['queries']);
            $this->assertStringContainsString("kiriminaja_transactions.instant_payment_id = 'pay''100%_id'", $sql);
            $this->assertStringContainsString("delivery_type = 'instant'", $sql);
            $this->assertStringNotContainsString('pickup_number =', $sql);
        }
    }

    #[Test]
    public function invalid_partition_defaults_express_and_instance_scope_and_caches_reset(): void {
        foreach ([null, [], 'Instant', "instant' OR 1=1"] as $value) {
            $result = $this->runFixture(['filters'=>['delivery_type'=>$value]]);
            foreach ($result['queries'] as $sql) { $this->assertStringContainsString("delivery_type = 'express'", $sql); }
        }
        $result = $this->runFixture(['filters'=>['delivery_type'=>'instant'],'reset'=>true]);
        $this->assertSame(['kiriof_distinct_couriers_instant','kiriof_distinct_couriers_express'], $result['cache_keys']);
        $this->assertStringContainsString("t.delivery_type = 'express'", end($result['queries']));
    }

    #[Test]
    public function repository_persists_inferred_type_and_only_actual_optional_vehicle(): void {
        foreach (['gosend'=>'instant','grab_express'=>'instant','borzo'=>'instant','jne'=>'express'] as $service=>$type) {
            $result = $this->runFixture(['mode'=>'repository','payload'=>['service'=>$service]]);
            $this->assertTrue($result['ok']);
            $this->assertSame($type, $result['prepared'][0][1][7]);
            $this->assertSame('', $result['prepared'][0][1][8]);
            $this->assertStringContainsString("NULLIF(%s, '')", $result['prepared'][0][0]);
            $this->assertContains('kiriof_distinct_couriers_instant', $result['deleted']);
        }
        $result = $this->runFixture(['mode'=>'repository','payload'=>['delivery_type'=>'instant','vehicle'=>'motor']]);
        $this->assertSame('instant', $result['prepared'][0][1][7]);
        $this->assertSame('motor', $result['prepared'][0][1][8]);
    }

    #[Test]
    public function migration_creates_and_upgrades_indexed_partition_and_nullable_vehicle_idempotently(): void {
        $fresh = $this->runFixture(['mode'=>'migration','exists'=>false]);
        $sql = implode("\n", $fresh['queries']);
        $this->assertStringContainsString("`delivery_type` varchar(20) NOT NULL DEFAULT 'express'", $sql);
        $this->assertStringContainsString('KEY `delivery_type` (`delivery_type`)', $sql);
        $this->assertStringContainsString('`vehicle` varchar(20) DEFAULT NULL', $sql);
        $upgrade = $this->runFixture(['mode'=>'migration']);
        $sql = implode("\n", $upgrade['queries']);
        $this->assertStringContainsString("ADD delivery_type varchar(20) NOT NULL DEFAULT 'express'", $sql);
        $this->assertStringContainsString('ADD KEY delivery_type (delivery_type)', $sql);
        $this->assertStringContainsString('ADD vehicle varchar(20) DEFAULT NULL', $sql);
        $this->assertStringContainsString("SET delivery_type = 'instant' WHERE delivery_type != 'instant' AND LOWER(TRIM(service)) IN ('gosend','grab_express','borzo')", $sql);
        $again = $this->runFixture(['mode'=>'migration','columns'=>['delivery_type','vehicle'],'indexed'=>true]);
        $sql = implode("\n", $again['queries']);
        $this->assertStringNotContainsString('ADD delivery_type', $sql);
        $this->assertStringNotContainsString('ADD KEY delivery_type', $sql);
        $this->assertStringNotContainsString('ADD vehicle', $sql);
        $this->assertStringContainsString("SET delivery_type = 'instant'", $sql);
        $this->assertSame($again['first_queries'], count($again['queries']));
        $this->assertSame('1', $again['options']['kiriof_transaction_partition_v1']);
        $this->assertSame('v3', $again['options']['kiriof_sync_version']);
        $this->assertSame($fresh['first_queries'], count($fresh['queries']));
        $this->assertSame($upgrade['first_queries'], count($upgrade['queries']));
        $complete = $this->runFixture(['mode'=>'migration', 'complete'=>true]);
        $this->assertSame([], $complete['queries']);
        $failed = $this->runFixture(['mode'=>'migration', 'fail'=>true]);
        $this->assertArrayNotHasKey('kiriof_transaction_partition_v1', $failed['options']);
        $this->assertGreaterThan($failed['first_queries'], count($failed['queries']));
    }
    #[Test]
    public function courier_edits_atomically_repartition_and_reject_unsupported_vehicle_codes(): void {
        foreach (['gosend'=>'instant', 'grab_express'=>'instant', 'borzo'=>'instant', 'jne'=>'express'] as $service=>$type) {
            $result = $this->runFixture(['mode'=>'update', 'changes'=>['service'=>$service, 'vehicle'=>'motor']]);
            $this->assertTrue($result['ok']);
            $this->assertSame($type, $result['updates'][0]['delivery_type']);
            $this->assertSame('instant' === $type ? 'motor' : null, $result['updates'][0]['vehicle']);
        }
        foreach ([null, [], 'motorcycle', ' Motor ', 'motor123', "mobil' OR 1=1"] as $vehicle) {
            $result = $this->runFixture(['mode'=>'repository', 'payload'=>['service'=>'gosend', 'vehicle'=>$vehicle]]);
            $this->assertSame('', $result['prepared'][0][1][8]);
        }
        $this->assertSame('mobil', \KiriminAjaOfficial\Services\TransactionDeliveryType::normalizeVehicle('mobil'));
    }

    #[Test]
    public function partial_courier_writes_preserve_existing_instant_vehicle_but_explicit_null_clears_it(): void {
        $result = $this->runFixture([
            'mode' => 'update',
            'changes' => ['service' => 'gosend'],
            'prior' => ['service' => 'gosend', 'delivery_type' => 'instant', 'vehicle' => 'motor'],
        ]);
        $this->assertTrue($result['ok']);
        $this->assertSame('instant', $result['updates'][0]['delivery_type']);
        $this->assertSame('motor', $result['updates'][0]['vehicle']);
        $this->assertSame('motor', $result['row']['vehicle']);

        $result = $this->runFixture([
            'mode' => 'update',
            'changes' => ['service' => 'gosend', 'vehicle' => null],
            'prior' => ['service' => 'gosend', 'delivery_type' => 'instant', 'vehicle' => 'motor'],
        ]);
        $this->assertTrue($result['ok']);
        $this->assertNull($result['updates'][0]['vehicle']);
        $this->assertNull($result['row']['vehicle']);
    }

    #[Test]
    public function existing_courier_classifies_delivery_type_only_writes_and_lookup_errors_fail_closed(): void {
        $result = $this->runFixture([
            'mode' => 'update',
            'changes' => ['delivery_type' => 'express'],
            'prior' => ['service' => 'gosend', 'delivery_type' => 'instant', 'vehicle' => 'mobil'],
        ]);
        $this->assertTrue($result['ok']);
        $this->assertSame('instant', $result['updates'][0]['delivery_type']);
        $this->assertSame('mobil', $result['updates'][0]['vehicle']);

        $result = $this->runFixture([
            'mode' => 'update',
            'changes' => ['service' => 'gosend'],
            'fail_lookup' => true,
        ]);
        $this->assertFalse($result['ok']);
        $this->assertSame([], $result['updates']);
    }

    #[Test]
    public function every_writer_repartitions_explicit_express_courier_and_aborts_failed_lookup(): void {
        foreach (['verified', 'callback', 'full'] as $writer) {
            $condition = 'full' === $writer ? ['wp_wc_order_stat_order_id' => 42] : ['order_id' => 'test'];
            $input = [
                'mode' => 'update', 'writer' => $writer, 'condition' => $condition,
                'changes' => ['service' => 'jne'],
                'prior' => ['service' => 'gosend', 'delivery_type' => 'instant', 'vehicle' => 'motor'],
            ];
            $result = $this->runFixture($input);
            $this->assertTrue($result['ok']);
            $this->assertSame('express', $result['row']['delivery_type']);
            $this->assertSame('jne', $result['row']['service']);
            $this->assertNull($result['row']['vehicle']);
            $this->assertCount(1, $result['queries']);
            $this->assertStringContainsString('full' === $writer ? 'wp_wc_order_stat_order_id = 42' : "`order_id` = 'test'", $result['queries'][0]);

            $result = $this->runFixture(array_replace($input, ['fail_lookup' => true]));
            $this->assertFalse($result['ok']);
            $this->assertSame([], $result['updates']);
            $this->assertSame('instant', $result['row']['delivery_type']);
            $this->assertSame('motor', $result['row']['vehicle']);
            $this->assertSame([], $result['deleted']);
        }
    }

    #[Test]
    public function lifecycle_and_vehicle_only_updates_do_not_read_existing_partition(): void {
        foreach (['verified', 'callback'] as $writer) {
            foreach ([['status' => 'shipped'], ['awb' => 'awb-123'], ['vehicle' => null]] as $changes) {
                $prior = ['service' => 'gosend', 'delivery_type' => 'instant', 'vehicle' => 'motor'];
                $result = $this->runFixture([
                    'mode' => 'update', 'writer' => $writer, 'changes' => $changes,
                    'prior' => $prior, 'fail_lookup' => true,
                ]);
                $this->assertTrue($result['ok']);
                $this->assertSame([], $result['queries']);
                $this->assertSame([], $result['deleted']);
                $this->assertSame($changes, $result['updates'][0]);
                $this->assertSame(array_replace($prior, $changes), $result['row']);
            }
        }
    }

}
