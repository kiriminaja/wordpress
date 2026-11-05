<?php

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}
$config = json_decode( $argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR );
$root   = dirname( __DIR__, 2 );
require $root . '/inc/Services/CourierServiceCatalog.php';
require $config['repository_source'] ?? $root . '/inc/Repositories/SettingRepository.php';

use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Services\CourierServiceCatalog as Catalog;

function get_transient( $key ) {
	return false;
}

class CourierPolicyPerformanceDb {
	public $prefix = 'policy_';
	public $last_error = '';
	public $reads = 0;
	public $rows = array();

	public function prepare( $sql, $key ) {
		preg_match( '/FROM ([^ ]+)/', $sql, $match );
		return $match[1] . '|' . $key;
	}

	public function get_row( $key ) {
		++$this->reads;
		return $this->rows[ $key ] ?? null;
	}
}

class ChangingCourierPolicyRepository extends SettingRepository {
	public $row;
	public $retrievals = 0;

	public function getSettingByKey( $key ) {
		++$this->retrievals;
		return $this->row;
	}
}

function policy_check( $actual, $expected ) {
	if ( $actual !== $expected ) {
		throw new RuntimeException( var_export( array( 'actual' => $actual, 'expected' => $expected ), true ) );
	}
}

function policy_cpu_ms(): float {
	$usage = getrusage();
	return 1000 * ( $usage['ru_utime.tv_sec'] + $usage['ru_stime.tv_sec'] )
		+ ( $usage['ru_utime.tv_usec'] + $usage['ru_stime.tv_usec'] ) / 1000;
}

$wpdb      = new CourierPolicyPerformanceDb();
$selection = array();
$pairs     = array();
foreach ( Catalog::known() as $courier => $definition ) {
	foreach ( $definition['services'] as $service ) {
		if ( count( $pairs ) >= 30 ) {
			break 2;
		}
		$selection[ $courier ][] = $service['code'];
		$pairs[] = array( $courier, $service['code'] );
	}
}
policy_check( count( $pairs ), 30 );
$raw   = json_encode( (object) $selection, JSON_THROW_ON_ERROR );
$rates = array();
for ( $i = 0; $i < 200; ++$i ) {
	$pair    = $pairs[ $i % count( $pairs ) ];
	$rates[] = array( $pair[0], 0 === $i % 4 ? 'NOT-SELECTED' : $pair[1] );
}
$wpdb->rows['policy_kiriminaja_settings|origin_whitelist_expedition_services'] = (object) array( 'value' => $raw );
$repository = new SettingRepository();
$repository->clearCache();
$decisions = array();
foreach ( $rates as $rate ) {
	$decisions[] = $repository->isCourierServiceEnabled( $rate[0], $rate[1] );
}
policy_check( $wpdb->reads, 1 );
policy_check( count( array_filter( $decisions ) ), 150 );

if ( 'benchmark' === ( $config['mode'] ?? '' ) ) {
	$workloads = array(
		'selection' => static function () use ( $repository ) {
			return $repository->getCourierServiceSelection();
		},
		'rate_policy' => static function ( $rate ) use ( $repository ) {
			return $repository->isCourierServiceEnabled( $rate[0], $rate[1] );
		},
	);
	$results = array();
	foreach ( $workloads as $name => $workload ) {
		$samples = array();
		for ( $sample = -$config['warmups']; $sample < $config['samples']; ++$sample ) {
			$start = policy_cpu_ms();
			for ( $iteration = 0; $iteration < $config['iterations']; ++$iteration ) {
				foreach ( $rates as $rate ) {
					$workload( $rate );
				}
			}
			$elapsed = policy_cpu_ms() - $start;
			if ( $sample >= 0 ) {
				$samples[] = $elapsed;
			}
		}
		$sorted = $samples;
		sort( $sorted );
		$results[ $name ] = array( 'cpu_ms_samples' => $samples, 'median_cpu_ms' => $sorted[ intdiv( count( $sorted ), 2 ) ], 'p95_cpu_ms' => $sorted[ (int) ceil( count( $sorted ) * 0.95 ) - 1 ] );
	}
	echo json_encode( array(
		'php' => PHP_VERSION,
		'source' => realpath( $config['repository_source'] ),
		'source_sha256' => hash_file( 'sha256', $config['repository_source'] ),
		'scope' => 'CPU only; fake wpdb, real catalog; no MySQL or browser timing',
		'samples' => $config['samples'],
		'warmups' => $config['warmups'],
		'iterations' => $config['iterations'],
		'rates_per_iteration' => count( $rates ),
		'selected_service_codes' => count( $pairs ),
		'database_reads' => $wpdb->reads,
		'decision_sha256' => hash( 'sha256', json_encode( $decisions ) ),
		'selection_sha256' => hash( 'sha256', json_encode( $repository->getCourierServiceSelection() ) ),
		'workloads' => $results,
	), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR ) . "\n";
	return;
}

$changing = new ChangingCourierPolicyRepository();
$cases    = array(
	array( null, null ),
	array( (object) array( 'value' => null ), null ),
	array( (object) array( 'value' => '' ), array() ),
	array( (object) array( 'value' => false ), array() ),
	array( (object) array( 'value' => 0 ), array() ),
	array( (object) array( 'value' => array() ), array() ),
	array( (object) array( 'value' => '{}' ), array() ),
	array( (object) array( 'value' => '{' ), array() ),
	array( (object) array( 'value' => '{"jne":["*"]}' ), array( 'jne' => array( '*' ) ) ),
	array( (object) array( 'value' => '{"jne":["YES"],"borzo":["*"],"ninja_inter":["*"]}' ), array( 'jne' => array( 'YES' ) ) ),
	array( null, null ),
);
foreach ( $cases as $case ) {
	$changing->row = $case[0];
	for ( $repeat = 0; $repeat < 3; ++$repeat ) {
		policy_check( $changing->getCourierServiceSelection(), $case[1] );
	}
}
policy_check( $changing->retrievals, 3 * count( $cases ) );
$changing->row = (object) array( 'value' => '{"jne":["*"]}' );
policy_check( $changing->isCourierServiceEnabled( 'jne', 'anything' ), true );
$changing->row->value = '{"jne":["YES"]}';
policy_check( $changing->isCourierServiceEnabled( 'jne', 'REG23' ), false );
policy_check( $changing->isCourierServiceEnabled( 'jne', 'yes' ), true );
$copy = $changing->getCourierServiceSelection();
$copy['jne'][] = 'REG';
policy_check( $changing->getCourierServiceSelection(), array( 'jne' => array( 'YES' ) ) );

$other = new SettingRepository();
policy_check( $other->getCourierServiceSelection(), $selection );
$wpdb->rows['policy_kiriminaja_settings|origin_whitelist_expedition_services']->value = '{"jne":["*"]}';
$other->clearCache();
policy_check( $repository->getCourierServiceSelection(), array( 'jne' => array( '*' ) ) );
$repository->table = 'other_settings';
$wpdb->rows['other_settings|origin_whitelist_expedition_services'] = (object) array( 'value' => '{"idx":["00"]}' );
policy_check( $repository->getCourierServiceSelection(), array( 'idx' => array( '00' ) ) );
$repository->table = 'policy_kiriminaja_settings';
policy_check( $repository->getCourierServiceSelection(), array( 'jne' => array( '*' ) ) );

echo json_encode( array( 'cases' => count( $cases ), 'retrievals' => 3 * count( $cases ), 'enabled_rates' => count( array_filter( $decisions ) ), 'passed' => true ), JSON_THROW_ON_ERROR ) . "\n";
