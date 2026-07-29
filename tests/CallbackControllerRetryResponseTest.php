<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CallbackControllerRetryResponseTest extends TestCase {
    #[Test]
    public function controller_uses_service_status_as_http_status(): void {
        $content = file_get_contents( PLUGIN_DIR . '/inc/Controllers/CallbackController.php' );

        $this->assertStringContainsString(
            '$service->status',
            $content,
            'Callback failures must preserve retryable service status codes in the HTTP response'
        );
    }

    #[Test]
    public function unexpected_controller_failures_return_server_error(): void {
        $content = file_get_contents( PLUGIN_DIR . '/inc/Controllers/CallbackController.php' );
        $catch   = strpos( $content, 'catch (\\Throwable $th)' );

        $this->assertNotFalse( $catch, 'Callback controller catch block must exist' );
        $this->assertStringContainsString(
            '500',
            substr( $content, $catch, 900 ),
            'Unexpected callback failures must return HTTP 500 so the webhook can be retried'
        );
    }
}
