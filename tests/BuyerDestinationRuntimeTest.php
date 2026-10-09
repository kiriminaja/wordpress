<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BuyerDestinationRuntimeTest extends TestCase {
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
    #[Test]
    public function real_factory_district_search_uses_injected_repository_without_network(): void {
        if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', dirname( __DIR__ ) . '/' ); }
        if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); }
        require_once dirname( __DIR__ ) . '/vendor/autoload.php';

        $repository = \Mockery::mock( \KiriminAjaOfficial\Repositories\KiriminajaApiRepository::class );
        $rows = array( (object) array( 'id' => 222, 'text' => 'New district' ) );
        $repository->shouldReceive( 'sub_district_search' )->once()->with( '12345' )
            ->andReturn( array( 'status' => true, 'data' => (object) array( 'result' => $rows ) ) );
        $factory = new \KiriminAjaOfficial\Services\CheckoutServiceFactory(
            \Mockery::mock( \KiriminAjaOfficial\Repositories\SettingRepository::class ),
            \Mockery::mock( \KiriminAjaOfficial\Repositories\TransactionRepository::class ),
            \Mockery::mock( \KiriminAjaOfficial\Repositories\WpPostMetaRepository::class ),
            $repository,
            \Mockery::mock( \KiriminAjaOfficial\Repositories\CodFeeApiRepository::class ),
            \Mockery::mock( \KiriminAjaOfficial\Services\ShipmentLocationService::class )
        );

        $response = $factory->districtSearch( '12345' );
        $this->assertInstanceOf( \KiriminAjaOfficial\Utils\ServiceResponse::class, $response );
        $this->assertSame(
            [
            '1: response->status' => 200,
            '2: response->data' => $rows,
            ],
            [
            '1: response->status' => $response->status,
            '2: response->data' => $response->data,
            ]
        );
    }

    private static function destination(): array {
        return array( 'district_id' => '222', 'district_label' => 'New district', 'postcode' => '12345', 'country' => 'ID', 'address_type' => 'shipping', 'version' => 1 );
    }

    private static function mapDestination(): array {
        $destination = self::destination();
        $destination['destination_latitude'] = '-6.2';
        $destination['destination_longitude'] = '106.8';
        $destination['version'] = 2;
        $destination['shipping_address'] = array( 'address_1' => 'Main street', 'address_2' => '', 'city' => 'Jakarta', 'state' => 'JK', 'postcode' => '12345', 'country' => 'ID' );
        return $destination;
    }

    #[Test]
    public function checkout_schema_exposes_typed_destination_with_optional_coordinates(): void {
        $expectedCases = $actualCases = [];
        $result = $this->runFixture( array( 'operation' => 'schema' ) );
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
            '1: result[schema][endpoint]' => 'checkout',
            '2: result[schema][namespace]' => 'kiriminaja-official',
            '3: result[schema][schema][shipping_selection][type]' => 'object',
            '4: result[schema][schema][shipping_selection][properties][packages][items][required]' => array( 'package_id', 'rate_id' ),
            ];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
            '1: result[schema][endpoint]' => $result['schema']['endpoint'],
            '2: result[schema][namespace]' => $result['schema']['namespace'],
            '3: result[schema][schema][shipping_selection][type]' => $result['schema']['schema']['shipping_selection']['type'],
            '4: result[schema][schema][shipping_selection][properties][packages][items][required]' => $result['schema']['schema']['shipping_selection']['properties']['packages']['items']['required'],
            ];
        $properties = $result['schema']['schema']['destination']['properties'];
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
            '1: properties[district_id][type]' => array( 'string', 'integer' ),
            '2: properties[address_type][enum]' => array( 'shipping' ),
            '3: properties[version][enum]' => array( 1, 2 ),
            '4: array_key_exists( destination_latitude, properties )' => true,
            '5: array_key_exists( destination_longitude, properties )' => true,
            '6: result[schema][schema][destination][required]' => array_keys( self::destination() ),
            '7: properties[destination_latitude][minimum]' => -90,
            '8: properties[destination_longitude][maximum]' => 180,
            '9: properties[shipping_address][required]' => array_keys( self::mapDestination()['shipping_address'] ),
            ];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
            '1: properties[district_id][type]' => $properties['district_id']['type'],
            '2: properties[address_type][enum]' => $properties['address_type']['enum'],
            '3: properties[version][enum]' => $properties['version']['enum'],
            '4: array_key_exists( destination_latitude, properties )' => array_key_exists( 'destination_latitude', $properties ),
            '5: array_key_exists( destination_longitude, properties )' => array_key_exists( 'destination_longitude', $properties ),
            '6: result[schema][schema][destination][required]' => $result['schema']['schema']['destination']['required'],
            '7: properties[destination_latitude][minimum]' => $properties['destination_latitude']['minimum'],
            '8: properties[destination_longitude][maximum]' => $properties['destination_longitude']['maximum'],
            '9: properties[shipping_address][required]' => $properties['shipping_address']['required'],
            ];
        $this->assertSame( $expectedCases, $actualCases );
    }

    #[Test]
    public function original_six_field_contract_and_empty_coordinate_keys_remain_version_one(): void {
        $expectedCases = $actualCases = [];
        foreach ( array( self::destination(), self::destination() + array( 'destination_latitude' => '', 'destination_longitude' => '' ) ) as $destination ) {
            $result = $this->runFixture( array( 'operation' => 'normalize', 'destination' => $destination ) );
            $contractCase = 'case ' . count( $expectedCases );
            $expectedCases[$contractCase . " / " . count( $expectedCases )] = self::destination();
            $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $result['destination'];
            $result = $this->runFixture( array( 'operation' => 'sync', 'data' => array( 'action' => 'sync_checkout', 'destination' => $destination ) ) );
            $contractCase = 'case ' . count( $expectedCases );
            $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                '1: result[session][kiriof_buyer_destination]' => self::destination(),
                '2: result[session][kiriof_buyer_destination_coordinates]' => null,
                ];
            $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                '1: result[session][kiriof_buyer_destination]' => $result['session']['kiriof_buyer_destination'],
                '2: result[session][kiriof_buyer_destination_coordinates]' => $result['session']['kiriof_buyer_destination_coordinates'],
                ];
        }
        $this->assertSame( $expectedCases, $actualCases );
    }

    #[Test]
    public function real_zero_and_precision_seven_coordinates_are_preserved(): void {
        $destination = self::mapDestination();
        $destination['destination_latitude'] = 0;
        $destination['destination_longitude'] = 106.123456789;
        $result = $this->order( array( 'extensions' => array( 'kiriminaja-official' => array( 'destination' => $destination ) ) ) );
        $this->assertSame(
            [
            '1: array_key_exists( error, result )' => false,
            '2: result[meta][_kiriof_buyer_destination_coordinates]' => array( 'latitude' => '0', 'longitude' => '106.1234568' ),
            ],
            [
            '1: array_key_exists( error, result )' => array_key_exists( 'error', $result ),
            '2: result[meta][_kiriof_buyer_destination_coordinates]' => $result['meta']['_kiriof_buyer_destination_coordinates'],
            ]
        );
    }

    #[Test]
    public function nonfinite_native_php_coordinates_are_rejected(): void {
        if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', dirname( __DIR__ ) . '/' ); }
        require_once dirname( __DIR__ ) . '/inc/Services/BuyerDestination.php';
        foreach ( array( NAN, INF, -INF, true, false, array() ) as $value ) {
            try {
                \KiriminAjaOfficial\Services\BuyerDestination::coordinate( $value, 90 );
                $this->fail( 'Invalid native coordinate was accepted.' );
            } catch ( \InvalidArgumentException $error ) {
                $this->assertSame( 'Invalid shipping destination.', $error->getMessage() );
            }
        }
    }

    #[Test]
    public function pins_are_bound_to_all_six_address_fields_and_not_only_postcode(): void {
        $expectedCases = $actualCases = [];
        $destination = self::mapDestination();
        foreach ( array_keys( $destination['shipping_address'] ) as $field ) {
            $address = $destination['shipping_address'];
            $address[$field] = 'country' === $field ? 'US' : 'Changed';
            $result = $this->order( array( 'extensions' => array( 'kiriminaja-official' => array( 'destination' => $destination ) ), 'shipping_address' => $address ) );
            $contractCase = 'case ' . count( $expectedCases );
            $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                '1: result[error][code]' => 'kiriof_invalid_destination',
                '2: array_key_exists( _kiriof_buyer_destination_coordinates, result[meta] )' => false,
                ];
            $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                '1: result[error][code]' => $result['error']['code'],
                '2: array_key_exists( _kiriof_buyer_destination_coordinates, result[meta] )' => array_key_exists( '_kiriof_buyer_destination_coordinates', $result['meta'] ),
                ];
        }
        $result = $this->order( array( 'extensions' => array( 'kiriminaja-official' => array( 'destination' => $destination ) ) ), array( 'order_address' => array( 'address_1' => 'Edited street' ) ) );
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = 'kiriof_invalid_destination';
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $result['error']['code'];
        $destination['district_id'] = ''; $destination['district_label'] = '';
        $result = $this->order( array( 'extensions' => array( 'kiriminaja-official' => array( 'destination' => $destination ) ) ) );
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = 'kiriof_invalid_destination';
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $result['error']['code'];
        $this->assertSame( $expectedCases, $actualCases );
    }

    #[Test]
    public function version_one_checkout_removes_stale_order_coordinates(): void {
        $result = $this->order( array( 'extensions' => array( 'kiriminaja-official' => array( 'destination' => self::destination() ) ) ), array( 'meta' => array( '_kiriof_buyer_destination' => self::mapDestination(), '_kiriof_buyer_destination_coordinates' => array( 'latitude' => '-6.2', 'longitude' => '106.8' ) ) ) );
        $this->assertSame(
            [
            '1: array_key_exists( error, result )' => false,
            '2: result[meta][_kiriof_buyer_destination]' => self::destination(),
            '3: array_key_exists( _kiriof_buyer_destination_coordinates, result[meta] )' => false,
            ],
            [
            '1: array_key_exists( error, result )' => array_key_exists( 'error', $result ),
            '2: result[meta][_kiriof_buyer_destination]' => $result['meta']['_kiriof_buyer_destination'],
            '3: array_key_exists( _kiriof_buyer_destination_coordinates, result[meta] )' => array_key_exists( '_kiriof_buyer_destination_coordinates', $result['meta'] ),
            ]
        );
    }

    #[Test]
    public function map_coordinates_are_optional_and_persisted_with_required_district(): void {
        $expectedCases = $actualCases = [];
        $result = $this->runFixture( array( 'operation' => 'normalize', 'destination' => self::mapDestination() ) );
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = self::mapDestination();
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $result['destination'];
        $destination = self::mapDestination();
        $destination['destination_latitude'] = '-6.200000';
        $destination['destination_longitude'] = '106.800000';
        $result = $this->runFixture( array( 'operation' => 'sync', 'session' => array(), 'data' => array( 'action' => 'sync_checkout', 'destination' => $destination ) ) );
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
            '1: result[session][kiriof_buyer_destination][destination_latitude]' => '-6.2',
            '2: result[session][kiriof_buyer_destination][destination_longitude]' => '106.8',
            '3: result[session][kiriof_buyer_destination_coordinates]' => array( 'latitude' => '-6.2', 'longitude' => '106.8' ),
            ];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
            '1: result[session][kiriof_buyer_destination][destination_latitude]' => $result['session']['kiriof_buyer_destination']['destination_latitude'],
            '2: result[session][kiriof_buyer_destination][destination_longitude]' => $result['session']['kiriof_buyer_destination']['destination_longitude'],
            '3: result[session][kiriof_buyer_destination_coordinates]' => $result['session']['kiriof_buyer_destination_coordinates'],
            ];
        $result = $this->order( array( 'extensions' => array( 'kiriminaja-official' => array( 'destination' => self::mapDestination() ) ), 'shipping_address' => array( 'postcode' => '12345', 'country' => 'ID' ) ) );
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
            '1: result[meta][_kiriof_buyer_destination]' => self::mapDestination(),
            '2: result[meta][_kiriof_buyer_destination_coordinates]' => array( 'latitude' => '-6.2', 'longitude' => '106.8' ),
            ];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
            '1: result[meta][_kiriof_buyer_destination]' => $result['meta']['_kiriof_buyer_destination'],
            '2: result[meta][_kiriof_buyer_destination_coordinates]' => $result['meta']['_kiriof_buyer_destination_coordinates'],
            ];
        $this->assertSame( $expectedCases, $actualCases );
    }

    #[Test]
    #[DataProvider( 'invalidCoordinates' )]
    public function invalid_map_coordinates_fail_closed( $destination ): void {
        $result = $this->runFixture( array( 'operation' => 'sync', 'session' => array(), 'data' => array( 'action' => 'sync_checkout', 'destination' => $destination ) ) );
        $this->assertSame(
            [
            '1: result[error][code]' => 'kiriof_invalid_destination',
            '2: result[error][status]' => 400,
            ],
            [
            '1: result[error][code]' => $result['error']['code'],
            '2: result[error][status]' => $result['error']['status'],
            ]
        );
    }

    public static function invalidCoordinates(): array {
        $destination = self::destination();
        $latitude = $destination;
        $latitude['destination_latitude'] = '-6.2';
        $latitude['version'] = 2;
        $longitude = $destination;
        $longitude['destination_longitude'] = '106.8';
        $longitude['version'] = 2;
        $outOfRange = self::mapDestination();
        $outOfRange['destination_latitude'] = '91';
        $notNumeric = self::mapDestination();
        $notNumeric['destination_longitude'] = 'east';
        $versionMismatch = $destination;
        $versionMismatch['version'] = 2;
        $cases = array(
            'missing longitude' => array( $latitude ),
            'missing latitude' => array( $longitude ),
            'latitude out of range' => array( $outOfRange ),
            'longitude not numeric' => array( $notNumeric ),
            'version 2 without coordinates' => array( $versionMismatch ),
        );
        foreach ( array( true, array(), 'NaN', 'INF', '1e1', '+1', '01', ' 1 ', '181' ) as $index => $value ) {
            $invalid = self::mapDestination();
            $invalid['destination_longitude'] = $value;
            $cases['bad longitude ' . $index] = array( $invalid );
        }
        $invalid = self::mapDestination(); unset( $invalid['shipping_address'] );
        $cases['missing snapshot'] = array( $invalid );
        $invalid = self::mapDestination(); unset( $invalid['shipping_address']['city'] );
        $cases['incomplete snapshot'] = array( $invalid );
        $invalid = self::mapDestination(); $invalid['shipping_address']['address_1'] = array();
        $cases['invalid snapshot type'] = array( $invalid );
        $invalid = self::mapDestination(); $invalid['shipping_address']['postcode'] = '99999';
        $cases['snapshot postcode inconsistent'] = array( $invalid );
        $invalid = self::mapDestination(); $invalid['version'] = 1;
        $cases['version 1 nonempty pin'] = array( $invalid );
        return $cases;
    }

    #[Test]
    public function modern_sync_is_authoritative_and_does_not_select_a_rate(): void {
        $native = array( 'kiriminaja-official_jnt_EZ', 'flat_rate:2' );
        $destination = self::destination();
        $destination['district_id'] = 222;
        $destination['country'] = 'id';
        $destination['postcode'] = '12 345';
        $result = $this->runFixture( array( 'operation' => 'sync', 'session' => array( 'chosen_shipping_methods' => $native ), 'data' => array(
            'action' => 'sync_checkout', 'destination' => $destination, 'destination_id' => 999, 'destination_name' => 'Stale district', 'postcode' => '99999',
            'shipping_metode_id' => 'kiriminaja-official_jne_REG', 'payment_method' => 'cod', 'insurance' => true, 'force_insurance' => false,
        ) ) );
        $this->assertSame(
            [
            '1: array_key_exists( error, result )' => false,
            '2: result[session][kiriof_buyer_destination]' => self::destination(),
            '3: result[session][destination_id]' => '222',
            '4: result[session][destination_name]' => 'New district',
            '5: result[session][kiriof_destination_postcode_map][12345]' => array( 'destination_id' => '222', 'destination_name' => 'New district' ),
            '6: result[session][kiriof_checkout_postcode]' => '12345',
            '7: result[session][chosen_shipping_methods]' => $native,
            '8: result[session][chosen_payment_method]' => 'cod',
            '9: result[session][kiriof_insurance]' => 1,
            ],
            [
            '1: array_key_exists( error, result )' => array_key_exists( 'error', $result ),
            '2: result[session][kiriof_buyer_destination]' => $result['session']['kiriof_buyer_destination'],
            '3: result[session][destination_id]' => $result['session']['destination_id'],
            '4: result[session][destination_name]' => $result['session']['destination_name'],
            '5: result[session][kiriof_destination_postcode_map][12345]' => $result['session']['kiriof_destination_postcode_map']['12345'],
            '6: result[session][kiriof_checkout_postcode]' => $result['session']['kiriof_checkout_postcode'],
            '7: result[session][chosen_shipping_methods]' => $result['session']['chosen_shipping_methods'],
            '8: result[session][chosen_payment_method]' => $result['session']['chosen_payment_method'],
            '9: result[session][kiriof_insurance]' => $result['session']['kiriof_insurance'],
            ]
        );
    }

    #[Test]
    public function empty_modern_sync_clears_id_name_and_token_but_preserves_rates(): void {
        $destination = self::destination();
        $destination['district_id'] = '';
        $destination['district_label'] = '';
        $native = array( 'kiriminaja-official_jnt_EZ' );
        $result = $this->runFixture( array( 'operation' => 'sync', 'session' => array( 'chosen_shipping_methods' => $native, 'destination_id' => '999', 'destination_name' => 'Stale' ), 'data' => array( 'action' => 'sync_checkout', 'destination' => $destination ) ) );
        $this->assertSame(
            [
            '1: result[session][destination_id]' => '',
            '2: result[session][destination_name]' => '',
            '3: result[session][kiriof_checkout_token]' => '',
            '4: result[session][chosen_shipping_methods]' => $native,
            ],
            [
            '1: result[session][destination_id]' => $result['session']['destination_id'],
            '2: result[session][destination_name]' => $result['session']['destination_name'],
            '3: result[session][kiriof_checkout_token]' => $result['session']['kiriof_checkout_token'],
            '4: result[session][chosen_shipping_methods]' => $result['session']['chosen_shipping_methods'],
            ]
        );
    }

    #[Test]
    public function fee_only_sync_is_partial_safe(): void {
        $expectedCases = $actualCases = [];
        $session = array( 'destination_id' => '222', 'destination_name' => 'New district', 'kiriof_insurance' => 1, 'force_insurance' => 1, 'chosen_shipping_methods' => array( 'flat_rate:2' ) );
        $result = $this->runFixture( array( 'operation' => 'sync', 'session' => $session, 'data' => array( 'action' => 'sync_checkout', 'payment_method' => 'bacs' ) ) );
        foreach ( $session as $key => $value ) { $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = $value;
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $result['session'][$key]; }
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = 'bacs';
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $result['session']['chosen_payment_method'];
        $this->assertSame( $expectedCases, $actualCases );
    }

    #[Test]
    #[DataProvider( 'malformedDestinations' )]
    public function malformed_sync_is_rejected_without_mutating_session( $destination ): void {
        $session = array( 'destination_id' => '111', 'destination_name' => 'Old district' );
        $result = $this->runFixture( array( 'operation' => 'sync', 'session' => $session, 'data' => array( 'action' => 'sync_checkout', 'destination' => $destination ) ) );
        $this->assertSame(
            [
            '1: result[error][code]' => 'kiriof_invalid_destination',
            '2: result[error][status]' => 400,
            '3: result[session]' => $session,
            '4: str_contains( result[error][message], <script> )' => false,
            ],
            [
            '1: result[error][code]' => $result['error']['code'],
            '2: result[error][status]' => $result['error']['status'],
            '3: result[session]' => $result['session'],
            '4: str_contains( result[error][message], <script> )' => str_contains( $result['error']['message'], '<script>' ),
            ]
        );
    }

    public static function malformedDestinations(): array {
        $cases = array( 'null' => array( null ), 'scalar' => array( '222' ), 'empty object' => array( array() ) );
        foreach ( array(
            'district_id' => array( -1, 0, 1.5, true, array(), '-1', '0', '1e3', '2.0', ' 222', 'District', '01' ),
            'district_label' => array( null, 222, array(), '', '222', '<script>bad</script>', "Bad\nlabel" ),
            'postcode' => array( null, 12345, array(), '' ),
            'country' => array( null, 'Indonesia', '' ),
            'address_type' => array( 'billing', null ),
            'version' => array( 0, 3, '1', true, '2' ),
        ) as $field => $values ) {
            foreach ( $values as $index => $value ) {
                $destination = self::destination();
                $destination[$field] = $value;
                $cases[$field . '-' . $index] = array( $destination );
            }
        }
        return $cases;
    }

    #[Test]
    public function modern_order_wins_over_legacy_and_persists_permanent_snapshot(): void {
        $destination = self::destination();
        $result = $this->order( array( 'extensions' => array( 'kiriminaja-official' => array( 'destination' => $destination ) ), 'shipping_address' => array( 'postcode' => '12 345', 'country' => 'id', 'additional_fields' => array( 'kiriminaja-official/kiriof_destination_area' => '999' ) ) ) );
        $this->assertSame(
            [
            '1: array_key_exists( error, result )' => false,
            '2: result[meta][_kiriof_buyer_destination]' => $destination,
            '3: result[meta][_kiriof_checkout_destination_area]' => '222',
            '4: result[meta][_kiriof_checkout_destination_area_name]' => 'New district',
            '5: result[meta][_kiriof_checkout_postcode]' => '12345',
            '6: result[meta][_kiriof_checkout_destination_present]' => '1',
            '7: result[meta][_kiriof_checkout_token]' => '1',
            ],
            [
            '1: array_key_exists( error, result )' => array_key_exists( 'error', $result ),
            '2: result[meta][_kiriof_buyer_destination]' => $result['meta']['_kiriof_buyer_destination'],
            '3: result[meta][_kiriof_checkout_destination_area]' => $result['meta']['_kiriof_checkout_destination_area'],
            '4: result[meta][_kiriof_checkout_destination_area_name]' => $result['meta']['_kiriof_checkout_destination_area_name'],
            '5: result[meta][_kiriof_checkout_postcode]' => $result['meta']['_kiriof_checkout_postcode'],
            '6: result[meta][_kiriof_checkout_destination_present]' => $result['meta']['_kiriof_checkout_destination_present'],
            '7: result[meta][_kiriof_checkout_token]' => $result['meta']['_kiriof_checkout_token'],
            ]
        );
    }

    #[Test]
    #[DataProvider( 'invalidOrders' )]
    public function invalid_modern_kiriminaja_checkout_never_uses_session_or_resolves_a_district( array $params ): void {
        $result = $this->order( $params );
        $this->assertSame(
            [
            '1: result[error][code]' => 'kiriof_invalid_destination',
            '2: result[error][status]' => 400,
            ],
            [
            '1: result[error][code]' => $result['error']['code'],
            '2: result[error][status]' => $result['error']['status'],
            ]
        );
    }

    public static function invalidOrders(): array {
        $wrap = static fn( $destination ) => array( 'extensions' => array( 'kiriminaja-official' => array( 'destination' => $destination ) ) );
        $empty = self::destination(); $empty['district_id'] = ''; $empty['district_label'] = '';
        $foreign = self::destination(); $foreign['country'] = 'US';
        return array(
            'missing' => array( array( 'extensions' => array( 'kiriminaja-official' => array() ) ) ),
            'empty district' => array( $wrap( $empty ) ),
            'foreign country' => array( $wrap( $foreign ) ),
            'postcode mismatch' => array( $wrap( self::destination() ) + array( 'shipping_address' => array( 'postcode' => '99999', 'country' => 'ID' ) ) ),
            'country mismatch' => array( $wrap( self::destination() ) + array( 'shipping_address' => array( 'postcode' => '12345', 'country' => 'US' ) ) ),
        );
    }

    #[Test]
    public function order_getters_validate_when_request_address_is_absent(): void {
        $result = $this->order( array( 'extensions' => array( 'kiriminaja-official' => array( 'destination' => self::destination() ) ) ), array( 'order_postcode' => '99999' ) );
        $this->assertSame( 'kiriof_invalid_destination', $result['error']['code'] );
    }

    #[Test]
    public function unrelated_order_shipping_lines_override_stale_kiriminaja_session(): void {
        $result = $this->order( array( 'extensions' => array( 'kiriminaja-official' => array( 'destination' => null ) ) ), array( 'order_methods' => array( 'flat_rate' ) ) );
        $this->assertSame(
            [
            '1: array_key_exists( error, result )' => false,
            '2: array_key_exists( _kiriof_buyer_destination, result[meta] )' => false,
            ],
            [
            '1: array_key_exists( error, result )' => array_key_exists( 'error', $result ),
            '2: array_key_exists( _kiriof_buyer_destination, result[meta] )' => array_key_exists( '_kiriof_buyer_destination', $result['meta'] ),
            ]
        );
    }

    #[Test]
    public function omitted_modern_snapshot_cannot_fall_back_to_a_modern_session(): void {
        $result = $this->runFixture( array( 'operation' => 'order', 'params' => array(), 'session' => array( 'chosen_shipping_methods' => array( 'kiriminaja-official_jnt_EZ' ), 'kiriof_buyer_destination' => self::destination(), 'kiriof_destination_area' => '222' ) ) );
        $this->assertSame( 'kiriof_invalid_destination', $result['error']['code'] );
    }

    #[Test]
    public function sync_country_must_match_known_customer_country(): void {
        $result = $this->runFixture( array( 'operation' => 'sync', 'customer_country' => 'US', 'data' => array( 'action' => 'sync_checkout', 'destination' => self::destination() ) ) );
        $this->assertSame(
            [
            '1: result[error][code]' => 'kiriof_invalid_destination',
            '2: result[session]' => array(),
            ],
            [
            '1: result[error][code]' => $result['error']['code'],
            '2: result[session]' => $result['session'],
            ]
        );
    }

    #[Test]
    public function processed_hook_does_not_revive_stale_session_after_modern_transients_are_consumed(): void {
        $result = $this->runFixture( array( 'operation' => 'processed', 'meta' => array( '_kiriof_buyer_destination' => self::destination(), '_kiriof_checkout_destination_present' => '1' ), 'session' => array( 'kiriof_expedition' => 'jne_REG', 'kiriof_destination_area' => '111', 'kiriof_destination_area_name' => 'Old district' ) ) );
        $this->assertSame(
            [
            '1: array_key_exists( error, result )' => false,
            '2: result[transaction]' => null,
            '3: result[meta][_kiriof_buyer_destination]' => self::destination(),
            ],
            [
            '1: array_key_exists( error, result )' => array_key_exists( 'error', $result ),
            '2: result[transaction]' => $result['transaction'],
            '3: result[meta][_kiriof_buyer_destination]' => $result['meta']['_kiriof_buyer_destination'],
            ]
        );
    }

    #[Test]
    public function modern_clear_preserves_canonical_postcode_history_without_customer_writes(): void {
        $destination = self::destination();
        $destination['district_label'] = 'Forged district';
        $destination['postcode'] = '12 345';
        $clear = self::destination();
        $clear['district_id'] = ''; $clear['district_label'] = '';
        $result = $this->runFixture( array( 'operation' => 'sync', 'customer' => true, 'updates' => array(
            array( 'action' => 'sync_checkout', 'destination' => $destination ),
            array( 'action' => 'sync_checkout', 'destination' => $clear ),
        ) ) );
        $this->assertSame(
            [
            '1: result[session][destination_id]' => '',
            '2: result[session][kiriof_destination_postcode_map][12345]' => array( 'destination_id' => '222', 'destination_name' => 'New district' ),
            '3: result[lookup_calls]' => array( '12345' ),
            '4: result[customer_saves]' => array(),
            ],
            [
            '1: result[session][destination_id]' => $result['session']['destination_id'],
            '2: result[session][kiriof_destination_postcode_map][12345]' => $result['session']['kiriof_destination_postcode_map']['12345'],
            '3: result[lookup_calls]' => $result['lookup_calls'],
            '4: result[customer_saves]' => $result['customer_saves'],
            ]
        );
    }

    #[Test]
    public function processed_hook_preserves_permanent_snapshot_and_does_not_fill_empty_label_from_session(): void {
        $meta = array( '_kiriof_buyer_destination' => self::destination(), '_kiriof_checkout_destination_present' => '1', '_kiriof_checkout_destination_area' => '222', '_kiriof_checkout_destination_area_name' => '', '_kiriof_checkout_postcode' => '12345', '_kiriof_checkout_expedition' => 'jnt_EZ' );
        $result = $this->runFixture( array( 'operation' => 'processed', 'meta' => $meta, 'session' => array( 'kiriof_destination_area_name' => 'Stale district' ) ) );
        $this->assertSame(
            [
            '1: result[transaction][kiriof_destination_area_name]' => '',
            '2: result[transaction][destination_zipcode]' => '12345',
            '3: result[meta][_kiriof_buyer_destination]' => self::destination(),
            '4: array_key_exists( _kiriof_checkout_destination_area, result[meta] )' => false,
            ],
            [
            '1: result[transaction][kiriof_destination_area_name]' => $result['transaction']['kiriof_destination_area_name'],
            '2: result[transaction][destination_zipcode]' => $result['transaction']['destination_zipcode'],
            '3: result[meta][_kiriof_buyer_destination]' => $result['meta']['_kiriof_buyer_destination'],
            '4: array_key_exists( _kiriof_checkout_destination_area, result[meta] )' => array_key_exists( '_kiriof_checkout_destination_area', $result['meta'] ),
            ]
        );
    }

    #[Test]
    public function sync_never_erases_customer_metadata_or_fetches_for_a_clear(): void {
        $expectedCases = $actualCases = [];
        $destination = self::destination();
        $destination['district_id'] = ''; $destination['district_label'] = '';
        $result = $this->runFixture( array( 'operation' => 'sync', 'customer' => true, 'data' => array( 'action' => 'sync_checkout', 'destination' => $destination ) ) );
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
            '1: result[customer_saves]' => array(),
            '2: result[lookup_calls]' => array(),
            ];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
            '1: result[customer_saves]' => $result['customer_saves'],
            '2: result[lookup_calls]' => $result['lookup_calls'],
            ];
        $result = $this->runFixture( array( 'operation' => 'sync', 'customer' => true, 'data' => array( 'action' => 'sync_checkout', 'destination' => self::destination() ) ) );
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = array();
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $result['customer_saves'];
        $this->assertSame( $expectedCases, $actualCases );
    }

    #[Test]
    public function server_identity_overrides_label_and_caches_postcode_results(): void {
        $expectedCases = $actualCases = [];
        $destination = self::destination(); $destination['district_label'] = 'Forged district';
        $update = array( 'action' => 'sync_checkout', 'destination' => $destination );
        $result = $this->runFixture( array( 'operation' => 'sync', 'updates' => array( $update, $update ) ) );
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
            '1: result[session][destination_name]' => 'New district',
            '2: result[lookup_calls]' => array( '12345' ),
            ];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
            '1: result[session][destination_name]' => $result['session']['destination_name'],
            '2: result[lookup_calls]' => $result['lookup_calls'],
            ];
        $result = $this->order( array( 'extensions' => array( 'kiriminaja-official' => array( 'destination' => $destination ) ) ) );
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = self::destination();
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $result['meta']['_kiriof_buyer_destination'];
        $this->assertSame( $expectedCases, $actualCases );
    }

    #[Test]
    public function unknown_district_and_api_failure_fail_closed_without_mutation(): void {
        $expectedCases = $actualCases = [];
        foreach ( array( array( 'lookup_rows' => array( array( 'id' => 111, 'text' => 'Other postcode district' ) ) ), array( 'lookup_status' => 400 ), array( 'lookup_throw' => true ) ) as $extra ) {
            $result = $this->runFixture( $extra + array( 'operation' => 'sync', 'session' => array( 'destination_id' => '111' ), 'data' => array( 'action' => 'sync_checkout', 'destination' => self::destination() ) ) );
            $contractCase = 'case ' . count( $expectedCases );
            $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                '1: array_key_exists( error, result )' => true,
                '2: result[session]' => array( 'destination_id' => '111' ),
                '3: str_contains( result[error][message], secret )' => false,
                ];
            $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                '1: array_key_exists( error, result )' => array_key_exists( 'error', $result ),
                '2: result[session]' => $result['session'],
                '3: str_contains( result[error][message], secret )' => str_contains( $result['error']['message'], 'secret' ),
                ];
            $result = $this->order( array( 'extensions' => array( 'kiriminaja-official' => array( 'destination' => self::destination() ) ) ), $extra );
            $contractCase = 'case ' . count( $expectedCases );
            $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                '1: array_key_exists( error, result )' => true,
                '2: array_key_exists( _kiriof_buyer_destination, result[meta] ?? array() )' => false,
                ];
            $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                '1: array_key_exists( error, result )' => array_key_exists( 'error', $result ),
                '2: array_key_exists( _kiriof_buyer_destination, result[meta] ?? array() )' => array_key_exists( '_kiriof_buyer_destination', $result['meta'] ?? array() ),
                ];
        }
        $this->assertSame( $expectedCases, $actualCases );
    }

    #[Test]
    public function legacy_update_clears_modern_contract_marker(): void {
        $result = $this->runFixture( array( 'operation' => 'sync', 'session' => array( 'kiriof_buyer_destination' => self::destination(), 'kiriof_buyer_destination_coordinates' => array( 'latitude' => '0', 'longitude' => '0' ) ), 'data' => array( 'destination_id' => 111, 'destination_name' => 'Legacy' ) ) );
        $this->assertSame(
            [
            '1: result[session][kiriof_buyer_destination]' => null,
            '2: result[session][kiriof_buyer_destination_coordinates]' => null,
            ],
            [
            '1: result[session][kiriof_buyer_destination]' => $result['session']['kiriof_buyer_destination'],
            '2: result[session][kiriof_buyer_destination_coordinates]' => $result['session']['kiriof_buyer_destination_coordinates'],
            ]
        );
    }

    #[Test]
    public function global_insurance_cannot_be_disabled_by_modern_payload(): void {
        $result = $this->runFixture( array( 'operation' => 'sync', 'global_insurance' => true, 'data' => array( 'action' => 'sync_checkout', 'insurance' => false, 'force_insurance' => false ) ) );
        foreach ( array( 'kiriof_insurance', 'billing_insurance', 'force_insurance', 'kiriof_force_insurance' ) as $key ) {
            $this->assertSame( 1, $result['session'][$key] );
        }
    }

    #[Test]
    public function transaction_failure_retains_retry_context_and_success_persists_customer(): void {
        $expectedCases = $actualCases = [];
        $meta = array( '_kiriof_buyer_destination' => self::destination(), '_kiriof_checkout_destination_present' => '1', '_kiriof_checkout_destination_area' => '222', '_kiriof_checkout_destination_area_name' => 'New district', '_kiriof_checkout_expedition' => 'jnt_EZ' );
        $session = array( 'kiriof_buyer_destination' => self::destination(), 'kiriof_buyer_destination_coordinates' => array( 'latitude' => '0', 'longitude' => '0' ), 'kiriof_expedition' => 'jnt_EZ', 'kiriof_destination_area' => '222' );
        foreach ( array( array( 'transaction_status' => 400 ), array( 'transaction_throw' => true ) ) as $extra ) {
            $result = $this->runFixture( $extra + array( 'operation' => 'processed', 'customer' => true, 'meta' => $meta, 'session' => $session ) );
            $contractCase = 'case ' . count( $expectedCases );
            $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                '1: result[meta]' => $meta,
                '2: result[session]' => $session,
                '3: result[customer_saves]' => array(),
                ];
            $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                '1: result[meta]' => $result['meta'],
                '2: result[session]' => $result['session'],
                '3: result[customer_saves]' => $result['customer_saves'],
                ];
        }
        $result = $this->runFixture( array( 'operation' => 'processed', 'customer' => true, 'meta' => $meta, 'session' => $session ) );
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
            '1: result[customer_saves]' => array( array( 'shipping', '222', 'New district' ) ),
            '2: result[session][kiriof_buyer_destination]' => null,
            '3: array_key_exists( _kiriof_checkout_expedition, result[meta] )' => false,
            '4: result[session][kiriof_buyer_destination_coordinates]' => null,
            '5: array_key_exists( _kiriof_checkout_destination_present, result[meta] )' => false,
            '6: result[meta][_kiriof_buyer_destination]' => self::destination(),
            ];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
            '1: result[customer_saves]' => $result['customer_saves'],
            '2: result[session][kiriof_buyer_destination]' => $result['session']['kiriof_buyer_destination'],
            '3: array_key_exists( _kiriof_checkout_expedition, result[meta] )' => array_key_exists( '_kiriof_checkout_expedition', $result['meta'] ),
            '4: result[session][kiriof_buyer_destination_coordinates]' => $result['session']['kiriof_buyer_destination_coordinates'],
            '5: array_key_exists( _kiriof_checkout_destination_present, result[meta] )' => array_key_exists( '_kiriof_checkout_destination_present', $result['meta'] ),
            '6: result[meta][_kiriof_buyer_destination]' => $result['meta']['_kiriof_buyer_destination'],
            ];
        $this->assertSame( $expectedCases, $actualCases );
    }

    #[Test]
    public function cart_hash_invalidates_fees_after_quantity_change(): void {
        $result = $this->runFixture( array( 'operation' => 'fees', 'global_insurance' => true, 'hashes' => array( 'quantity-one', 'quantity-one', 'quantity-two' ), 'session' => array( 'destination_id' => 222, 'chosen_payment_method' => 'cod', 'chosen_shipping_methods' => array( 'kiriminaja-official_jnt_EZ' ) ) ) );
        $this->assertSame(
            [
            '1: array_key_exists( error, result )' => false,
            '2: count( result[calculations] )' => 2,
            '3: result[calculations][0][is_insurance]' => true,
            '4: result[contexts][0][cart_hash]' => 'quantity-one',
            '5: result[contexts][2][cart_hash]' => 'quantity-two',
            ],
            [
            '1: array_key_exists( error, result )' => array_key_exists( 'error', $result ),
            '2: count( result[calculations] )' => count( $result['calculations'] ),
            '3: result[calculations][0][is_insurance]' => $result['calculations'][0]['is_insurance'],
            '4: result[contexts][0][cart_hash]' => $result['contexts'][0]['cart_hash'],
            '5: result[contexts][2][cart_hash]' => $result['contexts'][2]['cart_hash'],
            ]
        );
    }

    private function order( array $params, array $extra = array() ): array {
        return $this->runFixture( $extra + array( 'operation' => 'order', 'params' => $params, 'session' => array( 'chosen_shipping_methods' => array( 'kiriminaja-official_jnt_EZ' ), 'kiriof_destination_area' => '111', 'kiriof_destination_area_name' => 'Old district' ) ) );
    }

    private function runFixture( array $input ): array {
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/buyer-destination-hardening-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $exitCode );
        $text = implode( "\n", $output );
        $this->assertSame( 0, $exitCode, $text );
        return json_decode( $text, true, 512, JSON_THROW_ON_ERROR );
    }
}
