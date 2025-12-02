<?php

namespace Inc\Base;

use \Inc\Base\BaseInit;

class Enqueue extends BaseInit{
    
    private $is_vite_enabled = true;
    private $is_vite_dev = true;
    private $vite_server = 'http://localhost:3000';
    
    public function __construct() {
        parent::__construct();
        
        // Check if Vite manifest exists (production) or dev server is running (development)
        $this->is_vite_enabled = $this->checkViteEnabled();
        $this->is_vite_dev = false;
        
        if ($this->is_vite_enabled) {
            $this->is_vite_dev = $this->isViteDevServerRunning();
        }
    }
    
    public function register(){
        /** enqueue js & CSS */
        /* admin */
        add_action('admin_enqueue_scripts', array($this,'enqueueAdmin'));
        /* WP */
        add_action('wp_enqueue_scripts', array($this,'enqueueWp'));
        
        // Add Vite client for HMR in development
        if ($this->is_vite_dev) {
            add_action('admin_head', array($this, 'addViteDevClient'), 999);
            add_action('wp_head', array($this, 'addViteDevClient'), 999);
        }
    }
    
    /**
     * Check if Vite is enabled (manifest exists or dev server running)
     */
    private function checkViteEnabled() {
        $manifest_path = KJ_DIR . 'assets/.vite/manifest.json';
        $has_manifest = file_exists($manifest_path);
        
        // In debug mode, also check if dev server is running
        if (!$has_manifest && defined('WP_DEBUG') && WP_DEBUG) {
            return $this->isViteDevServerRunning();
        }
        
        return $has_manifest;
    }
    
    /**
     * Check if Vite dev server is running
     */
    private function isViteDevServerRunning() {
        $response = wp_remote_get($this->vite_server, array('timeout' => 1));
        return !is_wp_error($response);
    }
    
    /**
     * Get asset URL from Vite manifest
     */
    private function getViteAsset($entry) {
        if ($this->is_vite_dev) {
            return $this->vite_server . '/frontend/src/' . $entry;
        }
        
        $manifest_path = KJ_DIR . 'assets/.vite/manifest.json';
        if (!file_exists($manifest_path)) {
            return false;
        }
        
        $manifest = json_decode(file_get_contents($manifest_path), true); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        
        if (isset($manifest[$entry])) {
            return $this->plugin_url . 'assets/' . $manifest[$entry]['file'];
        }
        
        return false;
    }
    
    /**
     * Get CSS assets from manifest
     */
    private function getViteCss($entry) {
        if ($this->is_vite_dev) {
            return array();
        }
        
        $manifest_path = KJ_DIR . 'assets/.vite/manifest.json';
        if (!file_exists($manifest_path)) {
            return array();
        }
        
        $manifest = json_decode(file_get_contents($manifest_path), true); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        
        if (isset($manifest[$entry]['css'])) {
            return array_map(function($css) {
                return $this->plugin_url . 'assets/' . $css;
            }, $manifest[$entry]['css']);
        }
        
        return array();
    }
    
    /**
     * Add Vite dev client for HMR
     */
    public function addViteDevClient() {
        $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS);
        if (!in_array($page,[
            'settings',
            'transactions',
            'payment'])){return;}
            
        echo '<script type="module" crossorigin src="' . esc_url($this->vite_server . '/@vite/client') . '"></script>' . "\n";
    }

    /** Add Enqueue CSS & JS*/
    function enqueueWp(){

        wp_localize_script(
            'myjs',
            'myjs',
            array(
                'ajaxurl' => admin_url( 'admin-ajax.php' )
            )
        );
        
        wp_enqueue_script( 'select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true ); // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent
        wp_enqueue_style( 'select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', array(), '4.0.13' ); // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent

        wp_enqueue_style('kiriminPluginStyle', $this->plugin_url.'assets/wp/css/kj-wp-style.css',array(),wp_rand(),'all');

        // Option 1: Manually enqueue the wp-util library.
        wp_enqueue_script( 'wp-util' );
        
        // Legacy script (fallback or coexist)
        wp_enqueue_script('kiriminPluginScript', $this->plugin_url.'assets/wp/js/kj-wp-script.js', [ 'wp-util' ], KJ_PLUGIN_VERSION, true);
    }
    
    function enqueueAdmin(){

        $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS);
        if (!in_array($page,[
            'settings',
            'transactions',
            'payment'])){return;}

        wp_localize_script(
            'myjs',
            'myjs',
            array(
                'ajaxurl' => admin_url( 'admin-ajax.php' )
            )
        );
        wp_enqueue_style('kiriminPluginStyle', $this->plugin_url.'assets/admin/css/kj-admin-style.css',array(),KJ_PLUGIN_VERSION,'all');
        
        wp_enqueue_style('BSGridStyle', $this->plugin_url.'assets/admin/css/bootstrap-grid.css', array(), KJ_PLUGIN_VERSION);

        wp_enqueue_style('kj'.'wc_5', $this->plugin_url.'assets/admin/css/kj-wc-style/app.style.css', array(), KJ_PLUGIN_VERSION);
        wp_enqueue_style('kj'.'wc_5.5', $this->plugin_url.'assets/admin/css/kj-wc-style/app-custom.style.css', array(), KJ_PLUGIN_VERSION);
        wp_enqueue_style('kj'.'wc_1', $this->plugin_url.'assets/admin/css/kj-wc-style/3538.style.css', array(), KJ_PLUGIN_VERSION);
        wp_enqueue_style('kj'.'wc_2', $this->plugin_url.'assets/admin/css/kj-wc-style/5502.style.css', array(), KJ_PLUGIN_VERSION);
        wp_enqueue_style('kj'.'wc_3', $this->plugin_url.'assets/admin/css/kj-wc-style/8597.style.css', array(), KJ_PLUGIN_VERSION);

        /** QR CODE */
        wp_enqueue_script('qrcode', $this->plugin_url.'assets/admin/js/qrcode.min.js', array(), KJ_PLUGIN_VERSION, true);
        /** print */
        wp_enqueue_style('printCss', $this->plugin_url.'assets/admin/css/print.min.css', array(), KJ_PLUGIN_VERSION);
        wp_enqueue_script('printJs', $this->plugin_url.'assets/admin/js/print.min.js', array(), KJ_PLUGIN_VERSION, true);
        
        /** Select 2*/
        wp_enqueue_style( 'select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0'); // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent
        wp_enqueue_script( 'select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0-rc.0', true); // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent

        // Load Vite-built Vue app if available
        if ($this->is_vite_enabled) {
            $vue_entry = $this->getViteAsset('admin/main.ts');
            if ($vue_entry) {
                wp_enqueue_script('kiriminaja-admin-vue', $vue_entry, array(), KJ_PLUGIN_VERSION, true);
                
                // Localize script for Vue app with AJAX data
                wp_localize_script('kiriminaja-admin-vue', 'kiriminaja_admin', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('kiriminaja_ajax_nonce'),
                    'wp_ajax_nonce' => wp_create_nonce('wp_ajax_nonce')
                ));
                
                add_filter('script_loader_tag', function($tag, $handle) {
                    if ('kiriminaja-admin-vue' === $handle) {
                        return str_replace('<script', '<script type="module" crossorigin', $tag);
                    }
                    return $tag;
                }, 10, 2);
                
                $css_files = $this->getViteCss('admin/main.ts');
                foreach ($css_files as $index => $css_url) {
                    wp_enqueue_style('kiriminaja-admin-vue-' . $index, $css_url, array(), KJ_PLUGIN_VERSION);
                }
            }
        }
        
        // Legacy script (fallback or coexist)
        wp_enqueue_script('kiriminPluginScript', $this->plugin_url.'assets/admin/js/kj-admin-script.js',array(),KJ_PLUGIN_VERSION,true);
    }
    
}