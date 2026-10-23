<?php
namespace KiriminAjaOfficial\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Compare-and-repair persistence for the bounded origin identity migration.
 *
 * Repair requires InnoDB for every involved table, including WordPress options. Reads
 * bypass request caches; merchant edits always win over an obsolete snapshot.
 */
final class OriginAddressMigrationRepository {
    /**
     * Read the next complete row, including inactive origins.
     *
     * @param int $after_id Last visited location ID.
     * @return object|null Complete original database row plus, for defaults only,
     *     _origin_mirror (three raw legacy values; null means absent) and
     *     _origin_option (raw kiriof_wc_origin_area value, or null if absent).
     * @throws \RuntimeException When authoritative snapshot reads fail.
     */
    public function next( int $after_id ): ?object {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration cursor must see persisted rows.
        $snapshot = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE id > %d ORDER BY id ASC LIMIT 1',
                $wpdb->prefix . 'kiriminaja_shipment_location',
                $after_id
            )
        );
        if ( $wpdb->last_error ) {
            throw new \RuntimeException( 'Origin snapshot read failed.' );
        }
        if ( null === $snapshot || 1 !== (int) $snapshot->is_default ) {
            return $snapshot;
        }
        // Migration-only metadata, never location columns. Read persisted values
        // before remote matching so subsequent edits (including insert/delete)
        // cannot be mistaken for the original legacy fallback identity.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Snapshot must bypass settings caches.
        $settings = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id ASC', $wpdb->prefix . 'kiriminaja_settings' ) );
        if ( ! is_array( $settings ) || $wpdb->last_error ) {
            throw new \RuntimeException( 'Origin snapshot read failed.' );
        }
        $snapshot->_origin_mirror = array_fill_keys( array( 'origin_sub_district_id', 'origin_sub_district_name', 'origin_zip_code' ), null );
        $seen = array();
        foreach ( $settings as $setting ) {
            if ( array_key_exists( $setting->key, $snapshot->_origin_mirror ) ) {
                if ( isset( $seen[ $setting->key ] ) ) {
                    $snapshot->_origin_error = 'duplicate_mirrors';
                    return $snapshot;
                }
                $seen[ $setting->key ] = true;
                $snapshot->_origin_mirror[ $setting->key ] = $setting->value;
            }
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Options also belong to the prefetch snapshot.
        $options = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE option_name = %s', $wpdb->options, 'kiriof_wc_origin_area' ) );
        if ( ! is_array( $options ) || $wpdb->last_error || count( $options ) > 1 ) {
            throw new \RuntimeException( 'Origin snapshot read failed.' );
        }
        $snapshot->_origin_option = $options ? $options[0]->option_value : null;
        return $snapshot;
    }

    /**
     * Atomically replace identity only when the complete original row agrees.
     *
     * A default origin additionally requires every existing legacy identity
     * mirror to agree with its prefetch metadata and the old identity (a blank
     * seeded warehouse label may use the service-verified legacy label).
     * Missing mirrors are inserted;
     * duplicates or merchant changes abort the entire repair. Native WooCommerce
     * address/postcode options are deliberately never modified.
     *
     * @param object $snapshot Complete row returned by next().
     * @param array  $canonical Verified sub_district_id, sub_district_name, zip_code.
     * @return bool Whether the repair (including an already-correct row) committed.
     */
    public function repair( object $snapshot, array $canonical ): bool {
        global $wpdb;
        if ( isset( $snapshot->_origin_error ) || ! isset( $snapshot->id, $snapshot->is_default, $snapshot->sub_district_id, $snapshot->sub_district_name, $snapshot->zip_code )
            || (int) $snapshot->id < 1
            || ! isset( $canonical['sub_district_id'], $canonical['sub_district_name'], $canonical['zip_code'] )
            || ! is_scalar( $canonical['sub_district_id'] )
            || ! preg_match( '/^[1-9][0-9]*$/D', (string) $canonical['sub_district_id'] )
            || ! is_string( $canonical['sub_district_name'] ) || '' === trim( $canonical['sub_district_name'] )
            || 1 !== preg_match( '//u', $canonical['sub_district_name'] ) || preg_match_all( '/./us', $canonical['sub_district_name'] ) > 191
            || ! is_string( $canonical['zip_code'] ) || ! preg_match( '/^[0-9]{5}$/D', $canonical['zip_code'] ) ) {
            return false;
        }

        $is_default = 1 === (int) $snapshot->is_default;
        $tables = array( $wpdb->prefix . 'kiriminaja_shipment_location' );
        if ( $is_default ) {
            $tables[] = $wpdb->prefix . 'kiriminaja_settings';
            $tables[] = $wpdb->options;
        }
        // Refuse unsafe repairs rather than changing merchant table engines.
        // A custom origin never touches settings or options.
        foreach ( $tables as $table ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verify authoritative engine metadata before starting the transaction.
            $status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ) );
            if ( $wpdb->last_error || ! is_object( $status )
                || ! isset( $status->Name, $status->Engine ) || $table !== $status->Name
                || ! is_string( $status->Engine ) || 'innodb' !== strtolower( $status->Engine ) ) {
                return false;
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One transaction owns row and mirror locks.
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
            return false;
        }
        try {
            // Lock the small complete location set in ID order. This serializes
            // default switches, not just edits to the row being repaired.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Locking reads cannot use cached values.
            $locations = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id ASC FOR UPDATE', $wpdb->prefix . 'kiriminaja_shipment_location' ) );
            if ( ! is_array( $locations ) || $wpdb->last_error ) {
                throw new \RuntimeException( 'Location lock failed.' );
            }
            $current = null;
            $defaults = 0;
            foreach ( $locations as $location ) {
                $defaults += 1 === (int) $location->is_default ? 1 : 0;
                if ( (string) $location->id === (string) $snapshot->id ) {
                    $current = $location;
                }
            }
            if ( null === $current || ! $this->sameRow( $snapshot, $current ) || ( $is_default && 1 !== $defaults ) ) {
                throw new \RuntimeException( 'Origin changed.' );
            }
            if ( $is_default && ! $this->mirrorDefault( $snapshot, $canonical ) ) {
                throw new \RuntimeException( 'Origin mirrors changed or failed.' );
            }
            if ( (string) $current->sub_district_id !== (string) $canonical['sub_district_id']
                || $current->sub_district_name !== $canonical['sub_district_name']
                || $current->zip_code !== $canonical['zip_code'] ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Only the verified identity may change.
                $written = $wpdb->query(
                    $wpdb->prepare(
                        'UPDATE %i SET sub_district_id = %d, sub_district_name = %s, zip_code = %s, updated_at = %s WHERE id = %d',
                        $wpdb->prefix . 'kiriminaja_shipment_location',
                        (int) $canonical['sub_district_id'], $canonical['sub_district_name'], $canonical['zip_code'], current_time( 'mysql' ), (int) $snapshot->id
                    )
                );
                if ( 1 !== $written ) {
                    throw new \RuntimeException( 'Origin write failed.' );
                }
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A failed commit is not success.
            if ( false === $wpdb->query( 'COMMIT' ) ) {
                throw new \RuntimeException( 'Origin commit failed.' );
            }
        } catch ( \Throwable $error ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Never publish partial mirrors.
            $wpdb->query( 'ROLLBACK' );
            return false;
        }
        if ( $is_default ) {
            ( new SettingRepository() )->clearCache();
            wp_cache_delete( 'kiriof_wc_origin_area', 'options' );
            wp_cache_delete( 'alloptions', 'options' );
            wp_cache_delete( 'notoptions', 'options' );
        }
        return true;
    }

    /**
     * Compare all columns, preserving NULL and exact text but tolerating wpdb's
     * numeric string representation. No address, pin or timestamp is omitted.
     *
     * @param object $expected Original row.
     * @param object $current Locked row.
     * @return bool
     */
    private function sameRow( object $expected, object $current ): bool {
        $old = get_object_vars( $expected );
        $new = get_object_vars( $current );
        // Only these documented migration metadata properties are not columns.
        unset( $old['_origin_mirror'], $old['_origin_option'] );
        if ( count( $old ) !== count( $new ) ) {
            return false;
        }
        foreach ( $new as $key => $value ) {
            if ( ! array_key_exists( $key, $old ) || ( null === $value ) !== ( null === $old[ $key ] )
                || ( null !== $value && ( ! is_scalar( $old[ $key ] ) || (string) $value !== (string) $old[ $key ] ) ) ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Lock and conditionally mirror the default identity inside repair's transaction.
     *
     * @param object $snapshot Original default row.
     * @param array  $canonical Verified identity.
     * @return bool
     */
    private function mirrorDefault( object $snapshot, array $canonical ): bool {
        global $wpdb;
        // The legacy key column is not unique. Lock all rows (including gaps)
        // and reject duplicates rather than silently updating several values.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock missing-key insertion ranges too.
        $settings = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id ASC FOR UPDATE', $wpdb->prefix . 'kiriminaja_settings' ) );
        if ( ! is_array( $settings ) || $wpdb->last_error ) {
            return false;
        }
        // A legacy label is usable only with continuity to the original ID.
        // The service verifies the label against the canonical address; this
        // repository verifies the exact saved values have not changed meanwhile.
        if ( '' === trim( (string) $snapshot->sub_district_name )
            && ( ! isset( $snapshot->_origin_mirror['origin_sub_district_id'] )
                || (string) $snapshot->_origin_mirror['origin_sub_district_id'] !== (string) $snapshot->sub_district_id ) ) {
            return false;
        }
        $mirrors = array(
            'origin_sub_district_id'   => 'sub_district_id',
            'origin_sub_district_name' => 'sub_district_name',
            'origin_zip_code'          => 'zip_code',
        );
        foreach ( $mirrors as $key => $field ) {
            $matches = array();
            foreach ( $settings as $setting ) {
                if ( $key === $setting->key ) {
                    $matches[] = $setting;
                }
            }
            if ( count( $matches ) > 1 ) {
                return false;
            }
            if ( property_exists( $snapshot, '_origin_mirror' ) ) {
                if ( ! is_array( $snapshot->_origin_mirror ) || ! array_key_exists( $key, $snapshot->_origin_mirror ) ) {
                    return false;
                }
                $original = $snapshot->_origin_mirror[ $key ];
                if ( ( null === $original ) !== ( ! $matches )
                    || ( $matches && $matches[0]->value !== $original ) ) {
                    return false;
                }
                // A missing legacy mirror can be created. Existing ID/zip must
                // still describe this warehouse. A blank seeded warehouse label
                // may use the legacy label already verified by the matcher.
                if ( null !== $original
                    && ! ( 'sub_district_name' === $field && '' === trim( (string) $snapshot->$field ) )
                    && ! ( 'zip_code' === $field && '' === (string) $snapshot->$field )
                    && (string) $original !== (string) $snapshot->$field ) {
                    return false;
                }
            } elseif ( $matches && (string) $matches[0]->value !== (string) $snapshot->$field ) {
                return false;
            }
            if ( ! $matches ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Insert only absent mirrors.
                $written = $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (`key`, `value`) VALUES (%s, %s)', $wpdb->prefix . 'kiriminaja_settings', $key, (string) $canonical[ $field ] ) );
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Locked row ID avoids nonunique-key writes.
                $written = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET `value` = %s WHERE id = %d AND `key` = %s', $wpdb->prefix . 'kiriminaja_settings', (string) $canonical[ $field ], (int) $matches[0]->id, $key ) );
            }
            if ( false === $written ) {
                return false;
            }
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct SQL is required for transaction-safe option CAS.
        $options = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE option_name = %s FOR UPDATE', $wpdb->options, 'kiriof_wc_origin_area' ) );
        if ( ! is_array( $options ) || $wpdb->last_error || count( $options ) > 1
            || ( $options && (string) $options[0]->option_value !== (string) $snapshot->sub_district_id )
            || ( property_exists( $snapshot, '_origin_option' )
                && ( ( null === $snapshot->_origin_option ) !== ( ! $options )
                    || ( $options && $options[0]->option_value !== $snapshot->_origin_option ) ) ) ) {
            return false;
        }
        if ( $options ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Preserve existing autoload and unrelated options.
            return false !== $wpdb->query( $wpdb->prepare( 'UPDATE %i SET option_value = %s WHERE option_id = %d AND option_name = %s', $wpdb->options, (string) $canonical['sub_district_id'], (int) $options[0]->option_id, 'kiriof_wc_origin_area' ) );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WordPress stores scalar ID options as strings, not serialized arrays.
        return false !== $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s)', $wpdb->options, 'kiriof_wc_origin_area', (string) $canonical['sub_district_id'], 'no' ) );
    }
}
