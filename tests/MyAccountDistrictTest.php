<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MyAccountDistrictTest extends TestCase
{
    #[Test]
    public function account_address_controller_registers_account_only_district_lifecycle(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/AccountAddressController.php');

        $this->assertStringContainsString("woocommerce_address_to_edit", $content);
        $this->assertStringContainsString("woocommerce_after_save_address_validation", $content);
        $this->assertStringContainsString("woocommerce_customer_save_address", $content);
        $this->assertStringContainsString("is_wc_endpoint_url( 'edit-address' )", $content);
        $this->assertStringContainsString("\$address_type . '_kiriof_destination_area'", $content);
        $this->assertStringContainsString("\$address_type . '_address_2'", $content);
        $this->assertStringNotContainsString("\$fields[ \$legacy_key ]['type'] = 'hidden'", $content);
        $this->assertStringNotContainsString('isIndonesiaAddress', $content);
        $this->assertStringContainsString('syncCheckoutSession', $content);
        $this->assertStringContainsString('clearPollutedAddress2Post', $content);
        $this->assertStringContainsString('hideBlockMirrorDistrictFields', $content);
        $this->assertStringContainsString("\$district_id > 0 && '' === \$district_name", $content);
        $this->assertStringContainsString("'shipping_destination_id' : 'destination_id'", $content);
        $this->assertStringContainsString("'kiriof_destination_postcode_map'", $content);
    }

    #[Test]
    public function account_address_controller_removes_blocks_duplicate_district_fields(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/AccountAddressController.php');

        $this->assertStringContainsString('removeBlocksDistrictFields', $content);
        $this->assertStringContainsString("strpos( (string) \$key, 'kiriof_destination_area' )", $content);
        $this->assertStringContainsString("\$key !== \$canonical_key", $content);
    }

    #[Test]
    public function account_district_service_reads_and_writes_canonical_and_legacy_metadata(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CustomerDistrictService.php');

        $this->assertStringContainsString("\$address_type . '_' . self::FIELD_ID", $content);
        $this->assertStringContainsString("\$address_type . '_kiriminaja-official/' . self::FIELD_ID", $content);
        $this->assertStringContainsString("'_wc_' . \$address_type . '/kiriminaja-official/' . self::FIELD_ID", $content);
        $this->assertStringContainsString("update_user_meta", $content);
        $this->assertStringContainsString("update_meta_data", $content);
    }

    #[Test]
    public function account_district_script_supports_search_selection_and_postcode_invalidation(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/assets/wp/js/account-address.js');

        $this->assertStringContainsString('kiriminaja_subdistrict_search', $content);
        $this->assertStringContainsString('kiriofAjax.nonce', $content);
        $this->assertStringContainsString('select2:select.kiriofAccountDistrict', $content);
        $this->assertStringContainsString('select2:clear.kiriofAccountDistrict', $content);
        $this->assertStringContainsString('#billing_postcode, #shipping_postcode', $content);
        $this->assertStringContainsString('clearDistrict', $content);
        $this->assertStringContainsString('extractPostcode', $content);
        $this->assertStringContainsString('setPostcodeFromDistrict', $content);
        $this->assertStringContainsString('kiriofSettingPostcodeFromDistrict', $content);
        $this->assertStringContainsString('postcode: extractPostcode(row)', $content);
        $this->assertStringContainsString('hideBlockMirrorDistrictFields', $content);
        $this->assertStringNotContainsString('legacyDistrictField', $content);
        $this->assertStringNotContainsString('restoreLegacyDistrict', $content);
        $this->assertStringNotContainsString('#billing_address_2_field, #shipping_address_2_field', $content);
    }

    #[Test]
    public function classic_checkout_restores_saved_district_after_select2_refresh(): void
    {
        $config = file_get_contents(PLUGIN_DIR . '/templates/front/partials/form-billing-address-config.php');
        $script = file_get_contents(PLUGIN_DIR . '/assets/wp/js/form-billing-address.js');

        $this->assertStringContainsString("'billingDistrict'", $config);
        $this->assertStringContainsString("'shippingDistrict'", $config);
        $this->assertStringContainsString('kiriofRestoreClassicDistrictSelections', $script);
        $this->assertStringContainsString('kiriofRestoreClassicDistrictSelection', $script);
        $this->assertStringContainsString("setTimeout(kiriofRestoreClassicDistrictSelections, 1500)", $script);
        $this->assertStringContainsString("trigger('change.select2')", $script);
    }

    #[Test]
    public function checkout_footer_district_renderer_is_not_global(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $start = strpos($content, 'function add_custom_select_options_field_and_script');
        $end = strpos($content, 'private function kiriof_render_virtual_cart_district_cleanup', $start);
        $method = substr($content, $start, $end - $start);

        $this->assertStringContainsString('if ( ! is_cart() && ! is_checkout() )', $method);
    }

    #[Test]
    public function checkout_uses_customer_district_as_fallback_and_persists_canonical_meta(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');

        $this->assertStringContainsString("CustomerDistrictService()", $content);
        $this->assertStringContainsString("woocommerce_checkout_get_value", $content);
        $this->assertStringContainsString("kiriof_checkout_district_value", $content);
        $this->assertStringContainsString("\$this->field_destination_key === \$input", $content);
        $this->assertStringContainsString("\$this->field_shipping_destination_key === \$input", $content);
        $this->assertStringContainsString("'value'     => \$destination_id", $content);
        $this->assertStringContainsString("'value'     => ! empty( \$shipping_dest_id ) ? \$shipping_dest_id : \$destination_id", $content);
        $this->assertStringContainsString("\$customer = get_current_user_id()", $content);
        $this->assertStringContainsString("! empty( \$destination_id ) && ! empty( \$destination_name )", $content);
        $this->assertStringContainsString("! empty( \$shipping_dest_id ) && ! empty( \$shipping_dest_name )", $content);
        $this->assertStringContainsString("\$district_service->get( \$customer, 'billing' )", $content);
        $this->assertStringContainsString("\$district_service->get( \$customer, 'shipping' )", $content);
        $this->assertStringContainsString("\$district_service->save(", $content);
    }

    #[Test]
    public function shipping_method_prefers_canonical_shipping_district_metadata(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/wc/KiriminajaShippingMethod.php');

        $this->assertStringContainsString("'shipping_kiriof_destination_area'", $content);
        $this->assertStringContainsString("'shipping_kiriminaja-official/kiriof_destination_area'", $content);
    }
}
