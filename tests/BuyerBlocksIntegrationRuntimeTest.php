<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
final class BuyerBlocksIntegrationRuntimeTest extends TestCase {
    #[Test]
    public function registration_without_interface_does_not_load_integration(): void {
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/buyer-blocks-integration-runtime.php' ), $output, $status );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        $this->assertSame( array( 'registered' => 0, 'loaded' => false ), json_decode( implode( "\n", $output ), true ) );
    }

    #[Test]
    public function block_service_is_registered_for_native_checkout(): void {
        $content = file_get_contents( dirname( __DIR__ ) . '/inc/Init.php' );
        $this->assertStringContainsString( 'Blocks\BuyerCheckoutRegistration::class', $content, 'Native Cart/Checkout asset registration must be loaded through plugin services' );
    }

    #[Test]
    public function editor_only_mentions_registered_handles(): void {
        $content = file_get_contents( dirname( __DIR__ ) . '/inc/Blocks/BuyerCheckoutIntegration.php' );
        $this->assertStringContainsString( "'kiriof-checkout-district-editor'", $content );
        $this->assertStringNotContainsString( 'kiriof-checkout-district-placement', $content, 'Disabled editor handles must not reference a removed asset' );
    }

    #[Test]
    public function shipping_children_register_without_cart_or_server_layout_insertion(): void {
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/buyer-blocks-integration-runtime.php' ) . ' placement', $output, $status );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        $data = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
        $this->assertFalse( $data['cartLoaded'] );
        $this->assertFalse( $data['insertionMethodExists'] );
        $this->assertNotContains( 'render_block_data', array_column( $data['filters'], 'hook' ), 'Saved checkout trees must not be structurally mutated on the server' );
        $this->assertNotContains( 'kiriof_insert_shipping_checkout_blocks', array_column( $data['filters'], 'callback' ) );
        $identityFilters = array_values( array_filter( $data['filters'], static fn( $filter ) => 'kiriof_identify_checkout_child' === $filter['callback'] ) );
        $this->assertSame( array( array( 'hook' => 'render_block', 'callback' => 'kiriof_identify_checkout_child', 'priority' => 9, 'acceptedArgs' => 2 ) ), $identityFilters );
        $this->assertCount( 2, $data['blocks'] );
        foreach ( $data['blocks'] as $block ) {
            $metadata = $block['metadata'];
            $this->assertSame( array( 'woocommerce/checkout-shipping-address-block' ), $metadata['parent'] );
            $this->assertSame( array( 'remove' => true, 'move' => true ), $metadata['attributes']['lock']['default'] );
            $this->assertArrayNotHasKey( 'blockHooks', $metadata, 'Automatic hooks would insert shells into WooCommerce layouts again' );
            foreach ( array( 'multiple', 'reusable', 'inserter', 'lock', 'html' ) as $support ) {
                $this->assertFalse( $metadata['supports'][ $support ] );
            }
            $this->assertStringContainsString( 'data-block-name="' . $metadata['name'] . '"', $block['html'] );
            $this->assertStringContainsString( 'wp-block-' . str_replace( '/', '-', $metadata['name'] ), $block['html'] );
            $this->assertSame( 1, substr_count( $block['html'], '<div ' ) );
            $this->assertStringEndsWith( '></div>', $block['html'] );
        }
        $this->assertStringContainsString( 'data-kiriof-district-inner-block="shipping"', $data['blocks'][0]['html'] );
        $this->assertStringContainsString( 'data-kiriof-map-inner-block="shipping"', $data['blocks'][1]['html'] );
    }
}
