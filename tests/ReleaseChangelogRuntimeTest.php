<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/scripts/release-notes.php';

final class ReleaseChangelogRuntimeTest extends TestCase {
	private string $root;
	private string $body = "## What's Changed\n* fix(checkout): restrict rates to Indonesia by @yan-ad in https://github.com/kiriminaja/wordpress/pull/282\n* feat(couriers): select services by @yan-ad in https://github.com/kiriminaja/wordpress/pull/281\n\n**Full Changelog**: https://github.com/kiriminaja/wordpress/compare/v2.4.2...v2.4.3";

	protected function setUp(): void {
		$this->root = sys_get_temp_dir() . '/kiriof-changelog-' . bin2hex( random_bytes( 8 ) );
		mkdir( $this->root . '/scripts', 0777, true );
		mkdir( $this->root . '/bin' );
		foreach ( array( 'changelog.php', 'release-notes.php' ) as $script ) {
			copy( PLUGIN_DIR . '/scripts/' . $script, $this->root . '/scripts/' . $script );
		}

		file_put_contents( $this->root . '/readme.txt', "=== Plugin ===\nStable tag: 2.4.2\n\n== Changelog ==\n= 2.4.2 =\n- Previous entry\n\n== Upgrade Notice ==\n= 2.4.2 =\nPrevious notice\n" );
		file_put_contents( $this->root . '/kiriminaja.php', "<?php\n/**\n * Version:         2.4.2\n */\ndefine( 'KIRIOF_VERSION', '2.4.2' );\n" );
		$stub = '#!' . PHP_BINARY . "\n<?php\nfile_put_contents(getcwd() . '/gh-args.json', json_encode(array_slice(\$argv, 1)));\nif (getenv('KIRIOF_TEST_GH_FAIL')) { fwrite(STDERR, 'API unavailable'); exit(1); }\necho getenv('KIRIOF_TEST_NOTES');\n";
		file_put_contents( $this->root . '/bin/gh', $stub );
		chmod( $this->root . '/bin/gh', 0755 );
		$output = array();
		$status = 0;
		exec( 'git -C ' . escapeshellarg( $this->root ) . ' init -q && git -C ' . escapeshellarg( $this->root ) . ' -c user.name=Test -c user.email=test@example.com commit -q --allow-empty -m initial 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
	}

	protected function tearDown(): void {
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $files as $file ) {
			$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
		}
		rmdir( $this->root );
	}

	private function run_script( string $script, array $arguments, bool $fail = false, ?string $body = null ): array {
		$command = 'PATH=' . escapeshellarg( $this->root . '/bin:' . getenv( 'PATH' ) );
		$command .= ' KIRIOF_TEST_NOTES=' . escapeshellarg( $body ?? $this->body );
		$command .= ' KIRIOF_TEST_GH_FAIL=' . escapeshellarg( $fail ? '1' : '' );
		$command .= ' ' . escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $this->root . '/scripts/' . $script );
		foreach ( $arguments as $argument ) {
			$command .= ' ' . escapeshellarg( $argument );
		}
		$output = array();
		$status = 0;
		exec( $command . ' 2>&1', $output, $status );
		return array( $status, implode( "\n", $output ) );
	}

	public function test_generated_pr_notes_update_release_versions_and_are_reused_verbatim(): void {
		list( $status, $output ) = $this->run_script( 'changelog.php', array() );
		$this->assertSame( 0, $status, $output );
		$readme = file_get_contents( $this->root . '/readme.txt' );
		$notes = kiriof_normalize_release_notes( $this->body );
		$this->assertSame( $notes, kiriof_readme_release_notes( $readme, '2.4.3' ) );
		$this->assertStringContainsString( 'Stable tag: 2.4.3', $readme );
		$this->assertStringContainsString( "define( 'KIRIOF_VERSION', '2.4.3' )", file_get_contents( $this->root . '/kiriminaja.php' ) );
		$this->assertStringContainsString( '* Version:         2.4.3', file_get_contents( $this->root . '/kiriminaja.php' ) );
		$this->assertSame( '- Previous entry', kiriof_readme_release_notes( $readme, '2.4.2' ) );
		$args = json_decode( file_get_contents( $this->root . '/gh-args.json' ), true );
		$this->assertContains( 'previous_tag_name=v2.4.2', $args );
		$this->assertContains( 'tag_name=v2.4.3', $args );
		$this->assertMatchesRegularExpression( '/target_commitish=[a-f0-9]{40}/', implode( ' ', $args ) );
		list( $status, $output ) = $this->run_script( 'release-notes.php', array( 'v2.4.3' ) );
		$this->assertSame( 0, $status, $output );
		$this->assertSame( "## What's Changed\n\n" . $notes, $output );
		$this->run_script( 'changelog.php', array( 'v2.4.3' ), true );
		$this->assertSame( $readme, file_get_contents( $this->root . '/readme.txt' ) );
	}

	public function test_previous_tag_override_and_minor_bump_are_supported(): void {
		list( $status, $output ) = $this->run_script( 'changelog.php', array( '', 'v2.4.0', 'minor' ) );
		$this->assertSame( 0, $status, $output );
		$args = json_decode( file_get_contents( $this->root . '/gh-args.json' ), true );
		$this->assertContains( 'previous_tag_name=v2.4.0', $args );
		$this->assertContains( 'tag_name=v2.5.0', $args );
	}

	public function test_api_failure_does_not_modify_release_files(): void {
		$readme = file_get_contents( $this->root . '/readme.txt' );
		$plugin = file_get_contents( $this->root . '/kiriminaja.php' );
		list( $status, $output ) = $this->run_script( 'changelog.php', array(), true );
		$this->assertSame( 1, $status );
		$this->assertStringContainsString( 'GitHub release-note generation failed', $output );
		$this->assertSame( $readme, file_get_contents( $this->root . '/readme.txt' ) );
		$this->assertSame( $plugin, file_get_contents( $this->root . '/kiriminaja.php' ) );
	}

	public function test_empty_api_notes_do_not_modify_readme(): void {
		$readme = file_get_contents( $this->root . '/readme.txt' );
		list( $status, $output ) = $this->run_script( 'changelog.php', array(), false, '' );
		$this->assertSame( 1, $status );
		$this->assertStringContainsString( 'empty release notes', $output );
		$this->assertSame( $readme, file_get_contents( $this->root . '/readme.txt' ) );
	}

	public function test_release_extraction_rejects_missing_or_empty_entries(): void {
		list( $status, $output ) = $this->run_script( 'release-notes.php', array( '2.4.3' ) );
		$this->assertSame( 1, $status );
		$this->assertStringContainsString( 'not found', $output );
		$this->expectException( RuntimeException::class );
		kiriof_readme_release_notes( "== Changelog ==\n= 2.4.3 =\n\n= 2.4.2 =\n- Previous", '2.4.3' );
	}

	public function test_normalization_preserves_categories_and_drops_contributor_metadata(): void {
		$body = "## What's Changed\r\n\r\n## Fixes\r\n* Fix destination by @buyer in https://github.com/kiriminaja/wordpress/pull/282\r\n\r\n## New Contributors\r\n* @buyer made their first contribution in https://github.com/kiriminaja/wordpress/pull/282\r\n\r\n**Full Changelog**: https://github.com/kiriminaja/wordpress/compare/v2.4.2...v2.4.3";
		$this->assertSame( "**Fixes**\n- Fix destination (#282)\n\nFull Changelog: https://github.com/kiriminaja/wordpress/compare/v2.4.2...v2.4.3", kiriof_normalize_release_notes( $body ) );
	}

	public function test_release_extraction_does_not_use_an_upgrade_notice_as_a_changelog(): void {
		$this->expectException( RuntimeException::class );
		kiriof_readme_release_notes( "== Changelog ==\n= 2.4.2 =\n- Previous\n\n== Upgrade Notice ==\n= 2.4.3 =\nNot release notes\n", '2.4.3' );
	}

	public function test_explicit_prerelease_is_generated_and_extracted_without_a_stable_bump(): void {
		list( $status, $output ) = $this->run_script( 'changelog.php', array( 'v2.4.3-beta.1' ) );
		$this->assertSame( 0, $status, $output );
		$readme = file_get_contents( $this->root . '/readme.txt' );
		$this->assertStringContainsString( 'Stable tag: 2.4.3-beta.1', $readme );
		$this->assertSame( kiriof_normalize_release_notes( $this->body ), kiriof_readme_release_notes( $readme, '2.4.3-beta.1' ) );
		list( $status, $output ) = $this->run_script( 'release-notes.php', array( 'v2.4.3-beta.1' ) );
		$this->assertSame( 0, $status, $output );
		$this->assertStringContainsString( '- fix(checkout): restrict rates to Indonesia (#282)', $output );
		copy( PLUGIN_DIR . '/Makefile', $this->root . '/Makefile' );
		$commands = array();
		exec( 'make -n -C ' . escapeshellarg( $this->root ) . ' tag 2>&1', $commands, $status );
		$this->assertSame( 0, $status, implode( "\n", $commands ) );
		$this->assertStringContainsString( 'git tag -a "v2.4.3-beta.1"', implode( "\n", $commands ) );
	}

}
