<?php

use PHPUnit\Framework\TestCase;

final class KiriofCheckboxTest extends TestCase {
	public function test_shared_checkbox_keeps_native_primitive_and_theme_tokens(): void {
		$source = file_get_contents( PLUGIN_DIR . '/src/lib/ui/KiriofCheckbox.svelte' );
		foreach ( array( "from 'bits-ui'", 'bind:ref', 'bind:checked', 'bind:indeterminate', '{...restProps}', 'data-slot="checkbox"', 'data-kiriof-checkbox', '!size-4', '!min-h-4', '!min-w-4', 'data-[state=checked]:!bg-primary', 'data-[state=indeterminate]:!bg-primary', '!text-primary-foreground', 'focus-visible:ring-2', 'disabled:opacity-50' ) as $contract ) {
			$this->assertStringContainsString( $contract, $source );
		}
		$this->assertStringNotContainsString( 'bg-black', $source );
		$this->assertStringNotContainsString( 'bg-foreground', $source );
		$this->assertStringNotContainsString( 'text-white', $source );
		$this->assertStringNotContainsString( 'data-checked:', $source );
		$this->assertStringNotContainsString( 'data-indeterminate:', $source );
	}

	public function test_animation_changes_only_persistent_indicator_opacity_and_transform(): void {
		$source = file_get_contents( PLUGIN_DIR . '/src/lib/ui/KiriofCheckbox.svelte' );
		$this->assertStringContainsString( 'checked && !indeterminate', $source );
		$this->assertStringContainsString( "indeterminate ? 'scale-100 opacity-100'", $source );
		$this->assertStringContainsString( 'transition-[opacity,transform]', $source );
		$this->assertStringContainsString( 'motion-reduce:transition-none', $source );
		$this->assertStringContainsString( 'aria-hidden="true"', $source );
		$this->assertSame( 2, substr_count( $source, 'stroke={3}' ) );
		$this->assertStringNotContainsString( '{#if', $source, 'Persistent icons allow entering and leaving states to transition without changing layout.' );
		$this->assertStringNotContainsString( 'transition-all', $source );
	}

	public function test_all_admin_consumers_use_the_shared_control_without_table_color_overrides(): void {
		foreach ( array( 'src/lib/transactions/TransactionsApp.svelte', 'src/lib/transactions/TransactionActionDialogs.svelte', 'src/lib/ui/KiriofMultiFilter.svelte' ) as $file ) {
			$source = file_get_contents( PLUGIN_DIR . '/' . $file );
			$this->assertStringContainsString( "import KiriofCheckbox from '\$lib/ui/KiriofCheckbox.svelte'", $source );
			$this->assertStringContainsString( '<KiriofCheckbox', $source );
			$this->assertStringNotContainsString( '<Checkbox', $source );
		}
		$styles = file_get_contents( PLUGIN_DIR . '/src/styles/admin-list.css' );
		$this->assertStringNotContainsString( ".kiriof-transactions-app [data-slot='checkbox']", $styles );
		$this->assertStringNotContainsString( '!border-foreground !bg-foreground', $styles );
		$legacy = file_get_contents( PLUGIN_DIR . '/src/lib/components/ui/checkbox/checkbox.svelte' );
		$this->assertStringContainsString( '<KiriofCheckbox bind:ref bind:checked bind:indeterminate {...restProps}', $legacy );
	}

	public function test_table_select_all_has_a_label_and_real_partial_selection_state(): void {
		$source = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' );
		$this->assertStringContainsString( 'const someSelected = $derived(selectedRows.length > 0 && !allSelected)', $source );
		$this->assertSame( 2, substr_count( $source, 'indeterminate={someSelected}' ) );
		$this->assertSame( 2, substr_count( $source, 'aria-label={bootstrap.i18n.selectAll' ) );
		$this->assertSame( 2, substr_count( $source, 'disabled={selectableRows.length === 0}' ) );
	}
}
