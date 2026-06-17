<?php
/**
 * Fetches province + city data from the KiriminAja API and writes
 * a minified JSON bundle to inc/Data/regions.json.
 *
 * Usage:
 *   php scripts/fetch-regions.php [API_TOKEN] [BASE_URL]
 *
 * Defaults:
 *   BASE_URL = https://client.kiriminaja.com
 *
 * Example (dev):
 *   php scripts/fetch-regions.php "v4.local.xxx" "https://dev-core.bakso.my.id"
 */

$token   = $argv[1] ?? getenv( 'KIRIOF_API_TOKEN' );
$baseUrl = rtrim( $argv[2] ?? getenv( 'KIRIOF_API_BASE_URL' ) ?: 'https://client.kiriminaja.com', '/' );

if ( empty( $token ) ) {
    fwrite( STDERR, "Error: API token required as first argument or KIRIOF_API_TOKEN env var.\n" );
    exit( 1 );
}

function kiriof_api_post( string $url, string $token, array $body = [] ): array {
    $ch = curl_init( $url );
    curl_setopt_array( $ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode( $body ?: (object) [] ),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ] );
    $response = curl_exec( $ch );
    $err      = curl_error( $ch );
    curl_close( $ch );

    if ( $err ) {
        fwrite( STDERR, "cURL error: $err\n" );
        return [];
    }

    $decoded = json_decode( $response, true );
    return is_array( $decoded ) ? $decoded : [];
}

fwrite( STDERR, "Fetching provinces from $baseUrl ...\n" );
$provResp  = kiriof_api_post( "$baseUrl/api/mitra/province", $token );
$provinces = $provResp['datas'] ?? [];

if ( empty( $provinces ) ) {
    fwrite( STDERR, "Error: No provinces returned. Check token and base URL.\n" );
    exit( 1 );
}

fwrite( STDERR, 'Provinces: ' . count( $provinces ) . "\n" );

$result = [ 'provinces' => [], 'cities' => [] ];

foreach ( $provinces as $p ) {
    $id   = (int) ( $p['id'] ?? 0 );
    $name = (string) ( $p['provinsi_name'] ?? '' );
    if ( $id < 1 || '' === $name ) {
        continue;
    }

    $result['provinces'][] = [ 'id' => $id, 'name' => $name ];

    $cityResp = kiriof_api_post( "$baseUrl/api/mitra/city", $token, [ 'provinsi_id' => $id ] );
    $cities   = $cityResp['datas'] ?? [];

    foreach ( $cities as $c ) {
        $cid  = (int) ( $c['id'] ?? 0 );
        $cname = (string) ( $c['kabupaten_name'] ?? '' );
        $pid  = (int) ( $c['provinsi_id'] ?? 0 );
        if ( $cid < 1 || '' === $cname ) {
            continue;
        }
        $result['cities'][] = [ 'id' => $cid, 'province_id' => $pid, 'name' => $cname ];
    }

    fwrite( STDERR, "  $id $name: " . count( $cities ) . " cities\n" );
}

$outPath = dirname( __DIR__ ) . '/inc/Data/regions.json';
if ( ! is_dir( dirname( $outPath ) ) ) {
    mkdir( dirname( $outPath ), 0755, true );
}

file_put_contents( $outPath, json_encode( $result, JSON_UNESCAPED_UNICODE ) );

fwrite( STDERR, sprintf(
    "Written: %s (%d provinces, %d cities, %d bytes)\n",
    $outPath,
    count( $result['provinces'] ),
    count( $result['cities'] ),
    filesize( $outPath )
) );
