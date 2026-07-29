<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}

require_once PLUGIN_DIR . '/inc/Services/TransactionProcessServices/RecipientDataResolver.php';

final class RecipientDataResolverTest extends TestCase
{
    #[Test]
    public function current_shipping_data_replaces_the_checkout_snapshot(): void
    {
        $order = new RecipientDataResolverOrder(
            array(
                'first_name' => 'Updated Shipping',
                'last_name'  => 'Recipient',
                'address_1'  => 'Jl. Baru 10',
                'city'       => 'Bandung',
                'postcode'   => '40123',
                'phone'      => '08123456789',
            ),
            array(
                'first_name' => 'Billing',
                'last_name'  => 'Buyer',
            )
        );
        $snapshot = (object) array(
            '_shipping_first_name' => 'Old Shipping',
            '_shipping_address_1'  => 'Jl. Lama 1',
            '_shipping_postcode'   => '11111',
            '_shipping_phone'      => '08000000000',
        );
        $transaction = (object) array( 'destination_sub_district' => 'Coblong, 40132' );

        $recipient = ( new \KiriminAjaOfficial\Services\TransactionProcessServices\RecipientDataResolver() )
            ->resolve( $order, $snapshot, $transaction );

        $this->assertSame( 'Updated Shipping', $recipient['first_name'] );
        $this->assertSame( 'Jl. Baru 10', $recipient['address_1'] );
        $this->assertSame( '40123', $recipient['postcode'] );
        $this->assertSame( '08123456789', $recipient['phone'] );
    }

    #[Test]
    public function billing_data_is_used_when_shipping_data_is_empty(): void
    {
        $order = new RecipientDataResolverOrder(
            array(),
            array(
                'first_name' => 'Updated Billing',
                'last_name'  => 'Buyer',
                'address_1'  => 'Jl. Tagore 5',
                'city'       => 'Jakarta',
                'postcode'   => '12190',
                'phone'      => '08987654321',
            )
        );
        $transaction = (object) array( 'destination_sub_district' => 'Legacy District, 99999' );

        $recipient = ( new \KiriminAjaOfficial\Services\TransactionProcessServices\RecipientDataResolver() )
            ->resolve( $order, (object) array(), $transaction );

        $this->assertSame( 'Updated Billing', $recipient['first_name'] );
        $this->assertSame( 'Jl. Tagore 5', $recipient['address_1'] );
        $this->assertSame( '12190', $recipient['postcode'] );
        $this->assertSame( '08987654321', $recipient['phone'] );
    }
}

final class RecipientDataResolverOrder
{
    private array $shipping;
    private array $billing;

    public function __construct( array $shipping, array $billing )
    {
        $this->shipping = $shipping;
        $this->billing  = $billing;
    }

    public function get_address( string $type ): array
    {
        return 'shipping' === $type ? $this->shipping : $this->billing;
    }

    public function get_meta( string $key, bool $single ): string
    {
        return '';
    }
}
