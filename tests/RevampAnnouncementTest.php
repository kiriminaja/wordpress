<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RevampAnnouncementTest extends TestCase
{
    #[Test]
    public function announcement_service_is_registered_and_toolbar_contract_matches_svelte(): void
    {
        $service = file_get_contents(PLUGIN_DIR . '/inc/Services/RevampAnnouncementService.php');
        $init = file_get_contents(PLUGIN_DIR . '/inc/Init.php');

        $this->assertStringContainsString('Services\\RevampAnnouncementService::class', $init);
        $this->assertStringContainsString("public const ANNOUNCEMENT_VERSION = '2.4.0'", $service);
        $this->assertStringContainsString('kiriof_revamp_announcement_seen_version', $service);
        $this->assertStringContainsString("wp_ajax_kiriof_dismiss_revamp_announcement", $service);
        $this->assertStringContainsString('wordpress.org/plugins/kiriminaja-official/#reviews', $service);
        $this->assertStringContainsString('Rate KiriminAja', $service);
        $this->assertStringContainsString('Continue to KiriminAja', $service);
        $this->assertStringNotContainsString('80%', $service);
        $this->assertStringContainsString('attach_announcement', $service);

        foreach (array(
            'inc/Services/SettingsPageData.php',
            'inc/Services/TransactionListRenderService.php',
            'inc/Services/PaymentListRenderService.php',
            'inc/Services/PickupDetailPageData.php',
        ) as $bootstrap_file) {
            $this->assertStringContainsString(
                'RevampAnnouncementService::attach_announcement',
                file_get_contents(PLUGIN_DIR . '/' . $bootstrap_file),
                $bootstrap_file . ' must attach the revamp announcement to the toolbar.',
            );
        }

        $toolbar = file_get_contents(PLUGIN_DIR . '/src/lib/ui/toolbar.ts');
        $this->assertStringContainsString('ToolbarAnnouncement', $toolbar);
        $this->assertStringContainsString('feedbackUrl', $toolbar);
        $this->assertStringContainsString('continueLabel', $toolbar);

        $dialog = file_get_contents(PLUGIN_DIR . '/src/lib/ui/RevampAnnouncementDialog.svelte');
        $this->assertStringContainsString('Dialog.Root', $dialog);
        $this->assertStringContainsString('Dialog.Content', $dialog);
        $this->assertStringContainsString('Dialog.Title', $dialog);
        $this->assertStringContainsString('Dialog.Description', $dialog);
        $this->assertStringContainsString('Dialog.Footer', $dialog);
        $this->assertStringContainsString('kiriof_dismiss_revamp_announcement', $dialog);
        $this->assertStringContainsString('announcement.feedbackUrl', $dialog);
        $this->assertStringContainsString('announcement.continueLabel', $dialog);
        $this->assertSame(2, substr_count($dialog, '<Button'));

        $shell = file_get_contents(PLUGIN_DIR . '/src/lib/ui/Toolbar.svelte');
        $this->assertStringContainsString('RevampAnnouncementDialog', $shell);
        $this->assertStringContainsString('toolbar.announcement', $shell);

        $this->assertFileExists(PLUGIN_DIR . '/assets/admin/img/revamp-announcement.svg');
        $content = file_get_contents(PLUGIN_DIR . '/src/lib/components/ui/dialog/dialog-content.svelte');
        $overlay = file_get_contents(PLUGIN_DIR . '/src/lib/components/ui/dialog/dialog-overlay.svelte');
        $this->assertStringContainsString('z-50', $content);
        $this->assertStringContainsString('z-40', $overlay);
    }
}
