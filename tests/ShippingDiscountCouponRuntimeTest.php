<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShippingDiscountCouponRuntimeTest extends TestCase
{
    #[Test]
    public function coupon_eligibility_and_fresh_pricing_are_exercised_at_runtime(): void
    {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/shipping-coupon-eligibility-runtime.php');
        exec($command . ' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));
        $this->assertSame('ok', trim(implode("\n", $output)));
    }

}
