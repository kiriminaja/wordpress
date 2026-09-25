<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestPickupDetailRedirectTest extends TestCase
{
    #[Test]
    public function obsolete_pickup_detail_page_redirects_to_transactions_by_pickup_id(): void
    {
        $template = file_get_contents( PLUGIN_DIR . '/templates/request-pickup-detail/index.php' );
        $admin = file_get_contents( PLUGIN_DIR . '/inc/Pages/Admin.php' );
        $query = file_get_contents( PLUGIN_DIR . '/inc/Queries/WordPressTransactionListQuery.php' );

        $this->assertStringContainsString( "'key', 'pid:' . \$kiriof_pickup_number", $template );
        $this->assertStringContainsString( "admin.php?page=kiriminaja-transaction", $template );
        $this->assertStringContainsString( 'wp_safe_redirect', $template );
        $this->assertStringNotContainsString( "'menu_slug'=>'kiriminaja-request-pickup-detail'", $admin );
        $this->assertStringContainsString( "strpos(\$key, 'pid:')", $query );
        $this->assertStringContainsString( 'kiriminaja_transactions.pickup_number = %s', $query );
    }
}

final class PaymentDetailsLinkTargetTest extends TestCase
{
    #[Test]
    public function payment_details_link_opens_the_transaction_list_filtered_by_pickup_id(): void
    {
        $payments = file_get_contents( PLUGIN_DIR . '/inc/Services/PaymentListRenderService.php' );

        $this->assertStringContainsString( "add_query_arg( 'key', 'pid:' . \$pickup_number", $payments );
        $this->assertStringContainsString( "admin.php?page=kiriminaja-transaction", $payments );
        $this->assertStringNotContainsString( "kiriminaja-request-pickup-detail&pickup_number", $payments );
    }
}
