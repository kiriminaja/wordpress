<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if (! defined('ABSPATH')) {
    define('ABSPATH', PLUGIN_DIR . '/tests/wordpress/');
}
if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field($value)
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
if (! function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return $value;
    }
}
if (! function_exists('WC')) {
    function WC()
    {
        return $GLOBALS['kiriof_test_wc'] ?? null;
    }
}

/**
 * Regression coverage for React/block checkout themes such as ShopVerse.
 */
final class ShopVerseBlockCheckoutCompatibilityTest extends TestCase
{
    #[Test]
    public function checkout_pricing_inherits_missing_variation_attributes_from_parent(): void
    {
        require_once PLUGIN_DIR . '/inc/Base/BaseService.php';
        require_once PLUGIN_DIR . '/inc/Services/UtilServices/GetWCCartAttributeService.php';

        $method = new ReflectionMethod(
            \KiriminAjaOfficial\Services\UtilServices\GetWCCartAttributeService::class,
            'getResolvedProductAttribute'
        );
        $method->setAccessible(true);

        $attributes = array(
            100 => array(
                'weight' => 2,
                'length' => 10,
                'width'  => 20,
                'height' => 30,
            ),
            101 => array(
                'weight' => 3,
                'length' => 0,
                'width'  => 25,
                'height' => '',
            ),
            102 => array(
                'weight' => '',
                'length' => 0,
                'width'  => '',
                'height' => 0,
            ),
        );

        $this->assertSame(2.0, $method->invoke(null, $attributes, 102, 100, 'weight'));
        $this->assertSame(10.0, $method->invoke(null, $attributes, 102, 100, 'length'));
        $this->assertSame(20.0, $method->invoke(null, $attributes, 102, 100, 'width'));
        $this->assertSame(30.0, $method->invoke(null, $attributes, 102, 100, 'height'));
        $this->assertSame(3.0, $method->invoke(null, $attributes, 101, 100, 'weight'));
        $this->assertSame(10.0, $method->invoke(null, $attributes, 101, 100, 'length'));
        $this->assertSame(25.0, $method->invoke(null, $attributes, 101, 100, 'width'));
        $this->assertSame(30.0, $method->invoke(null, $attributes, 101, 100, 'height'));
        $this->assertSame(2.0, $method->invoke(null, $attributes, 100, 0, 'weight'));
    }

    #[Test]
    public function volumetric_box_uses_smallest_packable_axis_aligned_stack(): void
    {
        require_once PLUGIN_DIR . '/inc/Utils/Volumetric.php';

        $items = array(
            array('length' => 100, 'width' => 10, 'height' => 2, 'qty' => 1),
            array('length' => 10, 'width' => 100, 'height' => 2, 'qty' => 2),
            array('length' => 20, 'width' => 20, 'height' => 20, 'qty' => 1),
        );

        $box = \KiriminAjaOfficial\Utils\Volumetric::calculateSmallestBox($items);

        $this->assertSame(26.0, $box['length']);
        $this->assertSame(20.0, $box['width']);
        $this->assertSame(100.0, $box['height']);
        $this->assertSame(52000.0, $box['length'] * $box['width'] * $box['height']);
    }

    #[Test]
    public function volumetric_box_prefers_rotation_that_reduces_package_volume(): void
    {
        require_once PLUGIN_DIR . '/inc/Utils/Volumetric.php';

        $items = array(
            array('length' => 100, 'width' => 50, 'height' => 10, 'qty' => 1),
            array('length' => 10, 'width' => 50, 'height' => 100, 'qty' => 1),
        );

        $box = \KiriminAjaOfficial\Utils\Volumetric::calculateSmallestBox($items);

        $this->assertSame(100.0, $box['length']);
        $this->assertSame(10.0, $box['width']);
        $this->assertSame(100.0, $box['height']);
    }

    #[Test]
    public function volumetric_box_does_not_fake_conservatism_by_only_expanding_volume(): void
    {
        require_once PLUGIN_DIR . '/inc/Utils/Volumetric.php';

        $items = array(
            array('length' => 100, 'width' => 100, 'height' => 1, 'qty' => 1),
            array('length' => 1, 'width' => 1, 'height' => 100, 'qty' => 100),
        );

        $box = \KiriminAjaOfficial\Utils\Volumetric::calculateSmallestBox($items);

        $this->assertSame(200.0, $box['length']);
        $this->assertSame(1.0, $box['width']);
        $this->assertSame(100.0, $box['height']);
    }

    #[Test]
    public function store_api_update_callback_persists_force_insurance_in_session(): void
    {
        require_once PLUGIN_DIR . '/inc/Controllers/CheckoutController.php';

        $session = new class {
            public array $values = array();

            public function set($key, $value): void
            {
                $this->values[$key] = $value;
            }

            public function get($key, $default = null)
            {
                return $this->values[$key] ?? $default;
            }
        };
        $GLOBALS['kiriof_test_wc'] = (object) array('session' => $session);

        $controller = ( new ReflectionClass( \KiriminAjaOfficial\Controllers\CheckoutController::class ) )->newInstanceWithoutConstructor();
        $controller->kiriof_store_api_update_checkout(array(
            'shipping_metode_id' => 'kiriminaja-official_jne_REG23',
            'destination_id'     => 44064,
            'destination_name'   => 'Sariharjo',
            'payment_method'     => 'bacs',
            'insurance'          => 1,
            'force_insurance'    => 1,
        ));

        $this->assertSame(1, $session->get('force_insurance'));
        $this->assertSame(1, $session->get('kiriof_force_insurance'));
        $this->assertSame(1, $session->get('kiriof_insurance'));
        $this->assertSame(array('kiriminaja-official_jne_REG23'), $session->get('kiriof_chosen_shipping_methods'));
        $this->assertSame('jne_REG23', $session->get('kiriof_expedition'));
    }

    #[Test]
    public function fee_cache_matcher_invalidates_non_cod_insurance_when_checkout_context_changes(): void
    {
        require_once PLUGIN_DIR . '/inc/Controllers/CheckoutController.php';

        $controller = ( new ReflectionClass( \KiriminAjaOfficial\Controllers\CheckoutController::class ) )->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($controller, 'kiriof_fee_cache_matches');
        $method->setAccessible(true);

        $cachedContext = array(
            'shipping_method' => 'kiriminaja-official_idx_06',
            'destination_id'  => 44064,
            'payment_method'  => 'bacs',
            'insurance'       => 1,
        );
        $changedContext = array(
            'shipping_method' => 'kiriminaja-official_jne_REG23',
            'destination_id'  => 44064,
            'payment_method'  => 'bacs',
            'insurance'       => 1,
        );

        $this->assertTrue($method->invoke($controller, $cachedContext, $cachedContext));
        $this->assertFalse(
            $method->invoke($controller, $cachedContext, $changedContext),
            'A non-COD checkout that changes courier must not reuse stale cached insurance amounts'
        );
    }

    #[Test]
    public function invalid_transaction_status_filters_default_to_all(): void
    {
        require_once PLUGIN_DIR . '/inc/Contracts/TransactionListQueryInterface.php';
        require_once PLUGIN_DIR . '/inc/Services/ListDateRangeFilter.php';
        require_once PLUGIN_DIR . '/inc/Queries/WordPressTransactionListQuery.php';
        foreach (array('', 'invalid-status', null, array('wc-processing')) as $status) {
            $this->assertSame(
                'all',
                \KiriminAjaOfficial\Queries\WordPressTransactionListQuery::normalizeStatusFilter($status),
                'Empty/invalid status values must default to all rather than hiding non-processing orders'
            );
        }
    }

}
