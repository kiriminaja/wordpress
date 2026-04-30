<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Validates that plugin configuration endpoints and admin pages
 * are restricted to users with the manage_woocommerce capability
 * (WordPress Administrators and WooCommerce Shop Managers).
 *
 * These are static-analysis tests — they parse the source files without
 * loading WordPress — following the same pattern as SecurityValidationTest.
 */
final class AccessControlTest extends TestCase
{
    private const REQUIRED_CAPABILITY = 'manage_woocommerce';

    /** Controller files that handle admin AJAX actions. */
    private const ADMIN_CONTROLLERS = [
        'inc/Controllers/SettingController.php',
        'inc/Controllers/ShippingProcessController.php',
        'inc/Controllers/TransactionProcessController.php',
    ];

    /** Template files that render admin pages. */
    private const ADMIN_TEMPLATES = [
        'templates/setting/index.php',
        'templates/transaction-process/index.php',
        'templates/request-pickup/index.php',
        'templates/admin.php',
    ];

    // ------------------------------------------------------------------
    // AJAX handler capability checks
    // ------------------------------------------------------------------

    /**
     * Every admin AJAX handler must check for manage_woocommerce.
     */
    #[Test]
    public function admin_ajax_handlers_require_manage_woocommerce(): void
    {
        $violations = [];

        foreach (self::ADMIN_CONTROLLERS as $relPath) {
            $filePath = PLUGIN_DIR . '/' . $relPath;
            if (!file_exists($filePath)) {
                $this->fail("Expected controller file not found: {$relPath}");
            }

            $content = file_get_contents($filePath);

            // Collect all wp_ajax_ registrations (excluding nopriv)
            preg_match_all(
                '/add_action\s*\(\s*[\'"]wp_ajax_(?!nopriv_)([a-zA-Z0-9_-]+)[\'"].*?[\'"](\w+)[\'"]\s*\)/',
                $content,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $hookSuffix = $match[1];
                $methodName = $match[2];

                $methodPattern = '/function\s+' . preg_quote($methodName, '/') . '\s*\(/';
                if (!preg_match($methodPattern, $content, $_, PREG_OFFSET_CAPTURE)) {
                    $violations[] = sprintf(
                        '%s: method %s() referenced in wp_ajax_%s not found',
                        $relPath,
                        $methodName,
                        $hookSuffix
                    );
                    continue;
                }

                $startPos = (int) $_[0][1];
                $methodBody = substr($content, $startPos, 2500);

                if (!str_contains($methodBody, "current_user_can( '" . self::REQUIRED_CAPABILITY . "' )")) {
                    $violations[] = sprintf(
                        '%s: %s() (wp_ajax_%s) does not check %s',
                        $relPath,
                        $methodName,
                        $hookSuffix,
                        self::REQUIRED_CAPABILITY
                    );
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Admin AJAX handlers missing " . self::REQUIRED_CAPABILITY . " check:\n" . implode("\n", $violations)
        );
    }

    /**
     * Admin controllers must consistently use manage_woocommerce.
     */
    #[Test]
    public function admin_controllers_use_correct_capability(): void
    {
        $violations = [];

        foreach (self::ADMIN_CONTROLLERS as $relPath) {
            $filePath = PLUGIN_DIR . '/' . $relPath;
            if (!file_exists($filePath)) {
                continue;
            }

            $content = file_get_contents($filePath);
            $lines = explode("\n", $content);
            foreach ($lines as $lineNum => $line) {
                if (str_contains($line, 'current_user_can') && str_contains($line, 'manage_')) {
                    if (!str_contains($line, self::REQUIRED_CAPABILITY)) {
                        $violations[] = sprintf(
                            '%s:%d: %s',
                            $relPath,
                            $lineNum + 1,
                            trim($line)
                        );
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Admin controllers have capability checks not using " . self::REQUIRED_CAPABILITY . ":\n"
            . implode("\n", $violations)
        );
    }

    /**
     * The resiPrint feed handler must require manage_woocommerce.
     */
    #[Test]
    public function resi_print_handler_requires_manage_woocommerce(): void
    {
        $filePath = PLUGIN_DIR . '/inc/Controllers/ShippingProcessController.php';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);

        preg_match('/function\s+resiPrint\s*\(/s', $content, $_, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($_, 'resiPrint() method not found in ShippingProcessController');
        $methodBody = substr($content, (int) $_[0][1], 2500);

        $this->assertStringContainsString(
            "current_user_can( '" . self::REQUIRED_CAPABILITY . "' )",
            $methodBody,
            'resiPrint() must check ' . self::REQUIRED_CAPABILITY . ' capability'
        );
    }

    // ------------------------------------------------------------------
    // Admin template inline capability checks
    // ------------------------------------------------------------------

    /**
     * Admin templates must have their own inline manage_woocommerce
     * check (defense-in-depth — not relying solely on menu registration).
     */
    #[Test]
    public function admin_templates_have_inline_capability_check(): void
    {
        $violations = [];

        foreach (self::ADMIN_TEMPLATES as $relPath) {
            $filePath = PLUGIN_DIR . '/' . $relPath;
            if (!file_exists($filePath)) {
                $violations[] = "{$relPath} — file not found";
                continue;
            }

            $content = file_get_contents($filePath);

            if (!str_contains($content, "current_user_can( '" . self::REQUIRED_CAPABILITY . "' )")) {
                $violations[] = "{$relPath} — missing " . self::REQUIRED_CAPABILITY . " check";
            }
        }

        $this->assertEmpty(
            $violations,
            "Admin templates without inline capability check:\n" . implode("\n", $violations)
        );
    }

    // ------------------------------------------------------------------
    // AdminPost capability check
    // ------------------------------------------------------------------

    /**
     * AdminPost::register() modifies WooCommerce options (checkout page,
     * COD, etc.) and must be guarded by manage_woocommerce.
     */
    #[Test]
    public function admin_post_register_requires_manage_woocommerce(): void
    {
        $filePath = PLUGIN_DIR . '/inc/Pages/AdminPost.php';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);

        preg_match('/function\s+register\s*\(/s', $content, $_, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($_, 'register() method not found in AdminPost');
        $methodBody = substr($content, (int) $_[0][1], 1500);

        $this->assertStringContainsString(
            "current_user_can( '" . self::REQUIRED_CAPABILITY . "' )",
            $methodBody,
            'AdminPost::register() must check ' . self::REQUIRED_CAPABILITY
        );
    }

    // ------------------------------------------------------------------
    // Menu registration capability
    // ------------------------------------------------------------------

    /**
     * All admin menu / submenu page registrations must use manage_woocommerce.
     */
    #[Test]
    public function menu_pages_require_manage_woocommerce(): void
    {
        $filePath = PLUGIN_DIR . '/inc/Pages/Admin.php';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);

        preg_match_all(
            "/['\"]capability['\"]\s*=>\s*['\"]([^'\"]+)['\"]/",
            $content,
            $matches
        );

        $this->assertNotEmpty($matches[1], 'No capability entries found in Admin.php');

        foreach ($matches[1] as $capability) {
            $this->assertSame(
                self::REQUIRED_CAPABILITY,
                $capability,
                "Menu page uses '{$capability}' — should be '" . self::REQUIRED_CAPABILITY . "'"
            );
        }
    }

    // ------------------------------------------------------------------
    // Guest-facing endpoints must NOT use manage_woocommerce
    // ------------------------------------------------------------------

    /**
     * Endpoints registered with wp_ajax_nopriv_ are guest-facing and
     * should NOT have a manage_woocommerce check (that would block guests).
     */
    #[Test]
    public function nopriv_handlers_are_not_blocked_by_capability_check(): void
    {
        $guestControllers = [
            'inc/Controllers/GeneralAjaxController.php',
            'inc/Controllers/CheckoutController.php',
            'inc/Controllers/TrackingFrontPageController.php',
        ];

        foreach ($guestControllers as $relPath) {
            $filePath = PLUGIN_DIR . '/' . $relPath;
            if (!file_exists($filePath)) {
                continue;
            }

            $content = file_get_contents($filePath);

            preg_match_all(
                '/add_action\s*\(\s*[\'"]wp_ajax_nopriv_[a-zA-Z0-9_-]+[\'"].*?[\'"](\w+)[\'"]\s*\)/',
                $content,
                $matches
            );

            foreach ($matches[1] as $methodName) {
                $methodPattern = '/function\s+' . preg_quote($methodName, '/') . '\s*\(/';
                if (!preg_match($methodPattern, $content, $_, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                $methodBody = substr($content, (int) $_[0][1], 2500);

                $this->assertStringNotContainsString(
                    self::REQUIRED_CAPABILITY,
                    $methodBody,
                    "{$relPath}: {$methodName}() is a nopriv handler but checks " . self::REQUIRED_CAPABILITY . " — this would block guest users"
                );
            }
        }
    }
}
