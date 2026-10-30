<?php

use KiriminAjaOfficial\Services\WorkspaceAssets;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
	}
}

final class WorkspaceAssetsRuntimeTest extends TestCase {
	private string $manifest_path;

	public function test_enqueue_hook_outputs_the_selected_production_graph_with_matching_entry_version(): void {
		$manifest_path = PLUGIN_DIR . '/assets/admin/dist/workspace-manifest.json';
		if ( ! is_file( $manifest_path ) ) {
			$this->markTestSkipped( 'Run make zip before package verification.' );
		}
		$modules = ( new WorkspaceAssets() )->modules( $manifest_path, 'kiriminaja-request-pickup' );
		$fixture = PLUGIN_DIR . '/tests/fixtures/workspace-preload-runtime.php';
		$output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $fixture ) . ' kiriminaja-request-pickup yes' );
		$base = 'https://fixture.test/shop/wp-content/plugins/kiriminaja-official/assets/admin/dist/';
		$expected = '';
		foreach ( $modules as $file ) {
			$url = $base . $file;
			if ( 'kiriminaja-admin-workspace.js' === $file ) {
				$url .= '?ver=' . filemtime( PLUGIN_DIR . '/assets/admin/dist/' . $file );
			}
			$expected .= '<link rel="modulepreload" crossorigin href="' . $url . '">' . "\n";
		}
		self::assertNotEmpty( $modules );
		self::assertSame( $expected, $output );
		self::assertSame( '', (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $fixture ) . ' kiriminaja-request-pickup no' ) );
		self::assertSame( '', (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $fixture ) . ' unrelated yes' ) );
	}

	protected function setUp(): void {
		parent::setUp();
		$path = tempnam( sys_get_temp_dir(), 'kiriof-workspace-' );
		self::assertNotFalse( $path );
		$this->manifest_path = $path;
	}

	public function test_invalid_imports_field_is_ignored(): void {
		$this->write_manifest( array( 'src/entries/admin-workspace.ts' => array( 'file' => 'assets/workspace.js', 'imports' => 'invalid' ) ) );
		self::assertSame( array( 'assets/workspace.js' ), $this->modules() );
	}

	protected function tearDown(): void {
		if ( is_file( $this->manifest_path ) ) {
			unlink( $this->manifest_path );
		}
		parent::tearDown();
	}

	public function test_static_imports_are_recursive_unique_and_cycle_safe(): void {
		$this->write_manifest(
			array(
				'src/entries/admin-workspace.ts' => array(
					'file'    => 'assets/workspace.js',
					'imports' => array( '_shared.js', '_alias.js', '_missing.js', 12 ),
				),
				'_shared.js' => array(
					'file'    => 'assets/shared.js',
					'imports' => array( '_nested.js' ),
				),
				'_nested.js' => array(
					'file'    => 'assets/nested.js',
					'imports' => array( '_shared.js', 'src/entries/admin-workspace.ts' ),
				),
				'_alias.js' => array( 'file' => 'assets/shared.js' ),
				'src/lib/transactions/TransactionsApp.svelte' => array(
					'file'    => 'assets/transactions.js',
					'imports' => array( '_shared.js', '_nested.js' ),
				),
			)
		);

		self::assertSame(
			array( 'assets/workspace.js', 'assets/shared.js', 'assets/nested.js', 'assets/transactions.js' ),
			$this->modules()
		);
	}

	public function test_dynamic_imports_and_unrelated_routes_are_not_preloaded(): void {
		$this->write_manifest(
			array(
				'src/entries/admin-workspace.ts' => array(
					'file'           => 'assets/workspace.js',
					'dynamicImports' => array( 'src/lib/transactions/TransactionsApp.svelte', 'src/lib/payments/PaymentsList.svelte' ),
				),
				'src/lib/transactions/TransactionsApp.svelte' => array(
					'file'           => 'assets/transactions.js',
					'imports'        => array( '_shared.js' ),
					'dynamicImports' => array( '_lazy.js' ),
				),
				'_shared.js' => array(
					'file'           => 'assets/shared.js',
					'dynamicImports' => array( '_lazy.js' ),
				),
				'_lazy.js' => array( 'file' => 'assets/lazy.js' ),
				'src/lib/payments/PaymentsList.svelte' => array( 'file' => 'assets/payments.js' ),
			)
		);

		self::assertSame(
			array( 'assets/workspace.js', 'assets/transactions.js', 'assets/shared.js' ),
			$this->modules()
		);
	}

	#[DataProvider( 'route_cases' )]
	public function test_each_page_and_settings_section_selects_only_its_route( string $page, string $section, string $expected ): void {
		$manifest = array(
			'src/entries/admin-workspace.ts' => array( 'file' => 'assets/workspace.js' ),
			'src/lib/transactions/TransactionsApp.svelte' => array( 'file' => 'assets/transactions.js' ),
			'src/lib/transaction-detail/TransactionDetail.svelte' => array( 'file' => 'assets/transaction-detail.js' ),
			'src/lib/payments/PaymentsList.svelte' => array( 'file' => 'assets/payments.js' ),
			'src/lib/pickup-detail/PickupDetail.svelte' => array( 'file' => 'assets/pickup-detail.js' ),
			'src/lib/SettingsRoot.svelte' => array( 'file' => 'assets/settings.js' ),
			'src/lib/settings/AccountSection.svelte' => array( 'file' => 'assets/account.js' ),
			'src/lib/settings/CouriersSection.svelte' => array( 'file' => 'assets/couriers.js' ),
			'src/lib/settings/TrackingSection.svelte' => array( 'file' => 'assets/tracking.js' ),
			'src/lib/settings/TechnicalSection.svelte' => array( 'file' => 'assets/technical.js' ),
		);
		$this->write_manifest( $manifest );

		self::assertSame( array( 'assets/workspace.js', $expected ), $this->modules( $page, $section ) );
	}

	public static function route_cases(): array {
		return array(
			'transactions'           => array( 'kiriminaja-transaction', '', 'assets/transactions.js' ),
			'transaction detail'     => array( 'kiriminaja-transaction-detail', '', 'assets/transaction-detail.js' ),
			'pickup list'            => array( 'kiriminaja-request-pickup', '', 'assets/payments.js' ),
			'pickup detail'          => array( 'kiriminaja-request-pickup-detail', '', 'assets/pickup-detail.js' ),
			'settings default'       => array( 'kiriminaja-setting', '', 'assets/settings.js' ),
			'settings unknown'       => array( 'kiriminaja-setting', 'unknown', 'assets/settings.js' ),
			'settings account'       => array( 'kiriminaja-setting', 'account', 'assets/account.js' ),
			'settings couriers'      => array( 'kiriminaja-setting', 'couriers', 'assets/couriers.js' ),
			'settings tracking'      => array( 'kiriminaja-setting', 'tracking', 'assets/tracking.js' ),
			'settings technical'     => array( 'kiriminaja-setting', 'technical', 'assets/technical.js' ),
			'settings cache alias'   => array( 'kiriminaja-setting', 'cache', 'assets/technical.js' ),
			'settings webhook alias' => array( 'kiriminaja-setting', 'webhooks', 'assets/technical.js' ),
			'normalized cache'       => array( 'kiriminaja-setting', 'CaChE', 'assets/technical.js' ),
			'normalized webhook'     => array( 'kiriminaja-setting', 'WEB!HOOKS', 'assets/technical.js' ),
			'non-settings section'   => array( 'kiriminaja-request-pickup-detail', 'account', 'assets/pickup-detail.js' ),
		);
	}

	public function test_unknown_page_returns_no_modules_even_with_workspace_entry(): void {
		$this->write_manifest( array( 'src/entries/admin-workspace.ts' => array( 'file' => 'assets/workspace.js' ) ) );

		self::assertSame( array(), $this->modules( 'unrelated-admin-page' ) );
		self::assertSame( array(), $this->modules( '' ) );
	}

	public function test_missing_manifest_returns_no_modules(): void {
		unlink( $this->manifest_path );

		self::assertSame( array(), $this->modules() );
	}

	#[DataProvider( 'invalid_manifest_cases' )]
	public function test_invalid_or_empty_manifest_returns_no_modules( string $contents ): void {
		self::assertNotFalse( file_put_contents( $this->manifest_path, $contents ) );

		self::assertSame( array(), $this->modules() );
	}

	public static function invalid_manifest_cases(): array {
		return array(
			'empty file'        => array( '' ),
			'truncated JSON'    => array( '{"src/entries/admin-workspace.ts":' ),
			'JSON null'         => array( 'null' ),
			'JSON string'       => array( '"manifest"' ),
			'JSON number'       => array( '42' ),
			'empty object'      => array( '{}' ),
			'empty array'       => array( '[]' ),
			'missing entries'   => array( '{"unrelated":{"file":"assets/other.js"}}' ),
			'non-array entries' => array( '{"src/entries/admin-workspace.ts":false,"src/lib/transactions/TransactionsApp.svelte":"assets/invalid.js"}' ),
		);
	}

	#[DataProvider( 'unsafe_file_cases' )]
	public function test_unsafe_or_non_javascript_files_are_omitted_without_losing_safe_dependencies( mixed $file ): void {
		$this->write_manifest(
			array(
				'src/entries/admin-workspace.ts' => array(
					'file'    => $file,
					'imports' => array( '_safe.js' ),
				),
				'_safe.js' => array( 'file' => 'assets/safe_chunk-123.js' ),
			)
		);

		self::assertSame( array( 'assets/safe_chunk-123.js' ), $this->modules() );
	}

	public static function unsafe_file_cases(): array {
		return array(
			'parent directory'     => array( '../outside.js' ),
			'nested traversal'     => array( 'assets/../outside.js' ),
			'encoded traversal'    => array( 'assets/%2e%2e/outside.js' ),
			'remote HTTPS'         => array( 'https://example.com/module.js' ),
			'remote HTTP'          => array( 'http://example.com/module.js' ),
			'protocol relative'    => array( '//example.com/module.js' ),
			'dotless remote host'  => array( '//localhost/module.js' ),
			'absolute path'        => array( '/assets/module.js' ),
			'double quote'         => array( 'assets/evil"module.js' ),
			'single quote'         => array( "assets/evil'module.js" ),
			'HTML attribute'       => array( 'assets/module.js" onload="alert(1)' ),
			'backslash'            => array( 'assets\\module.js' ),
			'query string'         => array( 'assets/module.js?version=1' ),
			'fragment'             => array( 'assets/module.js#fragment' ),
			'stylesheet'           => array( 'assets/module.css' ),
			'trailing newline'     => array( "assets/module.js\n" ),
			'empty string'         => array( '' ),
			'null file'            => array( null ),
			'numeric file'         => array( 123 ),
			'array file'           => array( array( 'assets/module.js' ) ),
		);
	}

	private function write_manifest( array $manifest ): void {
		self::assertNotFalse( file_put_contents( $this->manifest_path, json_encode( $manifest, JSON_THROW_ON_ERROR ) ) );
	}

	private function modules( string $page = 'kiriminaja-transaction', string $section = '' ): array {
		return ( new WorkspaceAssets() )->modules( $this->manifest_path, $page, $section );
	}
}
