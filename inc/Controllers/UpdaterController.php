<?php 
namespace KiriminAjaOfficial\Controllers;

// phpcs:disable plugin_updater_detected
class UpdaterController
{
    private $plugin_slug;
    private $plugin_slug_file;
    private $cache_key;
    private $cache_allowed;
    private $url;
    

    public function register(){
        
        $this->cache_key = 'kaj_upd';
        $this->cache_allowed = false;
        $this->plugin_slug = KIRIOF_SLUG;
        $this->plugin_slug_file = KIRIOF_SLUG_FILE;
        
        $this->url = "https://kaj-prd-core-web-assets.kiriminaja.com/wp/woocommerce.json";
        
        //Updater Plugin GCS
        add_filter('plugins_api', array($this, 'kiriminaja_plugin_info'), 20, 3);
        add_filter('site_transient_update_plugins', array($this, 'push_update'));
        add_action('upgrader_process_complete', array($this, 'purge'), 10, 2);

        // Forced update behavior (driven by remote woocommerce.json)
        add_action('admin_notices', array($this, 'force_update_notice'));
        add_filter('auto_update_plugin', array($this, 'maybe_force_auto_update'), 10, 2);
    }

    public function request(){

        $remote = get_transient($this->cache_key);

        if (false === $remote || !$this->cache_allowed) {

            $remote = wp_remote_get($this->url,
                array(
                    'timeout' => 10,
                    'headers' => array(
                        'Accept' => 'application/json'
                    )
                )
            );

            if (
                is_wp_error($remote)
                || 200 !== wp_remote_retrieve_response_code($remote)
                || empty(wp_remote_retrieve_body($remote))
            ) {
                return false;
            }

            set_transient($this->cache_key, $remote, DAY_IN_SECONDS);

        }

        $remote = json_decode(wp_remote_retrieve_body($remote));

        return $remote;

    }

    public function kiriminaja_plugin_info($res, $action, $args){
       
        // do nothing if you're not getting plugin information right now
        if ('plugin_information' !== $action) {
            return $res;
        }

        // do nothing if it is not our plugin
        if ($this->plugin_slug !== $args->slug) {
            return $res;
        }

        // get updates
        $remote = $this->request();
        

        if (!$remote) {
            return $res;
        }

        $res = new \stdClass();

        $res->name = $remote->name;
        $res->slug = $remote->slug;
        $res->version = $remote->version;
        $res->tested = $remote->tested;
        $res->requires = $remote->requires;
        $res->author = $remote->author;
        $res->author_profile = $remote->author_profile;
        $res->download_link = $remote->download_url;
        $res->trunk = $remote->download_url;
        $res->requires_php = $remote->requires_php;
        $res->last_updated = $remote->last_updated;

        $res->sections = array(
            'description' => $remote->sections->description,
            'installation' => $remote->sections->installation,
            'changelog' => $remote->sections->changelog
        );

        if (!empty($remote->banners)) {
            $res->banners = array(
                'low' => $remote->banners->low,
                'high' => $remote->banners->high
            );
        }

        return $res;
    }

    public function push_update($transient)
    {

        if (empty($transient->checked)) {
            return $transient;
        }

        $remote = $this->request();

        if (
            $remote
            && version_compare(KIRIOF_VERSION,$remote->version, '<')
            && version_compare($remote->requires, get_bloginfo('version'), '<=')
            && version_compare($remote->requires_php, PHP_VERSION, '<')
        ) {
            
            $res = new \stdClass();

            $res->slug = $this->plugin_slug;
            $res->plugin = $this->plugin_slug_file;
            $res->new_version = $remote->version;
            $res->tested = $remote->tested;
            $res->package = $remote->download_url;
            $res->requires_php = $remote->requires_php;

            $transient->response[$res->plugin] = $res;

        }

        return $transient;
    }

    public function purge($upgrader, $options)
    {
        if (
            $this->cache_allowed
            && 'update' === $options['action']
            && 'plugin' === $options['type']
        ) {
            // just clean the cache when new plugin version is installed
            delete_transient($this->cache_key);
        }
    }

    /**
     * Show a red admin notice when the remote release is flagged as a forced update
     * and the installed version is older than the remote one.
     *
     * Controlled via two optional fields in woocommerce.json:
     *   "force_update":  true|false
     *   "force_message": "Custom HTML/text shown in the notice (optional)"
     */
    public function force_update_notice()
    {
        if ( ! current_user_can( 'update_plugins' ) ) {
            return;
        }

        $remote = $this->request();

        if ( ! $this->is_force_update( $remote ) ) {
            return;
        }

        $update_url = wp_nonce_url(
            self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $this->plugin_slug_file ) ),
            'upgrade-plugin_' . $this->plugin_slug_file
        );

        $default_message = sprintf(
            /* translators: 1: plugin name, 2: new version */
            esc_html__( 'A critical update for %1$s is available (v%2$s). Please update immediately to keep your store running correctly.', 'kiriminaja-official' ),
            esc_html( $remote->name ?? 'KiriminAja Official' ),
            esc_html( $remote->version )
        );

        $message = ! empty( $remote->force_message )
            ? wp_kses_post( $remote->force_message )
            : $default_message;

        printf(
            '<div class="notice notice-error" style="border-left-color:#d63638;"><p><strong>%s</strong></p><p>%s</p><p><a href="%s" class="button button-primary">%s</a></p></div>',
            esc_html__( 'KiriminAja Official — Required Update', 'kiriminaja-official' ),
            $message, // already escaped above
            esc_url( $update_url ),
            esc_html__( 'Update now', 'kiriminaja-official' )
        );
    }

    /**
     * Force WordPress core auto-updates to install this plugin's update
     * when the remote release is flagged as forced.
     */
    public function maybe_force_auto_update( $update, $item )
    {
        $slug = is_object( $item ) ? ( $item->slug ?? '' ) : '';

        if ( $slug !== $this->plugin_slug ) {
            return $update;
        }

        $remote = $this->request();

        if ( $this->is_force_update( $remote ) ) {
            return true;
        }

        return $update;
    }

    /**
     * Determine if the remote payload marks the current release as a forced update
     * and the installed version is older.
     */
    private function is_force_update( $remote )
    {
        return (
            $remote
            && ! empty( $remote->force_update )
            && ! empty( $remote->version )
            && version_compare( KIRIOF_VERSION, $remote->version, '<' )
        );
    }


}
// phpcs:enable plugin_updater_detected
?>