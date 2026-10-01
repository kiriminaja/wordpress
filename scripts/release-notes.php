#!/usr/bin/env php
<?php
/** Shared release-note generation and readme extraction for release tooling. */

function kiriof_normalize_release_notes( string $body ): string {
	$notes = array();
	$contributors = false;
	foreach ( explode( "\n", str_replace( "\r\n", "\n", trim( $body ) ) ) as $line ) {
		if ( "## What's Changed" === $line ) {
			continue;
		}
		if ( '## New Contributors' === $line ) {
			$contributors = true;
			continue;
		}
		if ( str_starts_with( $line, '**Full Changelog**:' ) ) {
			$contributors = false;
			$line = str_replace( '**Full Changelog**:', 'Full Changelog:', $line );
		}
		if ( $contributors ) {
			continue;
		}
		$line = preg_replace( '~^[*-] (.+) by @\S+ in https://github\.com/[^/]+/[^/]+/pull/(\d+)$~', '- $1 (#$2)', $line );
		$line = preg_replace( '/^## (.+)$/', '**$1**', $line );
		$notes[] = $line;
	}
	return trim( preg_replace( '/\n{3,}/', "\n\n", implode( "\n", $notes ) ) );
}

function kiriof_generate_release_notes( string $root_dir, string $version, ?string $previous_tag ): string {
	$commit = array();
	$status = 0;
	exec( 'git -C ' . escapeshellarg( $root_dir ) . ' rev-parse HEAD', $commit, $status );
	if ( 0 !== $status || ! preg_match( '/^[a-f0-9]{40,64}$/', $commit[0] ?? '' ) ) {
		throw new RuntimeException( 'Could not resolve the release commit.' );
	}
	$command = 'cd ' . escapeshellarg( $root_dir ) . ' && gh api repos/{owner}/{repo}/releases/generate-notes';
	$command .= ' -f ' . escapeshellarg( 'tag_name=v' . $version );
	$command .= ' -f ' . escapeshellarg( 'target_commitish=' . $commit[0] );
	if ( $previous_tag ) {
		$command .= ' -f ' . escapeshellarg( 'previous_tag_name=' . $previous_tag );
	}
	$command .= ' --jq ' . escapeshellarg( '.body' );
	$output = array();
	$status = 0;
	exec( $command, $output, $status );
	if ( 0 !== $status ) {
		throw new RuntimeException( 'GitHub release-note generation failed. Install gh, run gh auth login, and push the release branch before retrying.' );
	}
	$body = kiriof_normalize_release_notes( implode( "\n", $output ) );
	if ( '' === $body ) {
		throw new RuntimeException( 'GitHub returned empty release notes; no release files were updated.' );
	}
	return $body;
}

function kiriof_readme_release_notes( string $readme, string $version ): string {
	$changelog = preg_split( '/^== Changelog ==\s*$/m', $readme, 2 );
	$section = isset( $changelog[1] ) ? preg_split( '/^== [^=\n]+ ==\s*$/m', $changelog[1], 2 )[0] : '';
	if ( ! preg_match( '/^= ' . preg_quote( $version, '/' ) . ' =\s*\n(.*?)(?=^= [^=\n]+ =\s*$|\z)/ms', $section, $match ) ) {
		throw new RuntimeException( "Changelog entry for {$version} not found in readme.txt." );
	}
	$notes = trim( $match[1] );
	if ( '' === $notes ) {
		throw new RuntimeException( "Changelog entry for {$version} is empty." );
	}
	return $notes;
}

if ( isset( $_SERVER['SCRIPT_FILENAME'] ) && realpath( $_SERVER['SCRIPT_FILENAME'] ) === __FILE__ ) {
	try {
		$version = ltrim( $argv[1] ?? '', 'vV' );
		if ( ! preg_match( '/^\d+\.\d+\.\d+(?:-[A-Za-z0-9.-]+)?$/', $version ) ) {
			throw new RuntimeException( 'Usage: php scripts/release-notes.php <version>' );
		}
		$readme = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
		echo "## What's Changed\n\n" . kiriof_readme_release_notes( $readme, $version ) . "\n";
	} catch ( Throwable $error ) {
		fwrite( STDERR, 'Error: ' . $error->getMessage() . "\n" );
		exit( 1 );
	}
}
