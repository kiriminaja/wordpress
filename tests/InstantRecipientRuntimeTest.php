<?php
use PHPUnit\Framework\TestCase;

final class InstantRecipientRuntimeTest extends TestCase {
    private function runFixture(string $scenario = ''): array {
        $lines = []; $status = 0;
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/instant-recipient-runtime.php') . ' ' . escapeshellarg(json_encode(['scenario' => $scenario])) . ' 2>&1', $lines, $status);
        $this->assertSame(0, $status, implode("\n", $lines));
        return json_decode(implode("\n", $lines), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_classic_billing_contact_quotes_and_validates_real_context(): void {
        foreach (['', 'no_phone_getter', 'shipping_names', 'package_names', 'zero_coordinates'] as $scenario) {
            $r = $this->runFixture($scenario);
            $this->assertTrue($r['quote']['eligible'], $scenario);
            $this->assertSame('081234567890', $r['quote']['context']['recipient']['phone']);
            $this->assertSame($r['quote'], $r['again']);
            $this->assertSame($r['quote']['context'], $r['validated']['context']);
            $this->assertSame(1, $r['calls']);
        }
        $this->assertSame('Shipping', $this->runFixture('shipping_names')['quote']['context']['recipient']['first_name']);
        $this->assertSame('Actual', $this->runFixture('package_names')['quote']['context']['recipient']['first_name']);
    }

    public function test_explicit_values_and_other_address_identity_fail_closed(): void {
        foreach (['other_address', 'empty_phone', 'array_phone', 'number_phone', 'empty_names', 'package_empty_city', 'package_array_city', 'cod'] as $scenario) {
            $r = $this->runFixture($scenario);
            $this->assertFalse($r['quote']['eligible'], $scenario);
            $this->assertSame(0, $r['calls'], $scenario);
            $this->assertSame([], $r['cache'], $scenario);
        }
    }
}
