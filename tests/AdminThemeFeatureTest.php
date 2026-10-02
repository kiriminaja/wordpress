<?php

use PHPUnit\Framework\TestCase;

final class AdminThemeFeatureTest extends TestCase {
	public function test_shared_design_system_loads_built_theme_script_with_file_version(): void {
		$source = file_get_contents( PLUGIN_DIR . '/inc/Base/Enqueue.php' );
		$start = strpos( $source, 'private function enqueue_kiriof_design_system' );
		$end = strpos( $source, 'private function enqueue_workspace_style', $start );
		$method = substr( $source, $start, $end - $start );
		$this->assertStringContainsString( "'kiriof-admin-theme'", $method );
		$this->assertStringContainsString( 'assets/admin/dist/kiriminaja-admin-theme.js', $method );
		$this->assertStringContainsString( 'file_exists( $theme_script )', $method );
		$this->assertStringContainsString( 'filemtime( $theme_script )', $method );
		$this->assertStringContainsString( "wp_script_add_data( 'kiriof-admin-theme', 'type', 'module' )", $method );
		$config = file_get_contents( PLUGIN_DIR . '/vite.config.ts' );
		$this->assertStringContainsString( "'admin-theme': 'src/entries/admin-theme.ts'", $config );
	}
}
