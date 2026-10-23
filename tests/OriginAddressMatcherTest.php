<?php

declare(strict_types=1);

use KiriminAjaOfficial\Services\OriginAddressMatcher;
use PHPUnit\Framework\TestCase;

final class OriginAddressMatcherTest extends TestCase {
    private const LABEL = 'Sariharjo, Ngaglik, Sleman, DI Yogyakarta, 55581';
    private const SAVED = 'Sari Harjo, Ngaglik, Sleman, DI Yogyakarta, 55581';

    private function row( array $changes = array() ): array {
        return array_replace( array(
            'id' => 123, 'subdistrict_id' => 123, 'district_id' => 12,
            'city_id' => 34, 'province_id' => 5, 'text' => self::LABEL,
            'zip_code' => '55581', 'subdistrict_name' => 'Sariharjo',
            'district_name' => 'Ngaglik', 'city_name' => 'Sleman',
            'province_name' => 'DI Yogyakarta',
        ), $changes );
    }

    private function canonical(): array {
        return array( 'sub_district_id' => 123, 'sub_district_name' => self::LABEL, 'zip_code' => '55581' );
    }

    public function test_verified_id_accepts_array_or_public_object_and_partial_or_empty_label(): void {
        foreach ( array( $this->row(), (object) $this->row() ) as $row ) {
            foreach ( array( '', 'Sariharjo', self::SAVED ) as $label ) {
                self::assertSame( $this->canonical(), OriginAddressMatcher::match( '123', $label, '55581', array( $row ) ) );
            }
        }
    }

    public function test_repairs_stale_id_only_with_complete_whitespace_and_case_normalized_hierarchy(): void {
        foreach ( array( '999', '', 0, 'invalid-old-id' ) as $id ) {
            self::assertSame( $this->canonical(), OriginAddressMatcher::match( $id, " SARI Harjo , Nga glik , Sleman , DI YogyaKarta , 55581 ", '', array( $this->row() ) ) );
        }
        self::assertSame( $this->canonical(), OriginAddressMatcher::match( 999, self::SAVED, 55581, array( $this->row() ) ) );
    }

    public function test_postcode_derivation_and_explicit_conflicts(): void {
        self::assertSame( '55581', OriginAddressMatcher::postcode( self::SAVED, '' ) );
        self::assertSame( '55581', OriginAddressMatcher::postcode( '', 55581 ) );
        foreach ( array( '55582', '5558', '555810', ' 55581 ', false, null, array(), new stdClass() ) as $zip ) {
            self::assertNull( OriginAddressMatcher::postcode( self::SAVED, $zip ) );
            self::assertNull( OriginAddressMatcher::match( 123, self::SAVED, $zip, array( $this->row() ) ) );
        }
        self::assertNull( OriginAddressMatcher::postcode( 'Sariharjo, bad', '55581' ) );
        self::assertNull( OriginAddressMatcher::postcode( '', '' ) );
    }

    public function test_unknown_id_never_selects_sole_row_from_incomplete_or_fuzzy_label(): void {
        foreach ( array( '', 'Sariharjo', 'Sariharjo, Ngaglik, Sleman, 55581', 'Sariharjo,,Sleman, DI Yogyakarta, 55581', 'Sariharjo, Ngaglik, Sleman, DIY, 55581', 'Sari-harjo, Ngaglik, Sleman, DI Yogyakarta, 55581', 'Sariharjo Baru, Ngaglik, Sleman, DI Yogyakarta, 55581', self::LABEL . ', Indonesia' ) as $label ) {
            self::assertNull( OriginAddressMatcher::match( 999, $label, '55581', array( $this->row() ) ), $label );
        }
    }

    public function test_same_id_cannot_be_repurposed_even_when_another_row_matches_label(): void {
        $repurposed = $this->row( array( 'text' => 'Other, Ngaglik, Sleman, DI Yogyakarta, 55581' ) );
        $old = $this->row( array( 'id' => 456, 'subdistrict_id' => 456 ) );
        self::assertNull( OriginAddressMatcher::match( 123, self::SAVED, '55581', array( $repurposed, $old ) ) );
        self::assertNull( OriginAddressMatcher::match( 123, 'Sariharjo, Ngaglik, Other, DI Yogyakarta, 55581', '55581', array( $this->row() ) ) );
    }

    public function test_ambiguity_and_duplicate_ids_fail_closed_in_either_order(): void {
        $other = $this->row( array( 'id' => 456, 'subdistrict_id' => 456 ) );
        foreach ( array( array( $this->row(), $other ), array( $other, $this->row() ) ) as $rows ) {
            self::assertNull( OriginAddressMatcher::match( 999, self::SAVED, '55581', $rows ) );
            self::assertSame( $this->canonical(), OriginAddressMatcher::match( 123, self::SAVED, '55581', $rows ) );
        }
        self::assertNull( OriginAddressMatcher::match( 123, self::SAVED, '55581', array( $this->row(), $this->row() ) ) );
        self::assertNull( OriginAddressMatcher::match( 123, self::SAVED, '55581', array( $this->row(), $this->row( array( 'district_id' => 99 ) ) ) ) );
    }

    public function test_wrong_postcode_empty_error_or_invalid_rows_do_not_establish_identity(): void {
        foreach ( array(
            array(), array( 'status' => false, 'text' => 'error' ), array( null ), array( 'error' ),
            array( $this->row( array( 'zip_code' => '55582' ) ) ),
            array( $this->row( array( 'zip_code' => '55582', 'text' => str_replace( '55581', '55582', self::LABEL ) ) ) ),
            array( $this->row( array( 'district_id' => 0 ) ) ),
            array( $this->row( array( 'id' => 456 ) ) ),
            array( $this->row( array( 'text' => 'Sariharjo' ) ) ),
        ) as $rows ) {
            self::assertNull( OriginAddressMatcher::match( 123, self::SAVED, '55581', $rows ) );
        }
        $row = $this->row();
        unset( $row['id'] );
        self::assertSame( $this->canonical(), OriginAddressMatcher::match( 123, self::SAVED, '55581', array( $row ) ) );
    }

    public function test_row_ids_are_strict_and_all_four_hierarchy_components_are_required(): void {
        foreach ( array( false, 12.0, array(), new stdClass(), '12e0', ' 12 ', '-12', '999999999999999999999999' ) as $bad ) {
            self::assertNull( OriginAddressMatcher::match( 123, self::SAVED, '55581', array( $this->row( array( 'district_id' => $bad ) ) ) ) );
        }
        foreach ( array( 0, 1, 2, 3 ) as $index ) {
            $parts = explode( ', ', self::LABEL );
            $parts[ $index ] = 'Different';
            self::assertNull( OriginAddressMatcher::match( 999, implode( ', ', $parts ), '55581', array( $this->row() ) ) );
        }
        self::assertNull( OriginAddressMatcher::match( 123, "\xFF", '55581', array( $this->row() ) ) );
        $row = $this->row();
        unset( $row['subdistrict_id'] );
        self::assertSame( $this->canonical(), OriginAddressMatcher::match( 123, '', '55581', array( $row ) ) );
    }

    public function test_malicious_types_and_resource_limits_are_rejected_without_coercion_or_mutation(): void {
        $object = new class {
            public function __toString(): string { throw new RuntimeException( 'Must not cast objects' ); }
        };
        foreach ( array( $object, array(), true, 123.0, null ) as $bad ) {
            self::assertNull( OriginAddressMatcher::match( $bad, self::SAVED, '55581', array( $this->row() ) ) );
            self::assertNull( OriginAddressMatcher::match( 123, $bad, '55581', array( $this->row() ) ) );
            self::assertNull( OriginAddressMatcher::match( 123, self::SAVED, '55581', array( $this->row( array( 'text' => $bad ) ) ) ) );
        }
        self::assertNull( OriginAddressMatcher::match( 123, str_repeat( 'x', 1001 ), '55581', array( $this->row() ) ) );
        self::assertNull( OriginAddressMatcher::match( 123, self::SAVED, '55581', array( $this->row( array( 'text' => str_repeat( 'x', 1001 ) ) ) ) ) );
        self::assertNull( OriginAddressMatcher::match( 123, self::SAVED, '55581', array_fill( 0, 501, $this->row() ) ) );
        $rows = array( (object) $this->row() );
        $before = serialize( $rows );
        OriginAddressMatcher::match( 999, self::SAVED, '', $rows );
        self::assertSame( $before, serialize( $rows ) );
    }
}
