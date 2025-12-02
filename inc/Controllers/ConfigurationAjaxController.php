<?php

namespace Inc\Controllers;

use Inc\Base\BaseInit;
use Inc\Repositories\SettingRepository;

class ConfigurationAjaxController extends BaseInit {
    
    private $settingRepo;
    
    public function __construct() {
        parent::__construct();
        $this->settingRepo = new SettingRepository();
    }
    
    public function register() {
        // AJAX endpoints for configuration
        add_action('wp_ajax_kiriminaja_get_settings', array($this, 'getSettings'));
        add_action('wp_ajax_kiriminaja_save_settings', array($this, 'saveSettings'));
    }
    
    /**
     * Get settings for a specific tab
     */
    public function getSettings() {
        // No nonce check needed for reading settings, but verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $tab = sanitize_text_field($_POST['tab'] ?? 'integration');
        
        $keys = $this->getKeysForTab($tab);
        $settings = array();
        
        if (!empty($keys)) {
            $results = $this->settingRepo->getSettingByArray($keys);
            foreach ($results as $setting) {
                $settings[$setting->key] = $setting->value;
            }
        }
        
        // Check if WooCommerce is active
        $is_woocommerce_active = class_exists('WooCommerce');
        
        wp_send_json_success(array(
            'settings' => $settings,
            'is_woocommerce_active' => $is_woocommerce_active
        ));
    }
    
    /**
     * Save settings for a specific tab
     */
    public function saveSettings() {
        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $tab = sanitize_text_field($_POST['tab'] ?? 'integration');
        $settings_json = wp_unslash($_POST['settings'] ?? '{}');
        $settings = json_decode($settings_json, true);
        
        if (!is_array($settings)) {
            wp_send_json_error(array('message' => 'Invalid settings data'));
            return;
        }
        
        $allowed_keys = $this->getKeysForTab($tab);
        
        foreach ($settings as $key => $value) {
            if (in_array($key, $allowed_keys)) {
                $this->settingRepo->updateOrCreateSetting(
                    sanitize_text_field($key),
                    sanitize_text_field($value)
                );
            }
        }
        
        wp_send_json_success(array(
            'message' => 'Settings saved successfully'
        ));
    }
    
    /**
     * Get allowed setting keys for a specific tab
     */
    private function getKeysForTab($tab) {
        $keys_map = array(
            'integration' => array('setup_key', 'oid_prefix'),
            'shipping' => array(
                'origin_name',
                'origin_phone',
                'origin_address',
                'origin_latitude',
                'origin_longitude',
                'origin_sub_district_id',
                'origin_sub_district_name',
                'origin_zip_code',
                'origin_whitelist_expedition_id',
                'origin_whitelist_expedition_name'
            ),
            'advanced' => array('callback_url')
        );
        
        return $keys_map[$tab] ?? array();
    }
}
