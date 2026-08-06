<?php

namespace KiriminAjaOfficial\Repositories;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ShipmentLocationRepository
 *
 * Data layer for the multi-origin "Shipment Locations" feature.
 *
 * A shipment location is an independent fulfillment origin (name, address,
 * sub-district, postcode, lat/long). One location is always the active default
 * used as the fallback for products that do not have an explicit binding.
 *
 * Storage uses a dedicated dbDelta-managed table (see SetupMigration), which
 * matches the plugin's existing kiriminaja_transaction table pattern.
 */
class ShipmentLocationRepository
{
    const TABLE_NAME = 'kiriminaja_shipment_location';

    /**
     * Fully-qualified table name.
     *
     * @return string
     */
    public function getTableName()
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Insert a new location.
     *
     * @param array $data {
     *     @type string $name
     *     @type string $phone
     *     @type string $address
     *     @type int    $sub_district_id
     *     @type string $zip_code
     *     @type string $latitude
     *     @type string $longitude
     *     @type int    $is_default (0|1)
     *     @type int    $is_active  (0|1)
     * }
     * @return int|false Inserted location ID or false on failure.
     */
    public function insert(array $data)
    {
        global $wpdb;

        $now     = current_time('mysql');
        $payload = array(
            'name'            => isset($data['name']) ? sanitize_text_field($data['name']) : '',
            'phone'           => isset($data['phone']) ? sanitize_text_field($data['phone']) : '',
            'address'         => isset($data['address']) ? sanitize_textarea_field($data['address']) : '',
            'sub_district_id' => isset($data['sub_district_id']) ? (int) $data['sub_district_id'] : 0,
            'sub_district_name' => isset($data['sub_district_name']) ? sanitize_text_field($data['sub_district_name']) : '',
            'zip_code'        => isset($data['zip_code']) ? sanitize_text_field($data['zip_code']) : '',
            'latitude'        => isset($data['latitude']) ? sanitize_text_field($data['latitude']) : '',
            'longitude'       => isset($data['longitude']) ? sanitize_text_field($data['longitude']) : '',
            'is_default'      => !empty($data['is_default']) ? 1 : 0,
            'is_active'       => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
            'created_at'      => $now,
            'updated_at'      => $now,
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table insert via $wpdb.
        $inserted = $wpdb->insert(
            $this->getTableName(),
            $payload,
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s')
        );

        if (false === $inserted) {
            return false;
        }

        $id = (int) $wpdb->insert_id;

        // Enforce a single default row.
        if (1 === $payload['is_default']) {
            $this->clearDefaultExcept($id);
        }

        return $id;
    }

    /**
     * Update an existing location.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public function update($id, array $data)
    {
        global $wpdb;
        $id = (int) $id;
        if ($id <= 0) {
            return false;
        }

        $fields  = array();
        $formats = array();

        $map = array(
            'name'            => '%s',
            'phone'           => '%s',
            'address'         => '%s',
            'sub_district_id' => '%d',
            'sub_district_name' => '%s',
            'zip_code'        => '%s',
            'latitude'        => '%s',
            'longitude'       => '%s',
            'is_default'      => '%d',
            'is_active'       => '%d',
        );

        foreach ($map as $key => $format) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if ('%d' === $format) {
                $value = (int) $value;
            } elseif ('address' === $key) {
                $value = sanitize_textarea_field($value);
            } else {
                $value = sanitize_text_field($value);
            }
            $fields[$key] = $value;
            $formats[]    = $format;
        }

        $fields['updated_at'] = current_time('mysql');
        $formats[]            = '%s';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table update via $wpdb.
        $updated = $wpdb->update(
            $this->getTableName(),
            $fields,
            array('id' => $id),
            $formats,
            array('%d')
        );

        if (false === $updated) {
            return false;
        }

        if (isset($fields['is_default']) && 1 === (int) $fields['is_default']) {
            $this->clearDefaultExcept($id);
        }

        return true;
    }

    /**
     * Permanently delete a location.
     *
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table delete via $wpdb.
        $deleted = $wpdb->delete($this->getTableName(), array('id' => (int) $id), array('%d'));
        return false !== $deleted;
    }

    /**
     * Fetch a single location by ID.
     *
     * @param int $id
     * @return object|null
     */
    public function getById($id)
    {
        global $wpdb;
        $table = $this->getTableName();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal static table name.
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $id));
    }

    /**
     * Fetch all locations (active first, default first).
     *
     * @param bool $activeOnly
     * @return array
     */
    public function getAll($activeOnly = false)
    {
        global $wpdb;
        $table = $this->getTableName();
        $where = $activeOnly ? 'WHERE is_active = 1' : '';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal static table name.
        $rows = $wpdb->get_results("SELECT * FROM {$table} {$where} ORDER BY is_default DESC, id ASC");
        return is_array($rows) ? $rows : array();
    }

    /**
     * Fetch the default location.
     *
     * @return object|null
     */
    public function getDefault()
    {
        global $wpdb;
        $table = $this->getTableName();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal static table name.
        $row = $wpdb->get_row("SELECT * FROM {$table} WHERE is_default = 1 ORDER BY id ASC LIMIT 1");
        if ($row) {
            return $row;
        }
        // Fallback: first active location, then first location at all.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal static table name.
        $row = $wpdb->get_row("SELECT * FROM {$table} WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
        if ($row) {
            return $row;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal static table name.
        return $wpdb->get_row("SELECT * FROM {$table} ORDER BY id ASC LIMIT 1");
    }

    /**
     * Count all locations.
     *
     * @return int
     */
    public function count()
    {
        global $wpdb;
        $table = $this->getTableName();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal static table name.
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * Mark one location as default and clear the flag on all others.
     *
     * @param int $id
     * @return bool
     */
    public function setDefault($id)
    {
        $id = (int) $id;
        $this->update($id, array('is_default' => 1));
        $this->clearDefaultExcept($id);
        return true;
    }

    /**
     * Ensure at least one default location exists. If none, promote the first.
     *
     * @return void
     */
    public function ensureDefaultExists()
    {
        $default = $this->getDefault();
        if ($default) {
            return;
        }
        $all = $this->getAll();
        if (!empty($all)) {
            $this->setDefault((int) $all[0]->id);
        }
    }

    /**
     * Clear the default flag on every row except the given ID.
     *
     * @param int $exceptId
     * @return void
     */
    private function clearDefaultExcept($exceptId)
    {
        global $wpdb;
        $table = $this->getTableName();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal static table name.
        $wpdb->query($wpdb->prepare("UPDATE {$table} SET is_default = 0 WHERE id != %d", (int) $exceptId));
    }
}
