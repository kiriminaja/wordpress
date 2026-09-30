<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class KiriofFieldComponentsTest extends TestCase
{
    #[Test]
    public function shared_field_wrappers_support_prefix_suffix_and_formatted_numeric_input(): void
    {
        $input = file_get_contents( PLUGIN_DIR . '/src/lib/ui/KiriofInput.svelte' );
        $select = file_get_contents( PLUGIN_DIR . '/src/lib/ui/KiriofSelect.svelte' );
        $combobox = file_get_contents( PLUGIN_DIR . '/src/lib/ui/KiriofCombobox.svelte' );

        $this->assertStringContainsString( 'prefix?:', $input );
        $this->assertStringContainsString( 'suffix?:', $input );
        $this->assertStringContainsString( 'formatNumber', $input );
        $this->assertStringContainsString( 'Intl.NumberFormat', $input );
        $this->assertStringContainsString( 'prefix?:', $select );
        $this->assertStringContainsString( 'suffix?:', $select );
        $this->assertStringContainsString( 'prefix?:', $combobox );
        $this->assertStringContainsString( 'suffix?:', $combobox );
    }

    #[Test]
    public function affected_flows_use_shared_field_components(): void
    {
        $cod = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionActionDialogs.svelte' );
        $pickup = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/RequestPickupDialog.svelte' );

        $this->assertStringContainsString( 'KiriofInput', $cod );
        $this->assertStringContainsString( 'formatNumber', $cod );
        $this->assertStringContainsString( 'KiriofSelect', $pickup );
        $this->assertStringNotContainsString( 'Select.Trigger id="kiriof-pickup-date"', $pickup );
        $this->assertStringNotContainsString( 'Select.Trigger id="kiriof-pickup-time"', $pickup );
    }
}
