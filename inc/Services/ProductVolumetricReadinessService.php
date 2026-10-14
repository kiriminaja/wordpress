<?php

namespace KiriminAjaOfficial\Services;

use KiriminAjaOfficial\Contracts\ProductVolumetricReadinessRepositoryInterface;
use KiriminAjaOfficial\Repositories\ProductVolumetricReadinessRepository;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supplies product volumetric readiness to renderers without exposing persistence.
 */
class ProductVolumetricReadinessService {
	private ProductVolumetricReadinessRepositoryInterface $repository;

	public function __construct( ?ProductVolumetricReadinessRepositoryInterface $repository = null ) {
		$this->repository = $repository ?? new ProductVolumetricReadinessRepository();
	}

	/**
	 * @return array{total: int, configured: int, ready: bool}
	 */
	public function getReadiness(): array {
		return $this->repository->getReadiness();
	}
}
