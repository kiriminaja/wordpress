<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}

require_once PLUGIN_DIR . '/inc/Services/TransactionProcessServices/RecipientDataResolver.php';

final class RecipientDataResolverTest extends TestCase
{
	public function test_legacy_phone_metadata_is_preserved_for_issue_display(): void {
		$order = new class() {
			public function get_address( $type ): array { return array(); }
			public function get_meta( $key, $single ): string { return '_billing_phone' === $key ? '+6285451111000000' : ''; }
		};
		$recipient = ( new \KiriminAjaOfficial\Services\TransactionProcessServices\RecipientDataResolver() )->resolve( $order, (object) array(), (object) array() );
		$this->assertSame( '+6285451111000000', $recipient['phone'] );
	}

	public function test_empty_phone_getters_never_read_internal_phone_metadata(): void {
		foreach ( array( '', '+6285234534567' ) as $billing_phone ) {
			$order = new class( $billing_phone ) {
				public array $phone_getter_reads = array();
				public function __construct( private string $billing_phone ) {}
				public function get_address( $type ): array { return array(); }
				public function get_shipping_phone(): string { $this->phone_getter_reads[] = 'shipping'; return ''; }
				public function get_billing_phone(): string { $this->phone_getter_reads[] = 'billing'; return $this->billing_phone; }
				public function get_meta( $key, $single ): string {
					if ( in_array( $key, array( '_shipping_phone', '_billing_phone' ), true ) ) {
						throw new RuntimeException( 'Internal phone metadata must use its getter.' );
					}
					return '';
				}
			};
			$snapshot = (object) array( '_billing_phone' => '081234567890' );
			$recipient = ( new \KiriminAjaOfficial\Services\TransactionProcessServices\RecipientDataResolver() )->resolve( $order, $snapshot, (object) array() );
			$this->assertSame( '' === $billing_phone ? '081234567890' : $billing_phone, $recipient['phone'] );
			$this->assertSame( array( 'shipping', 'billing' ), $order->phone_getter_reads );
		}
	}

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
