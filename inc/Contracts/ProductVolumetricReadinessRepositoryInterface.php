<?php

namespace KiriminAjaOfficial\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface ProductVolumetricReadinessRepositoryInterface {
	/**
	 * Count shippable products and variations with complete volumetric data.
	 *
	 * @return array{total: int, configured: int, ready: bool}
	 */
	public function getReadiness(): array;
}
