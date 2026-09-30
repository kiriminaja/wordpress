<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Shared strings for courier service management in settings and onboarding. */
class CourierServiceUiData {
	public static function translations(): array {
		return array(
			'deliveryType' => __( 'Delivery type', 'kiriminaja-official' ),
			'expressDelivery' => __( 'Express Delivery', 'kiriminaja-official' ),
			'instantDelivery' => __( 'Instant Delivery', 'kiriminaja-official' ),
			'instantUnavailable' => __( 'Instant delivery is not available yet.', 'kiriminaja-official' ),
			'domesticDelivery' => __( 'Domestic Delivery', 'kiriminaja-official' ),
			'activeOnly' => __( 'Active only', 'kiriminaja-official' ),
			/* translators: %1$s: active courier count, %2$s: total courier count. */
			'activeTotal' => __( '%1$s Active / %2$s Total', 'kiriminaja-official' ),
			'filterAll' => __( 'All', 'kiriminaja-official' ),
			'filterSelected' => __( 'Fully enabled', 'kiriminaja-official' ),
			'filterPartial' => __( 'Partially enabled', 'kiriminaja-official' ),
			'filterUnselected' => __( 'Disabled', 'kiriminaja-official' ),
			'courierPickerTitle' => __( 'Courier services', 'kiriminaja-official' ),
			'courierPickerDescription' => __( 'Choose the couriers and services available at checkout.', 'kiriminaja-official' ),
			/* translators: %s: courier name or selected count. */
			'selectedCouriers' => __( '%s couriers selected', 'kiriminaja-official' ),
			/* translators: %s: courier name or selected count. */
			'selectedServices' => __( '%s services enabled', 'kiriminaja-official' ),
			'searchCouriers' => __( 'Search couriers or services', 'kiriminaja-official' ),
			'filterCouriers' => __( 'Filter couriers by selection', 'kiriminaja-official' ),
			/* translators: %s: courier name or selected count. */
			'enableAllCourier' => __( 'Enable all services for %s', 'kiriminaja-official' ),
			/* translators: %s: courier name or selected count. */
			'clearCourier' => __( 'Clear services for %s', 'kiriminaja-official' ),
			'clear' => __( 'Clear', 'kiriminaja-official' ),
			/* translators: %s: courier name or selected count. */
			'collapseCourier' => __( 'Collapse %s services', 'kiriminaja-official' ),
			/* translators: %s: courier name or selected count. */
			'expandCourier' => __( 'Expand %s services', 'kiriminaja-official' ),
			'noCouriersFound' => __( 'No couriers found', 'kiriminaja-official' ),
			'noCouriersFoundDescription' => __( 'Try another courier or service name, or change the selection filter.', 'kiriminaja-official' ),
			'resetCourierFilters' => __( 'Reset filters', 'kiriminaja-official' ),
			'autoSave' => __( 'Changes are saved automatically.', 'kiriminaja-official' ),
			'saving' => __( 'Saving courier services…', 'kiriminaja-official' ),
			'saved' => __( 'Courier services saved.', 'kiriminaja-official' ),
			'disableAll' => __( 'Disable all', 'kiriminaja-official' ),
			'onboardingSaveHint' => __( 'Your selection is saved when you choose Continue.', 'kiriminaja-official' ),
			'noSelectionHint' => __( 'No courier services are enabled. Customers cannot use KiriminAja shipping until you enable a service.', 'kiriminaja-official' ),
			'unavailableHint' => __( 'Unavailable services are saved choices no longer listed by the courier. You can keep or remove them; they do not guarantee a shipping rate.', 'kiriminaja-official' ),
		);
	}
}
