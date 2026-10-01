<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantCheckoutQuoteRuntimeTest extends TestCase {
    private function runFixture(array $input = []): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/instant-checkout-quote-runtime.php') . ' ' . escapeshellarg(json_encode($input, JSON_THROW_ON_ERROR)));
        return json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function live_quotes_are_session_bound_exact_and_cached(): void {
        $r = $this->runFixture();
        $this->assertTrue($r['quote']['eligible']);
        $this->assertSame(19000, $r['quote']['rates'][0]['cost']);
        $this->assertSame('GO-INSTANT', $r['quote']['rates'][0]['service']);
        $this->assertSame('motor', $r['quote']['rates'][0]['vehicle']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $r['quote']['rates'][0]['quote_token']);
        $this->assertSame($r['quote'], $r['again']);
        $this->assertSame(1, $r['calls']);
        $this->assertSame($r['quote']['rates'][0], $r['validated']['rate']);
        $this->assertSame(7, $r['quote']['context']['origin']['location_id']);
        $this->assertSame(1000, $r['payloads'][0]['weight']);
        $this->assertSame(100000, $r['payloads'][0]['item_price']);
        $this->assertArrayNotHasKey('latitude', $r['quote']['rates'][0]);
        $this->assertSame(['kiriof_instant_checkout_quotes'], array_keys($r['cache']));
    }

    #[Test]
    public function context_guards_fail_closed_without_api_calls(): void {
        foreach (['cod', 'disabled', 'credentials', 'settings_throw', 'no_pin', 'v1', 'country', 'stale_address', 'origin_bad', 'origin_missing', 'timezone_invalid', 'name', 'phone', 'postcode', 'virtual', 'weight_zero', 'dimensions_zero', 'overweight', 'quantity', 'negative_value'] as $scenario) {
            $r = $this->runFixture(['scenario' => $scenario]);
            $this->assertFalse($r['quote']['eligible'], $scenario);
            $this->assertSame([], $r['quote']['rates'], $scenario);
            $this->assertNull($r['quote']['context'], $scenario);
            $this->assertSame(0, $r['calls'], $scenario);
            $this->assertSame([], $r['cache'], $scenario);
            $this->assertStringNotContainsString('secret', $r['quote']['code']);
        }
    }

    #[Test]
    public function malformed_remote_quotes_never_cache_or_expose_errors(): void {
        foreach (['api_failure', 'throw', 'status_string', 'price_negative', 'price_fraction', 'price_bool', 'price_missing', 'duplicate', 'wrong_courier', 'wrong_service', 'car', 'malformed', 'total_string', 'total_fraction', 'total_bool', 'total_negative', 'total_disagree', 'double_count', 'total_missing', 'admin_missing', 'admin_negative', 'shipping_string', 'overflow', 'eta_html', 'eta_long', 'eta_array', 'eta_empty', 'eta_control', 'eta_missing'] as $scenario) {
            $r = $this->runFixture(['scenario' => $scenario]);
            $this->assertFalse($r['quote']['eligible'], $scenario);
            $this->assertSame([], $r['quote']['rates'], $scenario);
            $this->assertSame(2, $r['calls'], $scenario);
            $this->assertSame([], $r['cache'], $scenario);
            $this->assertStringNotContainsString('secret', $r['quote']['message']);
        }
        foreach (['zero_price', 'zero_coordinates', 'results_envelope', 'grab'] as $scenario) {
            $this->assertTrue($this->runFixture(['scenario' => $scenario])['quote']['eligible'], $scenario);
        }
    }

    #[Test]
    public function validation_rebuilds_context_without_api_and_blocks_stale_or_forged_tokens(): void {
        foreach (['mutate_cart', 'mutate_name', 'mutate_phone', 'mutate_pin', 'mutate_origin', 'mutate_policy', 'mutate_payment', 'expire', 'clear_session', 'forge', 'wrong_selection', 'mutate_package_id', 'mutate_dimensions', 'mutate_variation', 'mutate_value', 'mutate_fractional_value', 'mutate_fractional_dimensions', 'mutate_credentials'] as $scenario) {
            $r = $this->runFixture(['scenario' => $scenario]);
            $this->assertTrue($r['quote']['eligible'], $scenario);
            $this->assertNotEmpty($r['validation_error'], $scenario);
            $this->assertSame(1, $r['calls'], $scenario);
        }
    }

    #[Test]
    public function successful_context_cache_is_bounded_and_fractional_units_round_up(): void {
        $r = $this->runFixture(['scenario' => 'cache_bound']);
        $this->assertCount(8, $r['cache']['kiriof_instant_checkout_quotes']);
        $this->assertSame(11, $r['calls']);
        $r = $this->runFixture(['scenario' => 'fractional_units']);
        $this->assertTrue($r['quote']['eligible']);
        $this->assertSame(2, $r['quote']['context']['weight']);
        $this->assertSame(1, $r['quote']['context']['items'][0]['width']);
    }
    #[Test]
    public function instant_timezone_defaults_to_wib_independently_of_wordpress(): void {
        $r = $this->runFixture(['scenario' => 'timezone']);
        $this->assertTrue($r['quote']['eligible']);
        $this->assertSame('WIB', $r['payloads'][0]['timezone']);
        $this->assertSame('UTC', $r['wp_timezone']);
        $this->assertSame('WITA', $this->runFixture(['scenario' => 'timezone_lower'])['payloads'][0]['timezone']);
        $this->assertSame('timezone_unsupported', $this->runFixture(['scenario' => 'timezone_invalid'])['quote']['code']);
        $this->assertTrue($this->runFixture(['scenario' => 'insurance'])['quote']['eligible']);
    }

    #[Test]
    public function quote_traces_only_fixed_codes_and_safe_live_cache_metadata(): void {
        $r = $this->runFixture();
        $this->assertSame('ok', $r['logs'][0]['context']['code']);
        $this->assertTrue($r['logs'][0]['context']['live_quote_checked']);
        $this->assertTrue($r['logs'][1]['context']['cached']);
        $this->assertFalse($r['logs'][1]['context']['live_quote_checked']);
        $this->assertSame('kiriminaja_instant', $r['logs'][0]['source']);
        foreach (['secret', 'Buyer', '081234567890', '106.8', 'quote_token'] as $private) {
            $this->assertStringNotContainsString($private, json_encode($r['logs']));
        }
        $this->assertSame('destination_invalid', $this->runFixture(['scenario' => 'no_pin'])['logs'][0]['context']['code']);
        $this->assertSame('quote_unavailable', $this->runFixture(['scenario' => 'throw'])['logs'][0]['context']['code']);
        $this->assertTrue($this->runFixture(['scenario' => 'api_failure'])['logs'][0]['context']['live_quote_checked']);
        $this->assertTrue($this->runFixture(['scenario' => 'logger_throw'])['quote']['eligible']);
    }

    #[Test]
    public function customer_total_includes_admin_fee_and_preserves_hour_estimate(): void {
        $r = $this->runFixture(['scenario' => 'example_total']);
        $rate = $r['quote']['rates'][0];
        $this->assertSame(55000, $rate['cost']);
        $this->assertSame(55000, $rate['total_price']);
        $this->assertSame(54000, $rate['shipping_costs']);
        $this->assertSame(1000, $rate['admin_fee']);
        $this->assertSame('1-2 hours', $rate['estimation']);
        $this->assertSame(0, $this->runFixture(['scenario' => 'zero_admin'])['quote']['rates'][0]['admin_fee']);
        $this->assertTrue($this->runFixture(['scenario' => 'mutate_insurance'])['validated']['context']['insurance'] === false);
    }

    #[Test]
    public function incompatible_cached_amounts_are_refreshed_not_reused(): void {
        foreach (['cache_old', 'cache_total', 'cache_admin', 'cache_cost', 'cache_eta', 'cache_expiry', 'cache_context'] as $scenario) {
            $r = $this->runFixture(['scenario' => $scenario]);
            $this->assertTrue($r['again']['eligible'], $scenario);
            $this->assertSame(2, $r['calls'], $scenario);
            $this->assertNotSame($r['quote']['rates'][0]['quote_token'], $r['again']['rates'][0]['quote_token'], $scenario);
        }
    }

}
