<?php

use KiriminAjaOfficial\Contracts\TrackingPageRepositoryInterface;
use KiriminAjaOfficial\Controllers\SettingController;
use KiriminAjaOfficial\Repositories\TrackingPageRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}

require_once PLUGIN_DIR . '/inc/Contracts/TrackingPageRepositoryInterface.php';
require_once PLUGIN_DIR . '/inc/Repositories/TrackingPageRepository.php';
require_once PLUGIN_DIR . '/inc/Controllers/SettingController.php';

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
    public function controller_delegates_tracking_page_lookup_to_repository_contract(): void
    {
        $expected   = array( (object) array( 'ID' => 19, 'post_title' => 'Tracking' ) );
        $repository = new TrackingPageRepositoryFake( $expected );
        $controller = new SettingController( $repository );
        $method     = new ReflectionMethod( $controller, 'getTrackingShortcodePages' );

        $this->assertSame( $expected, $method->invoke( $controller ) );
        $this->assertSame( 1, $repository->calls );
    }
}

final class TrackingPageWpdbFake
{
    public string $posts = 'wp_posts';
    public string $prepared_query = '';
    public array $prepared_values = array();
    private array $results;

    public function __construct( array $results )
    {
        $this->results = $results;
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
}

final class TrackingPageRepositoryFake implements TrackingPageRepositoryInterface
{
    public int $calls = 0;
    private array $results;

    public function __construct( array $results )
    {
        $this->results = $results;
    }

    public function findTrackingShortcodePages(): array
    {
        ++$this->calls;

        return $this->results;
    }
}
