<?php

use KiriminAjaOfficial\Contracts\TrackingPageRepositoryInterface;
use KiriminAjaOfficial\Controllers\SettingController;
use KiriminAjaOfficial\Pages\AdminPost;
use KiriminAjaOfficial\Repositories\TrackingPageRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $option, $value ) {
        $GLOBALS['tracking_page_test_options'][ $option ] = $value;

        return true;
    }
}

require_once PLUGIN_DIR . '/inc/Contracts/TrackingPageRepositoryInterface.php';
require_once PLUGIN_DIR . '/inc/Repositories/TrackingPageRepository.php';
require_once PLUGIN_DIR . '/inc/Controllers/SettingController.php';
require_once PLUGIN_DIR . '/inc/Pages/AdminPost.php';

final class TrackingPageRepositoryRuntimeTest extends TestCase
{
    private $previous_wpdb;

    protected function setUp(): void
    {
        global $wpdb;
        $this->previous_wpdb = $wpdb ?? null;
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = $this->previous_wpdb;
    }

    #[Test]
    public function repository_finds_pages_with_current_or_legacy_tracking_shortcode(): void
    {
        global $wpdb;
        $expected = array(
            (object) array( 'ID' => 12, 'post_title' => 'Track Order' ),
        );
        $wpdb = new TrackingPageWpdbFake( $expected );

        $result = ( new TrackingPageRepository() )->findTrackingShortcodePages();

        $this->assertSame( $expected, $result );
        $this->assertStringContainsString( "post_type = 'page'", $wpdb->prepared_query );
        $this->assertStringContainsString( "post_status NOT IN ('trash', 'auto-draft')", $wpdb->prepared_query );
        $this->assertStringContainsString( 'ORDER BY post_title ASC, ID ASC', $wpdb->prepared_query );
        $this->assertSame(
            array(
                '%[kiriminaja-tracking-front-page%',
                '%[wp-tracking-front-page%',
            ),
            $wpdb->prepared_values
        );
    }

    #[Test]
    public function activation_page_delegates_tracking_lookup_to_repository_contract(): void
    {
        $expected   = (object) array( 'ID' => 31 );
        $repository = new TrackingPageRepositoryFake( array(), $expected );
        $admin_post = new AdminPost( $repository );
        $method     = new ReflectionMethod( $admin_post, 'getTrackingPageByShortcode' );

        $this->assertSame( $expected, $method->invoke( $admin_post ) );
        $this->assertSame( 1, $repository->preferred_calls );
    }

    #[Test]
    public function activation_disables_legacy_hpos_option_through_wordpress_options_api(): void
    {
        $admin_post = new AdminPost( new TrackingPageRepositoryFake() );
        $method     = new ReflectionMethod( $admin_post, 'setLegacyWoocommerceKiriminaja' );
        $GLOBALS['tracking_page_test_options'] = array();

        $method->invoke( $admin_post );

        $this->assertSame(
            'no',
            $GLOBALS['tracking_page_test_options']['woocommerce_custom_orders_table_enabled']
        );
    }

    #[Test]
    public function controller_delegates_tracking_page_lookup_to_repository_contract(): void
    {
        $expected   = array( (object) array( 'ID' => 19, 'post_title' => 'Tracking' ) );
        $repository = new TrackingPageRepositoryFake( $expected );
        $controller = new SettingController( $repository );
        $method     = new ReflectionMethod( $controller, 'getTrackingShortcodePages' );

        $this->assertSame( $expected, $method->invoke( $controller ) );
        $this->assertSame( 1, $repository->calls );
    }

    #[Test]
    public function repository_prefers_a_published_tracking_page_for_activation(): void
    {
        global $wpdb;
        $expected = (object) array( 'ID' => 23 );
        $wpdb     = new TrackingPageWpdbFake( array(), $expected );

        $result = ( new TrackingPageRepository() )->findPreferredTrackingShortcodePage();

        $this->assertSame( $expected, $result );
        $this->assertStringContainsString( "ORDER BY post_status = 'publish' DESC, ID ASC", $wpdb->prepared_query );
        $this->assertStringContainsString( 'LIMIT 1', $wpdb->prepared_query );
        $this->assertSame(
            array(
                '%[kiriminaja-tracking-front-page%',
                '%[wp-tracking-front-page%',
            ),
            $wpdb->prepared_values
        );
    }
}

final class TrackingPageWpdbFake
{
    public string $posts = 'wp_posts';
    public string $prepared_query = '';
    public array $prepared_values = array();
    private array $results;
    private $row;

    public function __construct( array $results, $row = null )
    {
        $this->results = $results;
        $this->row     = $row;
    }

    public function esc_like( $value )
    {
        return $value;
    }

    public function prepare( $query, ...$values )
    {
        $this->prepared_query  = $query;
        $this->prepared_values = $values;

        return $query;
    }

    public function get_results( $query )
    {
        return $this->results;
    }

    public function get_row( $query )
    {
        return $this->row;
    }
}

final class TrackingPageRepositoryFake implements TrackingPageRepositoryInterface
{
    public int $calls = 0;
    public int $preferred_calls = 0;
    private array $results;
    private $preferred_result;

    public function __construct( array $results = array(), $preferred_result = null )
    {
        $this->results         = $results;
        $this->preferred_result = $preferred_result;
    }

    public function findTrackingShortcodePages(): array
    {
        ++$this->calls;

        return $this->results;
    }

    public function findPreferredTrackingShortcodePage()
    {
        ++$this->preferred_calls;

        return $this->preferred_result ?? $this->results[0] ?? null;
    }
}
