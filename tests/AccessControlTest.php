<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Validates that plugin configuration endpoints and admin pages
 * are restricted to administrator users (manage_options capability).
 *
 * These are static-analysis tests — they parse the source files without
 * loading WordPress — following the same pattern as SecurityValidationTest.
 */
final class AccessControlTest extends TestCase
{
    /** Controller files that handle admin-only AJAX actions. */
    private const ADMIN_CONTROLLERS = [
        'inc/Controllers/SettingController.php',
        'inc/Controllers/ShippingProcessController.php',
        'inc/Controllers/TransactionProcessController.php',
    ];

    /** Template files that render admin-only pages. */
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
     * Every admin AJAX handler must check for both manage_options
     * AND manage_woocommerce capabilities.
     */
    #[Test]
    public function admin_ajax_handlers_require_manage_options_and_manage_woocommerce(): void
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

                // Locate the method body (first ~2500 chars should be enough)
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

                if (!str_contains($methodBody, "current_user_can( 'manage_options' )")) {
                    $violations[] = sprintf(
                        '%s: %s() (wp_ajax_%s) does not check manage_options',
                        $relPath,
                        $methodName,
                        $hookSuffix
                    );
                }

                if (!str_contains($methodBody, "current_user_can( 'manage_woocommerce' )")) {
                    $violations[] = sprintf(
                        '%s: %s() (wp_ajax_%s) does not check manage_woocommerce',
                        $relPath,
                        $methodName,
                        $hookSuffix
                    );
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Admin AJAX handlers missing capability checks:\n" . implode("\n", $violations)
        );
    }

    /**
     * Admin controllers must use BOTH manage_options and manage_woocommerce.
     * Using only manage_woocommerce would grant access to Shop Managers.
     * Using only manage_options would skip WooCommerce permission checks.
     */
    #[Test]
    public function admin_controllers_require_both_capabilities(): void
    {
        $violations = [];

        foreach (self::ADMIN_CONTROLLERS as $relPath) {
            $filePath = PLUGIN_DIR . '/' . $relPath;
            if (!file_exists($filePath)) {
                continue;
            }

            $content = file_get_contents($filePath);

            // Every capability check line must contain both capabilities
            $lines = explode("\n", $content);
            foreach ($lines as $lineNum => $line) {
                // Find lines that perform a capability check
                if (str_contains($line, 'current_user_can') && str_contains($line, 'manage_')) {
                    $hasOptions = str_contains($line, "manage_options");
                    $hasWoo = str_contains($line, "manage_woocommerce");

                    if ($hasOptions && !$hasWoo) {
                        $violations[] = sprintf(
                            '%s:%d → checks manage_options but not manage_woocommerce: %s',
                            $relPath,
                            $lineNum + 1,
                            trim($line)
                        );
                    }
                    if ($hasWoo && !$hasOptions) {
                        $violations[] = sprintf(
                            '%s:%d → checks manage_woocommerce but not manage_options: %s',
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
            "Admin controllers must check BOTH manage_options and manage_woocommerce:\n"
            . implode("\n", $violations)
        );
    }

    /**
     * The resiPrint feed handler must require both manage_options
     * and manage_woocommerce.
     */
    #[Test]
    public function resi_print_handler_requires_both_capabilities(): void
    {
        $filePath = PLUGIN_DIR . '/inc/Controllers/ShippingProcessController.php';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);

        preg_match('/function\s+resiPrint\s*\(/s', $content, $_, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($_, 'resiPrint() method not found in ShippingProcessController');
        $methodBody = substr($content, (int) $_[0][1], 2500);

        $this->assertStringContainsString(
            "current_user_can( 'manage_options' )",
            $methodBody,
            'resiPrint() must check manage_options capability'
        );
        $this->assertStringContainsString(
            "current_user_can( 'manage_woocommerce' )",
            $methodBody,
            'resiPrint() must check manage_woocommerce capability'
        );
    }

    // ------------------------------------------------------------------
    // Admin template inline capability checks
    // ------------------------------------------------------------------

    /**
     * Admin templates must have their own inline manage_options and
     * manage_woocommerce checks (defense-in-depth).
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

            if (!str_contains($content, "current_user_can( 'manage_options' )")) {
                $violations[] = "{$relPath} — missing manage_options check";
            }
            if (!str_contains($content, "current_user_can( 'manage_woocommerce' )")) {
                $violations[] = "{$relPath} — missing manage_woocommerce check";
            }
        }

        $this->assertEmpty(
            $violations,
            "Admin templates without dual capability check:\n" . implode("\n", $violations)
        );
    }

    // ------------------------------------------------------------------
    // AdminPost capability check
    // ------------------------------------------------------------------

    /**
     * AdminPost::register() modifies WooCommerce options and must be
     * guarded by both manage_options and manage_woocommerce.
     */
    #[Test]
    public function admin_post_register_requires_both_capabilities(): void
    {
        $filePath = PLUGIN_DIR . '/inc/Pages/AdminPost.php';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);

        preg_match('/function\s+register\s*\(/s', $content, $_, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($_, 'register() method not found in AdminPost');
        $methodBody = substr($content, (int) $_[0][1], 1500);

        $this->assertStringContainsString(
            "current_user_can( 'manage_options' )",
            $methodBody,
            'AdminPost::register() must check manage_options'
        );
        $this->assertStringContainsString(
            "current_user_can( 'manage_woocommerce' )",
            $methodBody,
            'AdminPost::register() must check manage_woocommerce'
        );
    }

    // ------------------------------------------------------------------
    // Menu registration capability
    // ------------------------------------------------------------------

    /**
     * All admin menu / submenu page registrations must use manage_options.
     */
    #[Test]
    public function menu_pages_require_manage_options(): void
    {
        $filePath = PLUGIN_DIR . '/inc/Pages/Admin.php';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);

        // Find all 'capability' => '...' entries
        preg_match_all(
            "/['\"]capability['\"]\s*=>\s*['\"]([^'\"]+)['\"]/",
            $content,
            $matches
        );

        $this->assertNotEmpty($matches[1], 'No capability entries found in Admin.php');

        foreach ($matches[1] as $capability) {
            $this->assertSame(
                'manage_options',
                $capability,
                "Menu page uses '{$capability}' capability — should be 'manage_options'"
            );
        }
    }

    // ------------------------------------------------------------------
    // Guest-facing endpoints must NOT use manage_options
    // ------------------------------------------------------------------

    /**
     * Endpoints registered with wp_ajax_nopriv_ are guest-facing and
     * should NOT have a manage_options check (that would block guests).
     */
    #[Test]
    public function nopriv_handlers_are_not_blocked_by_manage_options(): void
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

            // Collect nopriv handler method names
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
                    "manage_options",
                    $methodBody,
                    "{$relPath} → {$methodName}() is a nopriv handler but checks manage_options — this would block guest users"
                );
            }
        }
    }
}
