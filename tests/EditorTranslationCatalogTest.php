<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Static coverage for bundled editor catalogs, not a live WordPress locale test.
 */
final class EditorTranslationCatalogTest extends TestCase {
	private const EDITOR_SCRIPTS = array(
		'kiriof-checkout-district-editor' => 'blocks/checkout-district/edit.js',
		'kiriof-map-checkout-editor'      => 'blocks/map-checkout/edit.js',
	);

	private const EDITOR_TRANSLATIONS = array(
		'blocks/checkout-district/edit.js' => array(
			'KiriminAja Subdistrict'                      => 'Desa / Kelurahan KiriminAja',
			'Searchable shipping destination subdistrict.' => 'Desa / Kelurahan tujuan pengiriman yang dapat dicari.',
			'Subdistrict'                                 => 'Desa / Kelurahan',
			'Select Subdistrict'                          => 'Pilih Desa / Kelurahan',
		),
		'blocks/map-checkout/edit.js' => array(
			'KiriminAja Delivery Map'                                       => 'Peta Pengiriman KiriminAja',
			'Optional delivery pin for the shipping address.'               => 'Pin pengiriman opsional untuk alamat pengiriman.',
			'Delivery pin'                                                 => 'Pin pengiriman',
			'Optional. Customers can choose a delivery pin at checkout.'     => 'Opsional. Pelanggan dapat memilih pin pengiriman saat checkout.',
		),
	);

	private function catalog_path( string $script ): string {
		return PLUGIN_DIR . '/lang/kiriminaja-official-id_ID-' . md5( $script ) . '.json';
	}

	private function catalog( string $script ): array {
		$path = $this->catalog_path( $script );
		$this->assertFileExists( $path );
		return json_decode( file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
	}

	#[Test]
	public function catalog_filenames_hash_the_registered_plugin_relative_script_paths(): void {
		$enqueue = file_get_contents( PLUGIN_DIR . '/inc/Base/Enqueue.php' );
		$hashes  = array(
			'blocks/checkout-district/edit.js' => '2a22a4e0bfbefd2b9615e9bba7f5f876',
			'blocks/map-checkout/edit.js'      => '2e551a3a265cd20489b17437fcb66fc6',
		);

		foreach ( self::EDITOR_SCRIPTS as $handle => $script ) {
			$this->assertStringContainsString( "'" . $handle . "' => array( '" . $script . "'", $enqueue );
			$this->assertSame( $hashes[ $script ], md5( $script ) );
			$this->assertFileExists( $this->catalog_path( $script ) );

			// WordPress removes plugins/<plugin directory> before hashing the source URL.
			$relative = explode( '/', 'plugins/kiriminaja-official/' . $script );
			$relative = implode( '/', array_slice( $relative, 2 ) );
			$this->assertSame( $script, $relative );
			$this->assertSame( $hashes[ $script ], md5( $relative ) );
		}
	}

	#[Test]
	public function catalogs_have_wordpress_jed_metadata_and_the_po_revision_date(): void {
		$po = file_get_contents( PLUGIN_DIR . '/lang/kiriminaja-official-id_ID.po' );
		$this->assertSame( 1, preg_match( '/"PO-Revision-Date: ([^"\\\\]+)\\\\n"/', $po, $revision ) );

		foreach ( self::EDITOR_SCRIPTS as $script ) {
			$catalog = $this->catalog( $script );
			$this->assertSame( $script, $catalog['source'] );
			$this->assertSame( 'kiriminaja-official', $catalog['domain'] );
			$this->assertSame( $revision[1], $catalog['translation-revision-date'] );
			$this->assertNotEmpty( $catalog['generator'] );
			$this->assertSame(
				array(
					'domain'       => 'messages',
					'lang'         => 'id_ID',
					'plural-forms' => 'nplurals=2; plural=(n > 1);',
				),
				$catalog['locale_data']['messages']['']
			);
		}
	}

	#[Test]
	public function every_current_editor_message_has_an_exact_nonempty_indonesian_translation(): void {
		foreach ( self::EDITOR_SCRIPTS as $script ) {
			$source = file_get_contents( PLUGIN_DIR . '/' . $script );
			$count  = preg_match_all( "/wp\\.i18n\\.__\\(\\s*'([^']+)',\\s*'kiriminaja-official'\\s*\\)/", $source, $matches );
			$this->assertSame( 4, $count );
			$this->assertSame( array_keys( self::EDITOR_TRANSLATIONS[ $script ] ), $matches[1] );

			$messages = $this->catalog( $script )['locale_data']['messages'];
			unset( $messages[''] );
			$this->assertSame( $matches[1], array_keys( $messages ) );
			foreach ( self::EDITOR_TRANSLATIONS[ $script ] as $msgid => $translation ) {
				$this->assertSame( array( $translation ), $messages[ $msgid ] );
				$this->assertNotSame( '', trim( $messages[ $msgid ][0] ) );
				$this->assertNotSame( $msgid, $messages[ $msgid ][0] );
			}
		}
	}

	#[Test]
	public function editor_translation_attachment_uses_the_bundled_catalog_directory(): void {
		$enqueue = file_get_contents( PLUGIN_DIR . '/inc/Base/Enqueue.php' );
		$this->assertStringContainsString( "array( 'kiriof-map-checkout-editor', 'kiriof-checkout-district-editor' ) as \$editor_handle", $enqueue );
		$this->assertStringContainsString( "wp_set_script_translations( \$editor_handle, 'kiriminaja-official', KIRIOF_DIR . 'lang' )", $enqueue );
		foreach ( self::EDITOR_SCRIPTS as $script ) {
			$this->assertFileExists( $this->catalog_path( $script ) );
		}
	}
}
