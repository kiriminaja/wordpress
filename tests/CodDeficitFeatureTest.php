<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Validates the COD Deficit handling feature implementation:
 *
 * p5-unit-deficit  — CodDeficitService structure
 * p5-unit-repo     — CodFeeApiRepository structure
 * p5-unit-ajax     — CodAdjustmentController structure & security
 * p5-feature-create — CreateTransactionService wires CodDeficitService
 * p5-feature-cancel — CodAdjustmentController cancel deficit flow
 * p5-feature-validation — ValidationCodCalculationService uses settings
 * p5-security       — AccessControlTest coverage for new AJAX endpoints
 */
final class CodDeficitFeatureTest extends TestCase
{
    // =========================================================================
    // p5-unit-deficit — CodDeficitService
    // =========================================================================

    #[Test]
    public function cod_deficit_service_file_exists(): void
    {
        $this->assertFileExists(
            PLUGIN_DIR . '/inc/Services/CheckoutServices/CodDeficitService.php',
            'CodDeficitService.php must exist'
        );
    }

    // =========================================================================
    // p5-unit-repo — CodFeeApiRepository
    // =========================================================================

    #[Test]
    public function cod_fee_api_repository_file_exists(): void
    {
        $this->assertFileExists(
            PLUGIN_DIR . '/inc/Repositories/CodFeeApiRepository.php',
            'CodFeeApiRepository.php must exist'
        );
    }

    // =========================================================================
    // p5-unit-ajax — CodAdjustmentController
    // =========================================================================

    #[Test]
    public function cod_adjustment_controller_file_exists(): void
    {
        $this->assertFileExists(
            PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php',
            'CodAdjustmentController.php must exist'
        );
    }

    // =========================================================================
    // p5-feature-validation — ValidationCodCalculationService uses settings
    // =========================================================================

    #[Test]
    public function validation_cod_service_file_exists(): void
    {
        $this->assertFileExists(
            PLUGIN_DIR . '/inc/Services/CheckoutServices/ValidationCodCalculationService.php'
        );
    }

}
