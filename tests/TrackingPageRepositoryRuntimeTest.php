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
                'wp_posts',
                '%[kiriminaja-tracking-front-page%',
                '%[wp-tracking-front-page%',
            ),
            $wpdb->prepared_values
        );
    }

    #[Test]
    public function repository_reports_published_current_tracking_page_readiness(): void
    {
        global $wpdb;
        $wpdb = new TrackingPageWpdbFake( array(), null, array(), 1 );

        $this->assertTrue( ( new TrackingPageRepository() )->hasPublishedTrackingPage() );
        $this->assertStringContainsString( "post_type = 'page'", $wpdb->prepared_query );
        $this->assertStringContainsString( "post_status = 'publish'", $wpdb->prepared_query );
        $this->assertSame( array( 'wp_posts', '%[kiriminaja-tracking-front-page%' ), $wpdb->prepared_values );
    }

    #[Test]
    public function repository_returns_published_tracking_pages_then_posts_with_legacy_list_shape(): void
    {
        global $wpdb;
        $pages = array(
            (object) array(
                'ID'          => 12,
                'post_title'  => 'Track Order',
                'post_name'   => 'track-order',
                'post_status' => 'publish',
                'guid'        => 'https://example.test/?page_id=12',
            ),
        );
        $posts = array(
            (object) array(
                'ID'          => 18,
                'post_title'  => 'Shipment Lookup',
                'post_name'   => 'shipment-lookup',
                'post_status' => 'publish',
                'guid'        => 'https://example.test/?p=18',
            ),
        );
        $wpdb = new TrackingPageWpdbFake( array(), null, array( $pages, $posts ) );

        $result = ( new TrackingPageRepository() )->findPublishedTrackingContent();

        $this->assertSame( array_merge( $pages, $posts ), $result );
        $this->assertCount( 2, $wpdb->prepared_queries );
        $this->assertStringContainsString( 'SELECT ID, post_title, post_name, post_status, guid', $wpdb->prepared_queries[0] );
        $this->assertStringContainsString( "post_status = 'publish'", $wpdb->prepared_queries[0] );
        $this->assertStringContainsString( 'ORDER BY post_title ASC', $wpdb->prepared_queries[0] );
        $this->assertSame(
            array( 'wp_posts', 'page', '%[kiriminaja-tracking-front-page%', '%[wp-tracking-front-page%' ),
            $wpdb->prepared_value_sets[0]
        );
        $this->assertSame(
            array( 'wp_posts', 'post', '%[kiriminaja-tracking-front-page%', '%[wp-tracking-front-page%' ),
            $wpdb->prepared_value_sets[1]
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
        $setting_repository = $this->getMockBuilder( \KiriminAjaOfficial\Repositories\SettingRepository::class )
            ->disableOriginalConstructor()
            ->getMock();
        $controller = new SettingController( $repository, $setting_repository );
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
                'wp_posts',
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
    public array $prepared_queries = array();
    public array $prepared_value_sets = array();
    private array $results;
    private $row;
    private array $result_queue;
    private $var_result;

    public function __construct( array $results, $row = null, array $result_queue = array(), $var_result = 0 )
    {
        $this->results      = $results;
        $this->row          = $row;
        $this->result_queue = $result_queue;
        $this->var_result   = $var_result;
    }

    public function esc_like( $value )
    {
        return $value;
    }

    public function prepare( $query, ...$values )
    {
        $this->prepared_query  = $query;
        $this->prepared_values = $values;
        $this->prepared_queries[]   = $query;
        $this->prepared_value_sets[] = $values;

        return $query;
    }

    public function get_results( $query )
    {
        if ( ! empty( $this->result_queue ) ) {
            return array_shift( $this->result_queue );
        }

        return $this->results;
    }

    public function get_row( $query )
    {
        return $this->row;
    }

    public function get_var( $query )
    {
        return $this->var_result;
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

    public function hasPublishedTrackingPage(): bool
    {
        return ! empty( $this->results );
    }

    public function findTrackingShortcodePages(): array
    {
        ++$this->calls;

        return $this->results;
    }

    public function findPublishedTrackingContent(): array
    {
        return $this->results;
    }

    public function findPreferredTrackingShortcodePage()
    {
        ++$this->preferred_calls;

        return $this->preferred_result ?? $this->results[0] ?? null;
    }
}
