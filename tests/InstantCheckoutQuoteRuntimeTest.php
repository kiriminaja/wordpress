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
    public function outside_radius_never_reads_cached_rates_or_calls_the_api_and_old_selections_fail(): void {
        $r = $this->runFixture(['scenario'=>'outside_radius']);
        $this->assertSame(
            [
                'quote.eligible' => false,
                'quote.code' => 'outside_instant_radius',
                'quote.message' => 'Instant delivery is available only within 40 km of the pickup origin. You can use Express delivery for this address.',
                'calls' => 0,
                'cache' => [],
            ],
            [
                'quote.eligible' => $r['quote']['eligible'],
                'quote.code' => $r['quote']['code'],
                'quote.message' => $r['quote']['message'],
                'calls' => $r['calls'],
                'cache' => $r['cache'],
            ],
            __FUNCTION__
        );
        foreach (['mutate_outside_radius', 'mutate_parent_outside_radius'] as $scenario) {
            $r = $this->runFixture(['scenario'=>$scenario]);
            $this->assertSame(
                [
                    'quote.eligible' => true,
                    'validation_error' => 'outside_instant_radius',
                    'calls' => 1,
                ],
                [
                    'quote.eligible' => $r['quote']['eligible'],
                    'validation_error' => $r['validation_error'],
                    'calls' => $r['calls'],
                ],
                __FUNCTION__
            );
        }
        $this->assertSame(1, $this->runFixture()['quote']['context']['coverage_version']);
    }

    #[Test]
    public function live_quotes_are_session_bound_exact_and_cached(): void {
        $r = $this->runFixture();
        $this->assertSame(
            [
                'quote.eligible' => true,
                'quote.rates.0.cost' => 19000,
                'quote.rates.0.service' => 'GO-INSTANT',
                'quote.rates.0.vehicle' => 'motor',
                'format: \'/^.a-f0-9{64}$/\', r.quote.rates.0.quote_token' => 1,
                'again' => $r['quote'],
                'calls' => 1,
                'validated.rate' => $r['quote']['rates'][0],
                'quote.context.origin.location_id' => 7,
                'payloads.0.weight' => 1000,
                'payloads.0.item_price' => 100000,
                'absent fields: r.quote.rates.0, .latitude\' => true' => [],
                'array_keys(r.cache)' => ['kiriof_instant_checkout_quotes'],
            ],
            [
                'quote.eligible' => $r['quote']['eligible'],
                'quote.rates.0.cost' => $r['quote']['rates'][0]['cost'],
                'quote.rates.0.service' => $r['quote']['rates'][0]['service'],
                'quote.rates.0.vehicle' => $r['quote']['rates'][0]['vehicle'],
                'format: \'/^.a-f0-9{64}$/\', r.quote.rates.0.quote_token' => preg_match('/^[a-f0-9]{64}$/', $r['quote']['rates'][0]['quote_token']),
                'again' => $r['again'],
                'calls' => $r['calls'],
                'validated.rate' => $r['validated']['rate'],
                'quote.context.origin.location_id' => $r['quote']['context']['origin']['location_id'],
                'payloads.0.weight' => $r['payloads'][0]['weight'],
                'payloads.0.item_price' => $r['payloads'][0]['item_price'],
                'absent fields: r.quote.rates.0, .latitude\' => true' => array_intersect_key($r['quote']['rates'][0], ['latitude' => true]),
                'array_keys(r.cache)' => array_keys($r['cache']),
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function package_type_is_rebuilt_from_current_physical_products_for_quote_and_validation(): void {
        foreach (['package_type_nondefault' => 3, 'package_type_explicit' => 3, 'package_type_stale' => 3, 'package_type_virtual' => 3, 'package_type_mixed' => 7, 'package_type_invalid' => 7, '' => 7] as $scenario => $type) {
            $r = $this->runFixture(['scenario' => $scenario]);
            $this->assertSame(
                [
                    'quote.eligible' => true,
                    'quote.context.package_type_id' => $type,
                    'validation_error' => '',
                    'validated.context' => $r['quote']['context'],
                    'again' => $r['quote'],
                    'calls' => 1,
                ],
                [
                    'quote.eligible' => $r['quote']['eligible'],
                    'quote.context.package_type_id' => $r['quote']['context']['package_type_id'],
                    'validation_error' => $r['validation_error'],
                    'validated.context' => $r['validated']['context'],
                    'again' => $r['again'],
                    'calls' => $r['calls'],
                ],
                $scenario
            );
        }
    }

    #[Test]
    public function product_package_type_changes_invalidate_selection_even_with_stale_explicit_type(): void {
        foreach (['mutate_package_type', 'mutate_package_type_stale', 'mutate_package_type_default', 'mutate_package_type_mixed'] as $scenario) {
            $r = $this->runFixture(['scenario' => $scenario]);
            $this->assertSame(
                [
                    'quote.eligible' => true,
                    'quote.context.package_type_id' => 3,
                    'validated' => null,
                    'validation_error' => 'quote_expired_or_changed',
                    'calls' => 1,
                ],
                [
                    'quote.eligible' => $r['quote']['eligible'],
                    'quote.context.package_type_id' => $r['quote']['context']['package_type_id'],
                    'validated' => $r['validated'],
                    'validation_error' => $r['validation_error'],
                    'calls' => $r['calls'],
                ],
                $scenario
            );
        }
    }

    #[Test]
    public function context_guards_fail_closed_without_api_calls(): void {
        foreach (['currency', 'packages', 'cod', 'disabled', 'credentials', 'settings_throw', 'no_pin', 'v1', 'country', 'stale_address', 'origin_bad', 'origin_missing', 'timezone_invalid', 'name', 'phone', 'postcode', 'virtual', 'weight_zero', 'dimensions_zero', 'overweight', 'quantity', 'negative_value'] as $scenario) {
            $r = $this->runFixture(['scenario' => $scenario]);
            $this->assertSame(
                [
                    'quote.eligible' => false,
                    'quote.rates' => [],
                    'quote.context' => null,
                    'calls' => 0,
                    'logs' => [],
                    'cache' => [],
                    'redaction: r.quote.code, \'secret\'' => 0,
                ],
                [
                    'quote.eligible' => $r['quote']['eligible'],
                    'quote.rates' => $r['quote']['rates'],
                    'quote.context' => $r['quote']['context'],
                    'calls' => $r['calls'],
                    'logs' => $r['logs'],
                    'cache' => $r['cache'],
                    'redaction: r.quote.code, \'secret\'' => substr_count($r['quote']['code'], 'secret'),
                ],
                $scenario
            );
        }
    }

    #[Test]
    public function malformed_remote_quotes_never_cache_or_expose_errors(): void {
        foreach (['api_failure', 'throw', 'status_string', 'price_negative', 'price_fraction', 'price_bool', 'price_missing', 'duplicate', 'wrong_courier', 'wrong_service', 'car', 'malformed', 'total_string', 'total_fraction', 'total_bool', 'total_negative', 'total_disagree', 'double_count', 'total_missing', 'admin_missing', 'admin_negative', 'shipping_string', 'overflow', 'eta_html', 'eta_long', 'eta_array', 'eta_empty', 'eta_control', 'eta_missing'] as $scenario) {
            $r = $this->runFixture(['scenario' => $scenario]);
            $this->assertSame(
                [
                    'quote.eligible' => false,
                    'quote.rates' => [],
                    'calls' => 2,
                    'cache' => [],
                    'redaction: r.quote.message, \'secret\'' => 0,
                ],
                [
                    'quote.eligible' => $r['quote']['eligible'],
                    'quote.rates' => $r['quote']['rates'],
                    'calls' => $r['calls'],
                    'cache' => $r['cache'],
                    'redaction: r.quote.message, \'secret\'' => substr_count($r['quote']['message'], 'secret'),
                ],
                $scenario
            );
        }
        foreach (['zero_price', 'zero_coordinates', 'results_envelope', 'grab'] as $scenario) {
            $this->assertTrue($this->runFixture(['scenario' => $scenario])['quote']['eligible'], $scenario);
        }
    }

    #[Test]
    public function validation_rebuilds_context_without_api_and_blocks_stale_or_forged_tokens(): void {
        foreach (['mutate_currency', 'mutate_packages', 'mutate_cart', 'mutate_name', 'mutate_phone', 'mutate_pin', 'mutate_origin', 'mutate_policy', 'mutate_payment', 'expire', 'clear_session', 'forge', 'wrong_selection', 'mutate_package_id', 'mutate_dimensions', 'mutate_variation', 'mutate_value', 'mutate_fractional_value', 'mutate_fractional_dimensions', 'mutate_credentials'] as $scenario) {
            $r = $this->runFixture(['scenario' => $scenario]);
            $this->assertTrue($r['quote']['eligible'], $scenario);
            $this->assertNotEmpty($r['validation_error'], $scenario);
            $this->assertSame(1, $r['calls'], $scenario);
        }
    }

    #[Test]
    public function successful_context_cache_is_bounded_and_fractional_units_round_up(): void {
        $r = $this->runFixture(['scenario' => 'cache_bound']);
        $this->assertSame(
            [
                'count(r.cache.kiriof_instant_checkout_quotes)' => 8,
                'calls' => 11,
            ],
            [
                'count(r.cache.kiriof_instant_checkout_quotes)' => count($r['cache']['kiriof_instant_checkout_quotes']),
                'calls' => $r['calls'],
            ],
            __FUNCTION__
        );
        $r = $this->runFixture(['scenario' => 'fractional_units']);
        $this->assertSame(
            [
                'quote.eligible' => true,
                'quote.context.weight' => 2,
                'quote.context.items.0.width' => 1,
            ],
            [
                'quote.eligible' => $r['quote']['eligible'],
                'quote.context.weight' => $r['quote']['context']['weight'],
                'quote.context.items.0.width' => $r['quote']['context']['items'][0]['width'],
            ],
            __FUNCTION__
        );
    }
    #[Test]
    public function instant_timezone_defaults_to_wib_independently_of_wordpress(): void {
        $r = $this->runFixture(['scenario' => 'timezone']);
        $this->assertSame(
            [
                'quote.eligible' => true,
                'payloads.0.timezone' => 'WIB',
                'wp_timezone' => 'UTC',
                'fixture.payloads.0.timezone' => 'WITA',
                'fixture.quote.code' => 'timezone_unsupported',
                'fixture.quote.eligible' => true,
            ],
            [
                'quote.eligible' => $r['quote']['eligible'],
                'payloads.0.timezone' => $r['payloads'][0]['timezone'],
                'wp_timezone' => $r['wp_timezone'],
                'fixture.payloads.0.timezone' => $this->runFixture(['scenario' => 'timezone_lower'])['payloads'][0]['timezone'],
                'fixture.quote.code' => $this->runFixture(['scenario' => 'timezone_invalid'])['quote']['code'],
                'fixture.quote.eligible' => $this->runFixture(['scenario' => 'insurance'])['quote']['eligible'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function only_genuine_pricing_failures_log_fixed_redacted_warnings(): void {
        foreach (['', 'no_pin', 'cod', 'disabled', 'outside_radius', 'wrong_service', 'wrong_courier', 'car', 'logger_throw'] as $scenario) {
            $r = $this->runFixture(['scenario' => $scenario]);
            $this->assertSame([], $r['logs'], $scenario);
        }
        foreach (['throw' => 'pricing_api_exception', 'api_failure' => 'pricing_unavailable', 'status_string' => 'pricing_unavailable', 'malformed' => 'pricing_unavailable', 'price_negative' => 'pricing_unavailable', 'duplicate' => 'pricing_unavailable'] as $scenario => $code) {
            $r = $this->runFixture(['scenario' => $scenario]);
            $this->assertSame(
                [
                    'quote.eligible' => false,
                    'count(r.logs)' => 2,
                ],
                [
                    'quote.eligible' => $r['quote']['eligible'],
                    'count(r.logs)' => count($r['logs']),
                ],
                __FUNCTION__
            );
            foreach ($r['logs'] as $log) {
                $this->assertSame(
                    [
                        'log.level' => 'warning',
                        'log.source' => 'kiriminaja_instant',
                        'log.context' => ['code' => $code, 'backtrace' => false],
                    ],
                    [
                        'log.level' => $log['level'],
                        'log.source' => $log['source'],
                        'log.context' => $log['context'],
                    ],
                    __FUNCTION__
                );
            }
            foreach (['secret', 'Buyer', '081234567890', '106.8', 'quote_token'] as $private) {
                $this->assertStringNotContainsString($private, json_encode($r['logs']));
            }
        }
        $this->assertTrue($this->runFixture(['scenario' => 'logger_throw'])['quote']['eligible']);
    }

    #[Test]
    public function customer_total_includes_admin_fee_and_preserves_hour_estimate(): void {
        $r = $this->runFixture(['scenario' => 'example_total']);
        $rate = $r['quote']['rates'][0];
        $this->assertSame(
            [
                'rate.cost' => 55000,
                'rate.total_price' => 55000,
                'rate.shipping_costs' => 54000,
                'rate.admin_fee' => 1000,
                'rate.estimation' => '1-2 hours',
                'fixture.quote.rates.0.admin_fee' => 0,
                'fixture.validated.context.insurance === false' => true,
            ],
            [
                'rate.cost' => $rate['cost'],
                'rate.total_price' => $rate['total_price'],
                'rate.shipping_costs' => $rate['shipping_costs'],
                'rate.admin_fee' => $rate['admin_fee'],
                'rate.estimation' => $rate['estimation'],
                'fixture.quote.rates.0.admin_fee' => $this->runFixture(['scenario' => 'zero_admin'])['quote']['rates'][0]['admin_fee'],
                'fixture.validated.context.insurance === false' => $this->runFixture(['scenario' => 'mutate_insurance'])['validated']['context']['insurance'] === false,
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function incompatible_cached_amounts_are_refreshed_not_reused(): void {
        foreach (['cache_version2', 'cache_old', 'cache_total', 'cache_admin', 'cache_cost', 'cache_eta', 'cache_expiry', 'cache_context'] as $scenario) {
            $r = $this->runFixture(['scenario' => $scenario]);
            $this->assertSame(
                [
                    'again.eligible' => true,
                    'calls' => 2,
                    '(r.quote.rates.0.quote_token === r.again.rates.0.quote_token)' => false,
                ],
                [
                    'again.eligible' => $r['again']['eligible'],
                    'calls' => $r['calls'],
                    '(r.quote.rates.0.quote_token === r.again.rates.0.quote_token)' => ($r['quote']['rates'][0]['quote_token'] === $r['again']['rates'][0]['quote_token']),
                ],
                $scenario
            );
        }
    }

    #[Test]
    public function currency_is_explicit_fingerprinted_and_changes_invalidate_without_api(): void {
        $r = $this->runFixture();
        $this->assertSame(
            [
                'quote.context.currency' => 'IDR',
                'quote.context.amount_version' => 3,
                'fixture.quote.code' => 'currency_unsupported',
                'fixture.validation_error' => 'currency_unsupported',
                'fixture.validation_error' => 'packages_invalid',
            ],
            [
                'quote.context.currency' => $r['quote']['context']['currency'],
                'quote.context.amount_version' => $r['quote']['context']['amount_version'],
                'fixture.quote.code' => $this->runFixture(['scenario' => 'currency'])['quote']['code'],
                'fixture.validation_error' => $this->runFixture(['scenario' => 'mutate_currency'])['validation_error'],
                'fixture.validation_error' => $this->runFixture(['scenario' => 'mutate_packages'])['validation_error'],
            ],
            __FUNCTION__
        );
        $expires = $r['quote']['rates'][0]['expires'];
        $this->assertGreaterThan(time(), $expires);
        $this->assertLessThanOrEqual(time() + 120, $expires);
    }

}
