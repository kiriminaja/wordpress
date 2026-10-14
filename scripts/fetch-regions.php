<?php
/**
 * Fetch province and city data with the official KiriminAja PHP SDK.
 *
 * Usage:
 *   php scripts/fetch-regions.php [API_TOKEN] [BASE_URL]
 */

use KiriminAja\Base\Config\Cache\Mode;
use KiriminAja\Base\Config\KiriminAjaConfig;
use KiriminAja\Services\KiriminAja;

require dirname( __DIR__ ) . '/vendor/autoload.php';

$token    = $argv[1] ?? getenv( 'KIRIOF_API_TOKEN' );
$base_url = rtrim( $argv[2] ?? getenv( 'KIRIOF_API_BASE_URL' ) ?: 'https://client.kiriminaja.com', '/' );

if ( empty( $token ) ) {
    fwrite( STDERR, "Error: API token required as first argument or KIRIOF_API_TOKEN env var.\n" );
    exit( 1 );
}

KiriminAjaConfig::setCacheDirectory( sys_get_temp_dir() . '/kiriminaja-sdk' );
KiriminAjaConfig::setMode( Mode::Production );
KiriminAjaConfig::setBaseUrl( $base_url );
KiriminAjaConfig::setApiTokenKey( $token );

fwrite( STDERR, "Fetching provinces from {$base_url} ...\n" );
$province_response = KiriminAja::getProvince();
$provinces         = $province_response->status && is_array( $province_response->data ) ? $province_response->data : array();

if ( empty( $provinces ) ) {
    fwrite( STDERR, "Error: No provinces returned. Check token and base URL.\n" );
    exit( 1 );
}

fwrite( STDERR, 'Provinces: ' . count( $provinces ) . "\n" );
$result = array(
    'provinces' => array(),
    'cities'    => array(),
);

foreach ( $provinces as $province ) {
    $id   = (int) ( $province['id'] ?? 0 );
    $name = (string) ( $province['provinsi_name'] ?? '' );
    if ( $id < 1 || '' === $name ) {
        continue;
    }

    $result['provinces'][] = array(
        'id'   => $id,
        'name' => $name,
    );

    $city_response = KiriminAja::getCity( $id );
    $city_data     = $city_response->status && is_array( $city_response->data ) ? $city_response->data : array();
    $cities        = $city_data['datas'] ?? $city_data;

    foreach ( (array) $cities as $city ) {
        $city_id     = (int) ( $city['id'] ?? 0 );
        $city_name   = (string) ( $city['kabupaten_name'] ?? '' );
        $province_id = (int) ( $city['provinsi_id'] ?? $id );
        if ( $city_id < 1 || '' === $city_name ) {
            continue;
        }

        $result['cities'][] = array(
            'id'          => $city_id,
            'province_id' => $province_id,
            'name'        => $city_name,
        );
    }

    fwrite( STDERR, "  {$id} {$name}: " . count( (array) $cities ) . " cities\n" );
}

$output_path = dirname( __DIR__ ) . '/inc/Data/regions.json';
if ( ! is_dir( dirname( $output_path ) ) ) {
    mkdir( dirname( $output_path ), 0755, true );
}

file_put_contents( $output_path, json_encode( $result, JSON_UNESCAPED_UNICODE ) );

fwrite(
    STDERR,
    sprintf(
        "Written: %s (%d provinces, %d cities, %d bytes)\n",
        $output_path,
        count( $result['provinces'] ),
        count( $result['cities'] ),
        filesize( $output_path )
    )
);
