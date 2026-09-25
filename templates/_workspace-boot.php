<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Workspace boot skeleton.
 *
 * Server-rendered placeholder placed inside each Svelte mount root so the
 * WP content area shows a centered loading state (instead of a blank page)
 * while the ESM workspace bundle downloads and initializes. Removed by
 * admin-workspace.ts right before Svelte mounts.
 *
 * Expected variables:
 * @var string $kiriof_boot_title Optional heading. Defaults to translated "Loading…".
 * @var string $kiriof_boot_hint  Optional sub-text. Defaults to translated "Preparing the workspace…".
 */

$kiriof_boot_title = isset( $kiriof_boot_title ) ? (string) $kiriof_boot_title : __( 'Loading…', 'kiriminaja-official' );
$kiriof_boot_hint  = isset( $kiriof_boot_hint ) ? (string) $kiriof_boot_hint : __( 'Preparing the workspace…', 'kiriminaja-official' );
?>
<div class="kiriof-workspace-boot" data-kiriof-workspace-boot aria-hidden="true">
	<div class="kiriof-workspace-boot__spinner"></div>
	<p class="kiriof-workspace-boot__title"><?php echo esc_html( $kiriof_boot_title ); ?></p>
	<p class="kiriof-workspace-boot__hint"><?php echo esc_html( $kiriof_boot_hint ); ?></p>
	<div class="kiriof-workspace-boot__bars" aria-hidden="true">
		<span></span>
		<span></span>
		<span></span>
	</div>
</div>
