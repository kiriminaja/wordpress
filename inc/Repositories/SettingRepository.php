<?php
namespace KiriminAjaOfficial\Repositories;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SettingRepository{
    
    public $table;
    private static array $setting_cache = array();
    private static array $whitelist_expedition_ids_cache = array();

    public function __construct(){
        global $wpdb;
        $this->table = $wpdb->prefix . 'kiriminaja_settings';
    }

    /**
     * Clear request-local setting caches after a write.
     *
     * API clients read credentials while they are constructed, so setup-key
     * updates must not leave an old API token cached in the repository.
     */
    public function clearCache(): void {
        self::$setting_cache                    = array();
        self::$whitelist_expedition_ids_cache  = array();
    }
    
    public function getIntegrationData(){
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_results( 
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE `key` IN (%s, %s)",
                'oid_prefix',
                'setup_key'
            )
        );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false; 
        }
        return $query;
    }
    /**
     * @param $payload
     * $payload['api_key']
     * $payload['oid_prefix']
     * $payload['setup_key']
     * @return true
     */
    public function storeIntegrationData($payload){
        global $wpdb;
        if (!$payload['api_key'] || !$payload['oid_prefix'] || !$payload['setup_key']){throw new \Exception('payload err');}
        
        $wpdb->update($this->table, array('value' => @$payload['api_key']), array('key' => 'api_key')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['oid_prefix']), array('key' => 'oid_prefix')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['setup_key']), array('key' => 'setup_key')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['callback_url']), array('key' => 'callback_url')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        // Store merchant type from API response. is_top = 'yes' means TOP merchant (published rate, no discount).
        $isTop = isset( $payload['is_top'] ) ? ( $payload['is_top'] ? 'yes' : 'no' ) : 'no';
        $wpdb->update( $this->table, array( 'value' => $isTop ), array( 'key' => 'is_top' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $this->clearCache();

        return true;
    }
    
    public function disconnectIntegration(){
        global $wpdb;
        
        $wpdb->update($this->table, array('value' => null), array('key' => 'api_key')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => null), array('key' => 'oid_prefix')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => null), array('key' => 'setup_key')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => null), array('key' => 'callback_url')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update( $this->table, array( 'value' => 'no' ), array( 'key' => 'is_top' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return true;
    }
    public function getOriginData(){
        global $wpdb;
        $table = $wpdb->prefix . 'kiriminaja_settings';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_results( "SELECT * FROM {$this->table} WHERE `key` IN ('origin_name','origin_phone','origin_address','origin_sub_district_id','origin_sub_district_name','origin_latitude','origin_longitude','origin_zip_code')" );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
    }
    /**
     * @param $payload
     * $payload['origin_name']
     * $payload['origin_phone']
     * $payload['origin_address']
     * $payload['origin_sub_district_id']
     * $payload['origin_sub_district_name']
     * $payload['origin_latitude']
     * $payload['origin_longitude']
     * @return true
     */
    public function storeOriginData($payload){
        global $wpdb;
        if (
            !$payload['origin_name'] 
            || 
            !$payload['origin_phone'] 
            || 
            !$payload['origin_address']
            ||
            !$payload['origin_latitude']
            ||
            !$payload['origin_longitude']
            ||  
            !$payload['origin_sub_district_id']
            || 
            !$payload['origin_sub_district_name']
        ){throw new \Exception('payload err');}
        $wpdb->update($this->table, array('value' => @$payload['origin_name']), array('key' => 'origin_name')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['origin_phone']), array('key' => 'origin_phone')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['origin_address']), array('key' => 'origin_address')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['origin_sub_district_id']), array('key' => 'origin_sub_district_id')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['origin_sub_district_name']), array('key' => 'origin_sub_district_name')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['origin_latitude']), array('key' => 'origin_latitude')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['origin_longitude']), array('key' => 'origin_longitude')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['origin_zip_code']), array('key' => 'origin_zip_code')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if( empty($wpdb->get_row("SELECT * FROM $this->table WHERE `key`='origin_whitelist_expedition_id'") ) ){
            $wpdb->insert(
                $this->table, 
                array(
                    'key' => 'origin_whitelist_expedition_id',
                    'value' => @$payload['origin_whitelist_expedition_id']
                ),
                array(
                    '%s',
                    '%s',
                ) 
            );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert(
                $this->table, 
                array(
                    'key' => 'origin_whitelist_expedition_name',
                    'value' => @$payload['origin_whitelist_expedition_name']
                ),
                array(
                    '%s',
                    '%s',
                ) 
            );
        }
        $wpdb->update($this->table, array('value' => @$payload['origin_whitelist_expedition_id']), array('key' => 'origin_whitelist_expedition_id')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($this->table, array('value' => @$payload['origin_whitelist_expedition_name']), array('key' => 'origin_whitelist_expedition_name')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        
        unset( self::$setting_cache[ $this->table . '|origin_whitelist_expedition_id' ], self::$setting_cache[ $this->table . '|origin_whitelist_expedition_name' ] );
        self::$whitelist_expedition_ids_cache = array();
        return true;
    }

    public function storeOriginMirrorData( array $payload ) {
        global $wpdb;

        $allowed_keys = array(
            'origin_name',
            'origin_phone',
            'origin_address',
            'origin_latitude',
            'origin_longitude',
            'origin_sub_district_id',
            'origin_sub_district_name',
            'origin_zip_code',
        );

        foreach ( $allowed_keys as $key ) {
            if ( ! array_key_exists( $key, $payload ) ) {
                continue;
            }

            $value = 'origin_address' === $key
                ? sanitize_textarea_field( (string) $payload[ $key ] )
                : sanitize_text_field( (string) $payload[ $key ] );

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE `key` = %s", $key ) );

            if ( empty( $existing ) ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->insert(
                    $this->table,
                    array(
                        'key'   => $key,
                        'value' => $value,
                    ),
                    array( '%s', '%s' )
                );
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update( $this->table, array( 'value' => $value ), array( 'key' => $key ) );
        }

        return true;
    }
    public function getCallbackData(){
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_results( "SELECT * FROM {$this->table} WHERE `key` IN ('callback_url')" );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        return $query;
        
    }
    /**
     * @param $payload
     * $payload['link_callback']
     * @return true
     */
    public function storeCallbackData($payload){
        global $wpdb;
        if (!$payload['callback_url']){throw new \Exception('payload err');}
        $wpdb->update($this->table, array('value' => @$payload['callback_url']), array('key' => 'callback_url')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return true;
    }
    public function getSettingByKey($key){
        global $wpdb;
        $cache_key = $this->table . '|' . (string) $key;
        if ( array_key_exists( $cache_key, self::$setting_cache ) ) {
            return self::$setting_cache[ $cache_key ];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $wpdb->get_row( 
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE `key` = %s",
                $key
            )
        );
        if (strlen(@$wpdb->last_error ?? '') > 0){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis(@$wpdb->last_error);
            return false;
        }
        self::$setting_cache[ $cache_key ] = $query;
        return $query;
    }
    public function getSettingByArray( $array ) {
        global $wpdb;

        if ( empty( $array ) || ! is_array( $array ) ) {
            return [];
        }

        $keys = array_values( array_unique( array_filter( array_map( 'sanitize_key', $array ) ) ) );
        if ( empty( $keys ) ) {
            return [];
        }

        $placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE `key` IN ({$placeholders})", $keys ) );
        
        if ( strlen( $wpdb->last_error ?? '' ) > 0 ) {
            ( new \KiriminAjaOfficial\Base\BaseInit() )->logThis( $wpdb->last_error );
            return false;
        }
        return $query;
    }
    /**
     * @param array $payload
     * $payload['enable_cod'] - 'yes' or 'no'
     * @return true
     */
    public function storeConfigData($payload){
        global $wpdb;
        if (!isset($payload['enable_cod'])){throw new \Exception('payload err');}

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $existing = $wpdb->get_row("SELECT * FROM {$this->table} WHERE `key`='enable_cod'");

        if (empty($existing)){
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert(
                $this->table,
                array(
                    'key'   => 'enable_cod',
                    'value' => sanitize_text_field($payload['enable_cod']),
                ),
                array('%s', '%s')
            );
        } else {
            $wpdb->update($this->table, array('value' => sanitize_text_field($payload['enable_cod'])), array('key' => 'enable_cod')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        return true;
    }

    /**
     * Store insurance setting.
     *
     * @param string $enable_insurance 'yes' or 'no'
     * @return true
     */
    public function storeInsuranceData($enable_insurance){
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $existing = $wpdb->get_row("SELECT * FROM {$this->table} WHERE `key`='enable_insurance'");

        if (empty($existing)){
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert($this->table, array('key' => 'enable_insurance', 'value' => sanitize_text_field($enable_insurance)), array('%s', '%s'));
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update($this->table, array('value' => sanitize_text_field($enable_insurance)), array('key' => 'enable_insurance'));
        }

        return true;
    }

    /** Missing policy uses the legacy whitelist; corrupt stored policy fails closed. */
    public function getCourierServiceSelection(): ?array {
        $row = $this->getSettingByKey( 'origin_whitelist_expedition_services' );
        if ( ! $row || null === $row->value ) {
            return null;
        }
        try {
            $selection = \KiriminAjaOfficial\Services\CourierServiceCatalog::parseSelection( $row->value );
            unset( $selection['ninja_inter'] );
            return $selection;
        } catch ( \InvalidArgumentException $e ) {
            return array();
        }
    }

    public function isCourierServiceEnabled( string $courier, string $service ): bool {
        $courier = strtolower( trim( $courier ) );
        if ( 'ninja_inter' === $courier ) {
            return false;
        }
        $selection = $this->getCourierServiceSelection();
        if ( null === $selection ) {
            if ( in_array( $courier, \KiriminAjaOfficial\Services\CourierServiceCatalog::instantCodes(), true ) ) {
                return false;
            }
            $ids = array_map( 'strtolower', $this->getWhitelistExpeditionIds() );
            return ( empty( $ids ) && ! $this->hasLegacyCourierRestriction() ) || in_array( $courier, $ids, true );
        }
        $enabled = $selection[ $courier ] ?? array();
        if ( in_array( '*', $enabled, true ) ) {
            return true;
        }
        $canonical = \KiriminAjaOfficial\Services\CourierServiceCatalog::canonicalService( $courier, $service );
        foreach ( $enabled as $code ) {
            if ( 0 === strcasecmp( trim( $service ), $code ) || ( null !== $canonical && $canonical === \KiriminAjaOfficial\Services\CourierServiceCatalog::canonicalService( $courier, $code ) ) ) {
                return true;
            }
        }
        return false;
    }

    public function validateWhiteListExpedition( $data ) {
        $datas = array();
        foreach ( $data as $row ) {
            $fields = (array) $row;
            if ( \KiriminAjaOfficial\Services\CourierServiceCatalog::isSupportedCourier( (string) ( $fields['service'] ?? '' ), $fields ) && $this->isCourierServiceEnabled( (string) ( $fields['service'] ?? '' ), (string) ( $fields['service_type'] ?? $fields['service_name'] ?? '' ) ) ) {
                $datas[] = $row;
            }
        }
        return $datas;
    }

    public function hasEnabledCourierServices(): bool {
        $selection = $this->getCourierServiceSelection();
        if ( null === $selection ) {
            return ! empty( $this->getWhitelistExpeditionIds() ) || ! $this->hasLegacyCourierRestriction();
        }
        foreach ( $selection as $services ) {
            if ( ! empty( $services ) ) {
                return true;
            }
        }
        return false;
    }

    public function getWhitelistExpeditionIds(): array {
        $cache_key = $this->table . '|origin_whitelist_expedition_id';
        if ( array_key_exists( $cache_key, self::$whitelist_expedition_ids_cache ) ) {
            return self::$whitelist_expedition_ids_cache[ $cache_key ];
        }

        $origin_whitelist_expedition_id = $this->getSettingByKey( 'origin_whitelist_expedition_id' );

        if ( empty( $origin_whitelist_expedition_id ) || empty( $origin_whitelist_expedition_id->value ) ) {
            self::$whitelist_expedition_ids_cache[ $cache_key ] = array();
            return array();
        }

        $ids = array_map( 'trim', explode( ',', (string) $origin_whitelist_expedition_id->value ) );
        $ids = array_filter(
            array_map(
                static function ( $expedition_id ) {
                    $id = sanitize_text_field( (string) $expedition_id );
                    return 'ninja_inter' === strtolower( $id ) ? '' : $id;
                },
                $ids
            )
        );

        self::$whitelist_expedition_ids_cache[ $cache_key ] = array_values( array_unique( $ids ) );

        return self::$whitelist_expedition_ids_cache[ $cache_key ];
    }

    /** Keep an unsupported-only legacy whitelist restrictive rather than allow-all. */
    public function hasLegacyCourierRestriction(): bool {
        $row = $this->getSettingByKey( 'origin_whitelist_expedition_id' );
        if ( ! $row || null === $row->value ) {
            return false;
        }
        foreach ( explode( ',', (string) $row->value ) as $id ) {
            if ( '' !== trim( sanitize_text_field( $id ) ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Convenience static method: return the raw value for a setting key, or null if not found.
     *
     * @param string $key Setting key.
     * @return string|null
     */
    public static function getValue( string $key ): ?string {
        $row = ( new self() )->getSettingByKey( $key );
        if ( $row && isset( $row->value ) ) {
            return $row->value;
        }
        return null;
    }

    /**
     * Store courier whitelist without touching other origin fields.
     *
     * @param array $payload
     * $payload['origin_whitelist_expedition_id']   - comma-separated courier codes
     * $payload['origin_whitelist_expedition_name'] - comma-separated courier names
     * @return true
     */
    public function storeCourierWhitelist( $payload ) {
        global $wpdb;
        $values = array(
            'origin_whitelist_expedition_id' => sanitize_text_field( $payload['origin_whitelist_expedition_id'] ?? '' ),
            'origin_whitelist_expedition_name' => sanitize_text_field( $payload['origin_whitelist_expedition_name'] ?? '' ),
        );
        // Legacy clients update CSV only. An existing service policy stays authoritative,
        // so this compatibility path cannot silently re-enable disabled services.
        if ( array_key_exists( 'service_selection', $payload ) ) {
            $selection = \KiriminAjaOfficial\Services\CourierServiceCatalog::parseSelection( $payload['service_selection'] );
            $values['origin_whitelist_expedition_services'] = json_encode( (object) $selection );
        }
        // The settings table uses the server's default engine (InnoDB on supported
        // installations). Keep the legacy mirrors and authoritative policy together.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
            throw new \RuntimeException( 'Unable to start courier settings transaction.' );
        }
        try {
            foreach ( $values as $key => $value ) {
                $this->writeCourierSetting( $key, $value );
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ( false === $wpdb->query( 'COMMIT' ) ) {
                throw new \RuntimeException( 'Unable to commit courier settings transaction.' );
            }
        } catch ( \Throwable $error ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ( false === $wpdb->query( 'ROLLBACK' ) ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Third argument is the previous Throwable, not rendered output; the message is a fixed string.
                throw new \RuntimeException( 'Unable to roll back courier settings transaction.', 0, $error );
            }
            throw $error;
        } finally {
            // Never publish intermediate writes through the request-local caches.
            // Clear all policy keys after commit or rollback, including read failures.
            foreach ( array( 'origin_whitelist_expedition_id', 'origin_whitelist_expedition_name', 'origin_whitelist_expedition_services' ) as $key ) {
                unset( self::$setting_cache[ $this->table . '|' . $key ] );
            }
            unset( self::$whitelist_expedition_ids_cache[ $this->table . '|origin_whitelist_expedition_id' ] );
        }
        return true;
    }

    private function writeCourierSetting( string $key, string $value ): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE `key` = %s", $key ) );
        if ( ! empty( $wpdb->last_error ) ) {
            throw new \RuntimeException( 'Unable to read courier settings.' );
        }
        if ( $existing ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->update( $this->table, array( 'value' => $value ), array( 'key' => $key ), array( '%s' ), array( '%s' ) );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->insert( $this->table, array( 'key' => $key, 'value' => $value ), array( '%s', '%s' ) );
        }
        if ( false === $result || ! empty( $wpdb->last_error ) ) {
            throw new \RuntimeException( 'Unable to save courier settings.' );
        }
    }
}
