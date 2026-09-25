<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class KiriofDialogContractTest extends TestCase
{
    #[Test]
    public function shared_dialog_requires_title_and_primary_action_and_supports_optional_parts(): void
    {
        $dialog = file_get_contents( PLUGIN_DIR . '/src/lib/ui/KiriofDialog.svelte' );

        $this->assertStringContainsString( 'title: string;', $dialog );
        $this->assertStringContainsString( 'primaryLabel: string;', $dialog );
        $this->assertStringContainsString( 'onPrimary: () => void | Promise<void>;', $dialog );
        $this->assertStringContainsString( 'description?: string;', $dialog );
        $this->assertStringContainsString( 'secondaryLabel?: string;', $dialog );
        $this->assertStringContainsString( '<Dialog.Title>{title}</Dialog.Title>', $dialog );
        $this->assertStringContainsString( 'primaryVariant', $dialog );
        $this->assertStringContainsString( '!z-[100001]', file_get_contents( PLUGIN_DIR . '/src/lib/components/ui/dialog/dialog-content.svelte' ) );
    }

    #[Test]
    public function transaction_action_dialogs_use_the_shared_dialog_contract(): void
    {
        $actions = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionActionDialogs.svelte' );

        $this->assertStringContainsString( 'KiriofDialog', $actions );
        $this->assertStringNotContainsString( '<Dialog.Content', $actions );
        $this->assertStringNotContainsString( '<Dialog.Footer', $actions );
        $this->assertStringContainsString( 'primaryVariant="destructive"', $actions );
        $this->assertStringContainsString( 'onOpenChange={(open) => !open && close()}', $actions );
    }
}
