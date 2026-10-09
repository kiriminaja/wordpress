<?php

namespace KiriminAjaOfficial\Controllers;

function __( $text, $domain ) {
	return $text;
}

function admin_url( $path ) {
	return 'https://example.test/wp-admin/' . $path;
}

function wp_kses( $text, $allowed_html ) {
	return $text;
}

function esc_url( $url ) {
	return $url;
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}
