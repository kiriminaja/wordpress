<?php

use PHPUnit\Framework\TestCase;

/** Run against a transactional fake without loading WordPress. */
final class CourierServicePersistenceRuntimeTest extends TestCase {
    public function test_atomic_policy_persistence(): void {
        $script = <<<'PHP'
<?php
 define( 'ABSPATH', __DIR__ );
 function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
 function get_transient( $key ) { return false; }
 require %CATALOG%;
 require %REPOSITORY%;
 use KiriminAjaOfficial\Repositories\SettingRepository as Repository;
 function check( $actual, $expected ) {
     if ( $actual !== $expected ) { throw new RuntimeException( var_export( array( $actual, $expected ), true ) ); }
 }
 class PersistenceDb {
     public $prefix;
     public $last_error = '';
     public $rows;
     public $queries = array();
     public $writes = 0;
     public $failWrite = 0;
     public $failQuery = '';
     public $zeroWrites = 0;
     public $onWrite;
     private $snapshot;
     public function __construct( $rows ) {
         static $id = 0;
         $this->prefix = 'persistence_' . ++$id . '_';
         $this->rows = $rows;
     }
     public function prepare( $sql, $key ) { return $key; }
     public function get_row( $key ) {
         $this->last_error = '';
         return array_key_exists( $key, $this->rows ) ? (object) array( 'value' => $this->rows[$key] ) : null;
     }
     public function query( $sql ) {
         $this->last_error = '';
         $this->queries[] = $sql;
         if ( $this->failQuery === $sql ) { return false; }
         if ( 'START TRANSACTION' === $sql ) { $this->snapshot = $this->rows; $this->writes = 0; }
         elseif ( 'ROLLBACK' === $sql ) { $this->rows = $this->snapshot; $this->snapshot = null; }
         elseif ( 'COMMIT' === $sql ) { $this->snapshot = null; }
         else { throw new RuntimeException( 'Unexpected query: ' . $sql ); }
         return 0;
     }
     private function write( $key, $value ) {
         $this->last_error = '';
         if ( ++$this->writes === $this->failWrite ) { $this->last_error = 'injected write failure'; return false; }
         $same = array_key_exists( $key, $this->rows ) && $this->rows[$key] === $value;
         $this->rows[$key] = $value;
         if ( $this->onWrite ) { ($this->onWrite)(); }
         if ( $same ) { ++$this->zeroWrites; return 0; }
         return 1;
     }
     public function insert( $table, $data, ...$formats ) { return $this->write( $data['key'], $data['value'] ); }
     public function update( $table, $data, $where, ...$formats ) { return $this->write( $where['key'], $data['value'] ); }
 }
 function expect_failure( $repository, $payload, $message ) {
     try { $repository->storeCourierWhitelist( $payload ); }
     catch ( RuntimeException $error ) { check( $error->getMessage(), $message ); return $error; }
     throw new RuntimeException( 'Expected failed transaction' );
 }
 $legacy = array( 'origin_whitelist_expedition_id' => 'jne', 'origin_whitelist_expedition_name' => 'JNE', 'unrelated' => 'untouched' );
 $deny = array( 'service_selection' => '{}', 'origin_whitelist_expedition_id' => '', 'origin_whitelist_expedition_name' => '' );
 // First migration to deny-all must never empty legacy IDs without saving the policy.
 foreach ( array( 2, 3 ) as $failure ) {
     $wpdb = new PersistenceDb( $legacy );
     $repository = new Repository();
     check( $repository->getWhitelistExpeditionIds(), array( 'jne' ) );
     check( $repository->getCourierServiceSelection(), null );
     check( $repository->isCourierServiceEnabled( 'jnt', 'EZ' ), false );
     $wpdb->failWrite = $failure;
     expect_failure( $repository, $deny, 'Unable to save courier settings.' );
     check( $wpdb->queries, array( 'START TRANSACTION', 'ROLLBACK' ) );
     check( $wpdb->rows, $legacy );
     check( $repository->getWhitelistExpeditionIds(), array( 'jne' ) );
     check( $repository->getCourierServiceSelection(), null );
     check( $repository->isCourierServiceEnabled( 'jne', 'REG' ), true );
     check( $repository->isCourierServiceEnabled( 'jnt', 'EZ' ), false );
 }
 $existing = $legacy + array( 'origin_whitelist_expedition_services' => '{"jne":["REG"]}' );
 $replacement = array( 'service_selection' => '{"jnt":["EZ"]}', 'origin_whitelist_expedition_id' => 'jnt', 'origin_whitelist_expedition_name' => 'J&T' );
 foreach ( array( 2, 3 ) as $failure ) {
     $wpdb = new PersistenceDb( $existing );
     $repository = new Repository();
     check( $repository->getWhitelistExpeditionIds(), array( 'jne' ) );
     check( $repository->getCourierServiceSelection(), array( 'jne' => array( 'REG' ) ) );
     $wpdb->failWrite = $failure;
     expect_failure( $repository, $replacement, 'Unable to save courier settings.' );
     check( $wpdb->rows, $existing );
     check( $repository->getWhitelistExpeditionIds(), array( 'jne' ) );
     check( $repository->isCourierServiceEnabled( 'jne', 'REG23' ), true );
     check( $repository->isCourierServiceEnabled( 'jnt', 'EZ' ), false );
 }
 // A successful commit refreshes warm caches; unchanged updates (0) remain success.
 $wpdb = new PersistenceDb( $existing );
 $repository = new Repository();
 $repository->getWhitelistExpeditionIds();
 $repository->getCourierServiceSelection();
 check( $repository->storeCourierWhitelist( $replacement ), true );
 check( $wpdb->queries, array( 'START TRANSACTION', 'COMMIT' ) );
 check( $repository->getWhitelistExpeditionIds(), array( 'jnt' ) );
 check( $repository->isCourierServiceEnabled( 'jne', 'REG' ), false );
 check( $repository->isCourierServiceEnabled( 'jnt', 'EZ' ), true );
 check( $repository->storeCourierWhitelist( $replacement ), true );
 check( $wpdb->zeroWrites, 3 );
 check( $repository->storeCourierWhitelist( $deny ), true );
 check( $repository->getCourierServiceSelection(), array() );
 check( $repository->isCourierServiceEnabled( 'jne', 'REG' ), false );
 check( $wpdb->rows['unrelated'], 'untouched' );
 // Transaction control failures cannot report success.
 $wpdb = new PersistenceDb( $legacy );
 $repository = new Repository();
 $wpdb->failQuery = 'START TRANSACTION';
 expect_failure( $repository, $deny, 'Unable to start courier settings transaction.' );
 check( $wpdb->writes, 0 );
 check( $wpdb->rows, $legacy );
 $wpdb = new PersistenceDb( $existing );
 $repository = new Repository();
 $wpdb->failQuery = 'COMMIT';
 expect_failure( $repository, $replacement, 'Unable to commit courier settings transaction.' );
 check( $wpdb->queries, array( 'START TRANSACTION', 'COMMIT', 'ROLLBACK' ) );
 check( $wpdb->rows, $existing );
 check( $repository->getWhitelistExpeditionIds(), array( 'jne' ) );
 $wpdb = new PersistenceDb( $legacy );
 $repository = new Repository();
 $wpdb->failWrite = 2;
 $wpdb->failQuery = 'ROLLBACK';
 $error = expect_failure( $repository, $deny, 'Unable to roll back courier settings transaction.' );
 check( $error->getPrevious()->getMessage(), 'Unable to save courier settings.' );
 // International is denied for allow-all, legacy-only, and explicit wildcard policies.
 $wpdb = new PersistenceDb( array() );
 $repository = new Repository();
 check( $repository->isCourierServiceEnabled( ' NINJA_INTER ', 'anything' ), false );
 check( $repository->isCourierServiceEnabled( 'jne', 'REG' ), true );
 $wpdb = new PersistenceDb( array( 'origin_whitelist_expedition_id' => 'NINJA_INTER' ) );
 $repository = new Repository();
 check( $repository->getWhitelistExpeditionIds(), array() );
 check( $repository->hasEnabledCourierServices(), false );
 check( $repository->isCourierServiceEnabled( 'jne', 'REG' ), false );
 check( $repository->isCourierServiceEnabled( 'ninja_inter', 'anything' ), false );
 $wpdb = new PersistenceDb( array( 'origin_whitelist_expedition_services' => '{"ninja_inter":["*"]}' ) );
 $repository = new Repository();
 check( $repository->getCourierServiceSelection(), array() );
 check( $repository->hasEnabledCourierServices(), false );
 check( $repository->isCourierServiceEnabled( 'ninja_inter', 'anything' ), false );
 echo 'ok';
PHP;
        $script = str_replace( array( '%CATALOG%', '%REPOSITORY%' ), array( var_export( PLUGIN_DIR . '/inc/Services/CourierServiceCatalog.php', true ), var_export( PLUGIN_DIR . '/inc/Repositories/SettingRepository.php', true ) ), $script );
        $file = tempnam( sys_get_temp_dir(), 'courier-persistence-' );
        file_put_contents( $file, $script );
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $file ) . ' 2>&1', $output, $status );
        unlink( $file );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        $this->assertSame( 'ok', implode( "\n", $output ) );
    }
}
