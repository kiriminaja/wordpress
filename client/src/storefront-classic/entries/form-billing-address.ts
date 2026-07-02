import { kiriofHasWpBlockCheckoutContext } from "../lib/block-context";
import {
  bindClassicDistrictChange,
  bindClassicDistrictSearch,
} from "../lib/classic-district";
import { kiriofExtractJsonResponseText } from "../lib/json";
import { kiriofNormalizePaymentMethod } from "../lib/payment";
import type {
  KiriofRuntimeValue,
  KiriofStoreApiAddress,
  KiriofStoreApiPayload,
} from "../lib/store-api";

const kiriofBillingAddressConfig = window.kiriofBillingAddressConfig || {};
kiriofBillingAddressConfig.i18n = kiriofBillingAddressConfig.i18n || {};
let kiriofUpdatingCheckoutLock = false;
let kiriofTriggeredInitialShippingUpdate = false;
let kiriofFeeRefreshRequest = null;
let kiriofInFlightFeeRefreshKey = "";
let kiriofPendingFeeRefresh = false;
let kiriofPendingFeeRefreshKey = "";
let kiriofLastCompletedFeeRefreshKey = "";
let kiriofLastCompletedFeeRefreshAt = 0;
let kiriofCodInsuranceTimer = null;
let kiriofBlockRatesRefreshTimer = null;
let kiriofBlockCartRefreshTimer = null;
let kiriofLastBlockCartRefreshKey = "";
let kiriofLastBlockCartRefreshAt = 0;
let kiriofLastBlockCartUpdateKey = "";
let kiriofPendingPaymentMethod = "";
let kiriofPendingPaymentMethodAt = 0;
let kiriofLastRawStoreCustomerUpdateKey = "";
let kiriofLastRawStoreCustomerUpdateAt = 0;
let kiriofLastObservedBlockPostcode = "";
const kiriofSavedDistrictByPostcode =
  kiriofBillingAddressConfig.savedDistrictByPostcode || {};
let kiriofSavedCheckoutPostcode =
  kiriofBillingAddressConfig.savedCheckoutPostcode || "";
const kiriofStoreApiNonce = kiriofBillingAddressConfig.storeApiNonce || "";
const kiriofStoreApiUpdateCustomerUrl =
  kiriofBillingAddressConfig.storeApiUpdateCustomerUrl || "";
let kiriofPendingShippingMethod = "";
let kiriofPendingShippingMethodAt = 0;

jQuery(document).ready(function () {
  if (kiriofBillingAddressConfig.globalInsurance) {
    // Global insurance forced — check and disable the checkbox
    const $ins = jQuery("#kiriof_insurance, #kiriof_shipping_insurance");
    $ins.prop("checked", true).prop("disabled", true);
    $ins.closest(".form-row").css("opacity", "0.6");
  }

  bindClassicDistrictSearch(kiriofBillingAddressConfig);
  bindClassicDistrictChange({
    config: kiriofBillingAddressConfig,
    getClassicInsuranceValue: kiriofGetClassicInsuranceValue,
    refreshCodInsurance: kiriofCodInsurance,
  });
  kiriofScheduleClassicShippingMethodSelectInit();
  kiriofInitBlockCheckoutCompatibility();

  if (kiriofBillingAddressConfig.isCart) {
    setTimeout(() => {
      jQuery(".shipping-calculator-form").show();
    }, 300);

    jQuery(document.body).on("updated_cart_totals", function () {
      bindClassicDistrictSearch(kiriofBillingAddressConfig);
      bindClassicDistrictChange({
        config: kiriofBillingAddressConfig,
        getClassicInsuranceValue: kiriofGetClassicInsuranceValue,
        refreshCodInsurance: kiriofCodInsurance,
      });
    });

    // Save chosen shipping method to local storage
    jQuery(document).on(
      "change",
      'input[name="shipping_method[0]"]',
      function () {
        localStorage.setItem("chosen_shipping_method", jQuery(this).val());
      },
    );
  }

  if (kiriofBillingAddressConfig.isCheckout) {
    kiriofChangeCodPayment();
    kiriofChangeDifferentAddress();

    jQuery(document.body).on("change", "input.shipping_method", function () {
      kiriofRememberSelectedShippingMethod(jQuery(this).val());
      kiriofHandleCodInsurance();
    });

    // Re-bind handlers after AJAX fragment refresh (theme compatibility).
    // Do not call kiriofCodInsurance() here: its success callback triggers
    // update_checkout once so WooCommerce can render native fee rows. Calling
    // it again from updated_checkout creates an endless loading loop.
    jQuery(document.body).on("updated_checkout", function () {
      kiriofChangeCodPayment();
      kiriofChangeDifferentAddress();
    });
  }
});

function kiriofInitBlockCheckoutCompatibility() {
  if (window.kiriofBlockCheckoutCompatibilityInitialized) {
    return;
  }
  window.kiriofBlockCheckoutCompatibilityInitialized = true;

  function kiriofScheduleCodInsurance(delay) {
    delay = typeof delay === "number" ? delay : 150;
    if (kiriofCodInsuranceTimer) {
      clearTimeout(kiriofCodInsuranceTimer);
    }
    kiriofCodInsuranceTimer = setTimeout(function () {
      kiriofCodInsuranceTimer = null;
      kiriofCodInsurance();
    }, delay);
  }

  function kiriofScheduleBlockShippingRatesRefresh(delay) {
    delay = typeof delay === "number" ? delay : 120;
    if (kiriofBlockRatesRefreshTimer) {
      clearTimeout(kiriofBlockRatesRefreshTimer);
    }
    kiriofBlockRatesRefreshTimer = setTimeout(function () {
      kiriofBlockRatesRefreshTimer = null;
      kiriofRefreshBlockShippingRates();
    }, delay);
  }

  // Block checkout: listen for shipping method changes via WC data store.
  // React blocks don't use jQuery change events reliably.
  if (typeof wp !== "undefined" && wp.data && wp.data.subscribe) {
    let kiriofLastShippingMethod = "";
    jQuery(document).on("change click", 'input[type="radio"]', function () {
      const selectedMethod = jQuery(this).val();
      if (!kiriofIsKiriminajaShippingMethod(selectedMethod)) {
        return;
      }

      kiriofRememberSelectedShippingMethod(selectedMethod);
      kiriofSelectBlockShippingRate(selectedMethod);
      setTimeout(function () {
        kiriofCodInsurance();
      }, 50);
    });

    wp.data.subscribe(function () {
      try {
        const currentMethod = kiriofGetSelectedBlockShippingMethod();
        if (
          currentMethod &&
          currentMethod !== kiriofLastShippingMethod &&
          currentMethod.indexOf("kiriminaja-official") === 0
        ) {
          if (
            kiriofGetPendingShippingMethod() &&
            currentMethod !== kiriofGetPendingShippingMethod()
          ) {
            return;
          }
          kiriofLastShippingMethod = currentMethod;
          // Delay to let the Store API update the cart first
          kiriofScheduleCodInsurance(400);
        }
      } catch (e) {}
    });

    // Block checkout: listen for payment method changes via WC payment store.
    // COD radios in Woo Blocks do not use the classic name=payment_method input.
    let kiriofLastPaymentMethod = "";
    wp.data.subscribe(function () {
      try {
        const currentPaymentMethod = kiriofGetPaymentMethod();
        if (
          !currentPaymentMethod ||
          currentPaymentMethod === kiriofLastPaymentMethod
        )
          return;
        kiriofLastPaymentMethod = currentPaymentMethod;
        kiriofScheduleCodInsurance(250);
      } catch (e) {}
    });

    jQuery(document).on(
      "change.kiriofBlockPaymentRefresh click.kiriofBlockPaymentRefresh",
      '[name="payment_method"], [name="radio-control-wc-payment-method-options"], .wc-block-checkout__payment-method input[type="radio"], .wc-block-components-checkout-step--payment input[type="radio"], [id*="payment-method-options"]',
      function (event) {
        const clickedPaymentMethod =
          kiriofGetPaymentMethodFromElement(event.target) ||
          kiriofGetPaymentMethodFromElement(this);
        if (!clickedPaymentMethod) {
          return;
        }

        kiriofRememberPendingPaymentMethod(clickedPaymentMethod);
        kiriofLastPaymentMethod = clickedPaymentMethod;
        kiriofScheduleCodInsurance(80);
      },
    );

    // Dynamic District options from postcode
    let kiriofLastPostcode = "";
    let kiriofLastTypedPostcode = "";
    let kiriofLastTypedPostcodeAt = 0;
    let kiriofPostcodeReapplyTimers = [];
    const kiriofFieldId = "kiriminaja-official/kiriof_destination_area";
    const kiriofBlockPostcodeSelectors = [
      "input#billing-postcode",
      "input#shipping-postcode",
      'input[name="billing_postcode"]',
      'input[name="shipping_postcode"]',
      'input[name="billing-postcode"]',
      'input[name="shipping-postcode"]',
      'input[id$="-postcode"]',
      'input[name$="[postcode]"]',
    ].join(",");

    function kiriofGetRelevantPostcodeInputs() {
      const useShippingAddress =
        jQuery('[name="ship_to_different_address"]:checked').length > 0;
      const primarySelector =
        useShippingAddress ?
          'input#shipping-postcode, input[name="shipping_postcode"], input[name="shipping-postcode"]'
        : 'input#billing-postcode, input[name="billing_postcode"], input[name="billing-postcode"]';

      const $primary = jQuery(primarySelector)
        .filter(":enabled")
        .filter(":visible");
      if ($primary.length) {
        return $primary;
      }

      return jQuery(kiriofBlockPostcodeSelectors)
        .filter(":enabled")
        .filter(function () {
          return (
            jQuery(this).is(":visible") &&
            jQuery(this).attr("type") !== "hidden"
          );
        });
    }

    function kiriofGetFocusedPostcodeInput() {
      return kiriofGetRelevantPostcodeInputs()
        .filter(function () {
          return this === document.activeElement;
        })
        .first();
    }

    function kiriofRestoreSavedPostcodeField() {
      if (
        kiriofLastTypedPostcodeAt &&
        Date.now() - kiriofLastTypedPostcodeAt < 5000
      ) {
        return false;
      }

      const savedPostcode = kiriofNormalizePostcode(kiriofSavedCheckoutPostcode);
      if (!savedPostcode) {
        return false;
      }

      let restored = false;
      kiriofGetRelevantPostcodeInputs().each(function () {
        const $input = jQuery(this);
        const currentValue = kiriofNormalizePostcode($input.val());
        if (currentValue === savedPostcode || currentValue) {
          return;
        }

        $input.val(savedPostcode).trigger("input").trigger("change");
        restored = true;
      });

      if (restored) {
        kiriofLastTypedPostcode = savedPostcode;
        kiriofLastTypedPostcodeAt = Date.now();
      }

      return restored;
    }

    function kiriofGetCheckoutPostcodeFromDom() {
      let postcode = "";
      const $focused = kiriofGetFocusedPostcodeInput();
      if ($focused.length) {
        return String($focused.val() || "");
      }

      kiriofGetRelevantPostcodeInputs().each(function () {
        const val = jQuery(this).val();
        if (val) {
          postcode = String(val);
          return false;
        }
      });
      return postcode;
    }

    function kiriofIsBlockDistrictSourceField($field) {
      const name = String($field.attr("name") || "");
      const id = String($field.attr("id") || "");

      if (!name && !id) {
        return false;
      }

      if (
        name.indexOf("kiriof_destination_area_name") !== -1 ||
        name.indexOf("kiriof_shipping_destination_area_name") !== -1
      ) {
        return false;
      }

      return (
        name === kiriofFieldId ||
        name.slice(-kiriofFieldId.length) === kiriofFieldId ||
        name.indexOf("/kiriof_destination_area") !== -1 ||
        id.indexOf("kiriof_destination_area") !== -1 ||
        id.indexOf("kiriof-destination-area") !== -1
      );
    }

    function kiriofGetBlockDistrictFields() {
      const selectors = [
        ".kiriof-block-district-source",
        '[name="' + kiriofFieldId + '"]',
        'input[name*="kiriof_destination_area"]',
        'textarea[name*="kiriof_destination_area"]',
        'input[id*="kiriof_destination_area"]',
        'textarea[id*="kiriof_destination_area"]',
        'input[id*="kiriof-destination-area"]',
        'textarea[id*="kiriof-destination-area"]',
      ].join(",");

      return jQuery(selectors).filter(function () {
        const $currentField = jQuery(this);

        return (
          !$currentField.is("select") &&
          !$currentField.hasClass("kiriof-block-district-select") &&
          kiriofIsBlockDistrictSourceField($currentField)
        );
      });
    }

    function kiriofGetBlockDistrictField() {
      return kiriofGetBlockDistrictFields().first();
    }

    function kiriofEnsureLegacyBlockDistrictMirror() {
      const $existing = jQuery("#kiriof-block-district-mirror");
      if ($existing.length) {
        return $existing;
      }

      let $container = jQuery("#kiriof_destination_area_group > div").first();
      if (!$container.length) {
        $container = jQuery('<div style="display: none"></div>').appendTo(
          "#kiriof_destination_area_group",
        );
      }

      return jQuery(
        '<input type="hidden" id="kiriof-block-district-mirror" name="kiriof_destination_area" value="">',
      ).appendTo($container);
    }

    function kiriofSetCheckoutTokenValue(isCompleted) {
      jQuery('[name="kiriof_checkout_token"]').val(isCompleted ? "1" : "");
    }

    function kiriofSyncBlockDistrictSourceField(
      destinationId,
      destinationName = "",
      options: { silentEvents?: boolean; triggerChange?: boolean } = {},
    ) {
      options = options || {};

      const $fields = kiriofGetBlockDistrictFields();
      if (!$fields.length) {
        return false;
      }
      const $field = $fields.first();

      $fields.each(function () {
        const $currentField = jQuery(this);
        $currentField
          .addClass("kiriof-block-district-source")
          .attr("type", "hidden")
          .removeAttr("required")
          .hide();

        let $currentWrapper = $currentField
          .closest(
            ".wc-block-components-text-input, .wc-block-components-address-form__input",
          )
          .first();
        if (!$currentWrapper.length) {
          $currentWrapper = $currentField.parent();
        }
        $currentWrapper.addClass("kiriof-block-district-source-wrapper").hide();
      });

      let $wrapper = $field
        .closest(
          ".wc-block-components-text-input, .wc-block-components-address-form__input",
        )
        .first();
      if (!$wrapper.length) {
        $wrapper = $field.parent();
      }
      $wrapper.addClass("kiriof-block-district-source-wrapper").hide();

      const normalizedValue = String(destinationId || "");
      $fields.each(function () {
        const $currentField = jQuery(this);
        const currentValue = String($currentField.val() || "");
        const sourceField = $currentField.get(0);
        if (!sourceField) {
          return;
        }

        sourceField.removeAttribute("required");
        sourceField.required = false;
        sourceField.setAttribute(
          "aria-invalid",
          normalizedValue ? "false" : "true",
        );
        if (
          normalizedValue &&
          typeof sourceField.setCustomValidity === "function"
        ) {
          sourceField.setCustomValidity("");
        }
        if (currentValue !== normalizedValue) {
          try {
            const valueSetter = Object.getOwnPropertyDescriptor(
              window.HTMLInputElement.prototype,
              "value",
            );
            if (valueSetter && typeof valueSetter.set === "function") {
              valueSetter.set.call(sourceField, normalizedValue);
            } else {
              sourceField.value = normalizedValue;
            }
          } catch (e) {
            sourceField.value = normalizedValue;
          }

          sourceField.setAttribute("value", normalizedValue);
          if (!options.silentEvents) {
            sourceField.dispatchEvent(new Event("input", { bubbles: true }));
            if (options.triggerChange) {
              sourceField.dispatchEvent(new Event("change", { bubbles: true }));
            }
          }
        }
      });

      kiriofEnsureLegacyBlockDistrictMirror().val(normalizedValue);
      kiriofSetCheckoutTokenValue(!!normalizedValue);

      if (typeof destinationName !== "undefined") {
        jQuery('[name="kiriof_destination_area_name"]').val(
          destinationName || "",
        );
        jQuery('[name="kiriof_shipping_destination_area_name"]').val(
          destinationName || "",
        );
      }

      if (normalizedValue) {
        try {
          if (typeof wp !== "undefined" && wp.data && wp.data.dispatch) {
            const validationDispatch = wp.data.dispatch("wc/store/validation");
            if (
              validationDispatch &&
              typeof validationDispatch.clearValidationErrors === "function"
            ) {
              validationDispatch.clearValidationErrors([
                "shipping_" + kiriofFieldId,
                "billing_" + kiriofFieldId,
                kiriofFieldId,
              ]);
            } else if (
              validationDispatch &&
              typeof validationDispatch.clearValidationError === "function"
            ) {
              validationDispatch.clearValidationError(
                "shipping_" + kiriofFieldId,
              );
              validationDispatch.clearValidationError(
                "billing_" + kiriofFieldId,
              );
              validationDispatch.clearValidationError(kiriofFieldId);
            }
          }
        } catch (e) {}
      }

      return true;
    }

    function kiriofResyncSelectedBlockDistrictSource() {
      const $select = jQuery(".kiriof-block-district-select").first();
      const districtValue = String(
        ($select.length ? $select.val() || "" : "") ||
          kiriofEnsureLegacyBlockDistrictMirror().val() ||
          "",
      );

      if (!districtValue || districtValue === "0") {
        return false;
      }

      let districtLabel =
        $select.length ? $select.find("option:selected").text() || "" : "";
      districtLabel =
        districtLabel ||
        jQuery('[name="kiriof_destination_area_name"]').val() ||
        "";

      kiriofSyncBlockDistrictSourceField(districtValue, districtLabel, {
        silentEvents: true,
      });
      return true;
    }

    function kiriofUpdateBlockCheckoutPostcode(postcode: KiriofRuntimeValue) {
      postcode = kiriofNormalizePostcode(postcode);
      kiriofSavedCheckoutPostcode = postcode;

      if (
        typeof wp === "undefined" ||
        !wp.data ||
        !wp.data.select ||
        !wp.data.dispatch
      ) {
        return;
      }

      try {
        const checkoutStore = wp.data.select("wc/store/checkout");
        const checkoutDispatch = wp.data.dispatch("wc/store/checkout");
        const editingShippingAddress =
          (
            checkoutStore &&
            typeof checkoutStore.getEditingShippingAddress === "function"
          ) ?
            checkoutStore.getEditingShippingAddress() || {}
          : {};
        const editingBillingAddress =
          (
            checkoutStore &&
            typeof checkoutStore.getEditingBillingAddress === "function"
          ) ?
            checkoutStore.getEditingBillingAddress() || {}
          : {};

        if (
          checkoutDispatch &&
          typeof checkoutDispatch.setEditingShippingAddress === "function"
        ) {
          checkoutDispatch.setEditingShippingAddress(
            Object.assign({}, editingShippingAddress, {
              postcode: postcode,
            }),
          );
        }

        if (
          checkoutDispatch &&
          typeof checkoutDispatch.setEditingBillingAddress === "function"
        ) {
          checkoutDispatch.setEditingBillingAddress(
            Object.assign({}, editingBillingAddress, {
              postcode: postcode,
            }),
          );
        }
      } catch (e) {}
    }

    function kiriofSetPostcodeInputValue($input, postcode) {
      const input = $input.get(0);
      if (!input) {
        return false;
      }

      const currentValue = kiriofNormalizePostcode($input.val());
      if (currentValue === postcode) {
        return false;
      }

      try {
        const valueSetter = Object.getOwnPropertyDescriptor(
          window.HTMLInputElement.prototype,
          "value",
        );
        if (valueSetter && typeof valueSetter.set === "function") {
          valueSetter.set.call(input, postcode);
        } else {
          input.value = postcode;
        }
      } catch (e) {
        input.value = postcode;
      }

      input.setAttribute("value", postcode);
      input.dispatchEvent(new Event("input", { bubbles: true }));
      return true;
    }

    function kiriofReapplyTypedPostcode(postcode) {
      postcode = kiriofNormalizePostcode(postcode);
      const recentlyTyped =
        kiriofLastTypedPostcodeAt &&
        Date.now() - kiriofLastTypedPostcodeAt < 5000;
      if (
        !recentlyTyped ||
        kiriofNormalizePostcode(kiriofLastTypedPostcode) !== postcode
      ) {
        return;
      }

      kiriofUpdateBlockCheckoutPostcode(postcode);
      kiriofGetRelevantPostcodeInputs().each(function () {
        kiriofSetPostcodeInputValue(jQuery(this), postcode);
      });
    }

    function kiriofSchedulePostcodeReapply(postcode: KiriofRuntimeValue) {
      postcode = kiriofNormalizePostcode(postcode);
      kiriofPostcodeReapplyTimers.forEach(function (timer) {
        clearTimeout(timer);
      });
      kiriofPostcodeReapplyTimers = [];

      [50, 150, 350, 700, 1200, 2200].forEach(function (delay) {
        const timer = setTimeout(function () {
          kiriofReapplyTypedPostcode(postcode);
        }, delay);
        kiriofPostcodeReapplyTimers.push(timer);
      });
    }

    function kiriofUpdateCheckoutAdditionalFields(val) {
      if (
        typeof wp === "undefined" ||
        !wp.data ||
        !wp.data.select ||
        !wp.data.dispatch
      ) {
        return;
      }

      try {
        const checkoutStore = wp.data.select("wc/store/checkout");
        const checkoutDispatch = wp.data.dispatch("wc/store/checkout");
        if (
          checkoutDispatch &&
          typeof checkoutDispatch.setAdditionalFields === "function"
        ) {
          const additionalFields =
            (
              checkoutStore &&
              typeof checkoutStore.getAdditionalFields === "function"
            ) ?
              checkoutStore.getAdditionalFields() || {}
            : {};

          additionalFields[kiriofFieldId] = val;
          checkoutDispatch.setAdditionalFields(additionalFields);
        }

        let editingShippingAddress =
          (
            checkoutStore &&
            typeof checkoutStore.getEditingShippingAddress === "function"
          ) ?
            checkoutStore.getEditingShippingAddress() || {}
          : {};
        let editingBillingAddress =
          (
            checkoutStore &&
            typeof checkoutStore.getEditingBillingAddress === "function"
          ) ?
            checkoutStore.getEditingBillingAddress() || {}
          : {};

        editingShippingAddress = Object.assign({}, editingShippingAddress);
        editingBillingAddress = Object.assign({}, editingBillingAddress);

        editingShippingAddress[kiriofFieldId] = val;
        editingBillingAddress[kiriofFieldId] = val;

        if (typeof checkoutDispatch.setEditingShippingAddress === "function") {
          checkoutDispatch.setEditingShippingAddress(editingShippingAddress);
        }

        if (typeof checkoutDispatch.setEditingBillingAddress === "function") {
          checkoutDispatch.setEditingBillingAddress(editingBillingAddress);
        }
      } catch (e) {}
    }

    function kiriofGetDestinationAreaAjaxData(val, label, different_address) {
      return {
        action: "kiriof_get_destination_area",
        val: val,
        insurance: kiriofBillingAddressConfig.globalInsurance ? 1 : 0,
        different_address: different_address,
        text: label || "",
        postcode: kiriofGetCurrentPostcodeKey(),
        payment_method: kiriofGetPaymentMethod(),
        nonce: kiriofBillingAddressConfig.destinationNonce || "",
        country: jQuery("#billing_country").find(":selected").val() || "ID",
      };
    }

    function kiriofPersistDestinationArea(val, label, different_address, done) {
      if (!val) {
        if (typeof done === "function") done(false);
        return;
      }
      jQuery.ajax({
        url: kiriofBillingAddressConfig.ajaxUrl || "",
        type: "post",
        data: kiriofGetDestinationAreaAjaxData(val, label, different_address),
        dataType: "JSON",
        complete: function (response) {
          let ok = false;
          try {
            ok = !!(
              response &&
              response.responseJSON &&
              response.responseJSON.success
            );
          } catch (e) {}
          if (typeof done === "function") done(ok);
        },
      });
    }

    function kiriofRefreshBlockShippingRates() {
      if (typeof wp === "undefined" || !wp.data || !wp.data.dispatch) {
        return;
      }

      try {
        const cartDispatch = wp.data.dispatch("wc/store/cart");
        if (
          cartDispatch &&
          typeof cartDispatch.invalidateResolutionForStoreSelector ===
            "function"
        ) {
          cartDispatch.invalidateResolutionForStoreSelector("getShippingRates");
        }
        if (
          cartDispatch &&
          typeof cartDispatch.invalidateResolutionForStore === "function"
        ) {
          cartDispatch.invalidateResolutionForStore();
        }
      } catch (e) {}

      try {
        const coreDataDispatch = wp.data.dispatch("core/data");
        if (
          coreDataDispatch &&
          typeof coreDataDispatch.invalidateResolution === "function"
        ) {
          coreDataDispatch.invalidateResolution(
            "wc/store/cart",
            "getShippingRates",
            [],
          );
        }
      } catch (e) {}
    }

    function kiriofDispatchWooBlocksCartRefresh() {
      try {
        document.body.dispatchEvent(
          new CustomEvent("wc-blocks_added_to_cart", {
            bubbles: true,
            detail: { preserveCartData: false },
          }),
        );
      } catch (e) {}
    }

    function kiriofGetCheckoutFieldValue(addressType, field) {
      const selectors = [
        "#" + addressType + "-" + field,
        "#" + addressType + "_" + field,
        '[name="' + addressType + "_" + field + '"]',
        '[name="' + addressType + "-" + field + '"]',
      ];
      for (let i = 0; i < selectors.length; i++) {
        const $field = jQuery(selectors[i]).first();
        if ($field.length) {
          const value = $field.val();
          if (value) {
            return String(value);
          }
        }
      }

      return "";
    }

    function kiriofBuildStoreApiAddressFromDom(addressType): KiriofStoreApiAddress {
      const address: KiriofStoreApiAddress = {
        first_name: kiriofGetCheckoutFieldValue(addressType, "first_name"),
        last_name: kiriofGetCheckoutFieldValue(addressType, "last_name"),
        company: kiriofGetCheckoutFieldValue(addressType, "company"),
        address_1: kiriofGetCheckoutFieldValue(addressType, "address_1"),
        address_2: kiriofGetCheckoutFieldValue(addressType, "address_2"),
        city: kiriofGetCheckoutFieldValue(addressType, "city"),
        state: kiriofGetCheckoutFieldValue(addressType, "state"),
        postcode: kiriofGetCheckoutFieldValue(addressType, "postcode"),
        country: kiriofGetCheckoutFieldValue(addressType, "country") || "ID",
        phone: kiriofGetCheckoutFieldValue(addressType, "phone"),
        email: kiriofGetCheckoutFieldValue(addressType, "email"),
      };

      if (addressType === "billing") {
        address.email =
          kiriofGetCheckoutFieldValue("billing", "email") ||
          kiriofGetCheckoutFieldValue("", "email") ||
          jQuery('input[type="email"]').first().val() ||
          "";
      }

      return address;
    }

    function kiriofApplyCurrentPostcodeToStoreApiAddress(address, postcode) {
      postcode = kiriofNormalizePostcode(postcode);
      if (!address || !postcode) {
        return address;
      }

      address.postcode = postcode;
      return address;
    }

    function kiriofAddressHasCheckoutValue(address) {
      return !!(
        address &&
        (address.address_1 ||
          address.city ||
          address.state ||
          address.postcode ||
          address.country ||
          address.first_name ||
          address.last_name)
      );
    }

    function kiriofForceBlockCartUpdate(districtName, districtId) {
      // Invalidate shipping rates through every available mechanism
      try {
        const cartDispatch = wp.data.dispatch("wc/store/cart");
        if (cartDispatch) {
          if (
            typeof cartDispatch.invalidateResolutionForStoreSelector ===
            "function"
          ) {
            cartDispatch.invalidateResolutionForStoreSelector(
              "getShippingRates",
            );
          }
          if (typeof cartDispatch.invalidateResolutionForStore === "function") {
            cartDispatch.invalidateResolutionForStore();
          }
        }
      } catch (e) {}

      try {
        const coreDataDispatch = wp.data.dispatch("core/data");
        if (coreDataDispatch) {
          if (typeof coreDataDispatch.invalidateResolution === "function") {
            coreDataDispatch.invalidateResolution(
              "wc/store/cart",
              "getShippingRates",
              [],
            );
          }
        }
      } catch (e) {}

      if (!districtId) return;
      const currentPostcode = kiriofGetCurrentPostcodeKey();
      const rawUpdateKey = [
        districtId || "",
        districtName || "",
        currentPostcode || "",
      ].join("|");
      const now = Date.now();
      if (
        rawUpdateKey === kiriofLastRawStoreCustomerUpdateKey &&
        now - kiriofLastRawStoreCustomerUpdateAt < 1500
      ) {
        kiriofSyncBlockDistrictWarningState();
        return;
      }
      kiriofLastRawStoreCustomerUpdateKey = rawUpdateKey;
      kiriofLastRawStoreCustomerUpdateAt = now;

      // Use raw fetch + Store API nonce instead of wp.data dispatch / wp.apiFetch.
      // Some WooCommerce versions / themes do not expose wp.data on the
      // frontend, and wp.apiFetch may not be loaded either.  A plain POST to
      // the Store API update-customer endpoint is the most reliable approach.
      try {
        const postData: KiriofStoreApiPayload = {
          additional_fields: {
            "kiriminaja-official/kiriof_destination_area": String(districtId),
          },
        };
        const shippingAddress = kiriofApplyCurrentPostcodeToStoreApiAddress(
          kiriofBuildStoreApiAddressFromDom("shipping"),
          currentPostcode,
        );
        const billingAddress = kiriofApplyCurrentPostcodeToStoreApiAddress(
          kiriofBuildStoreApiAddressFromDom("billing"),
          currentPostcode,
        );
        if (kiriofAddressHasCheckoutValue(shippingAddress)) {
          shippingAddress[kiriofFieldId] = String(districtId);
          postData.shipping_address = shippingAddress;
        }
        if (kiriofAddressHasCheckoutValue(billingAddress)) {
          billingAddress[kiriofFieldId] = String(districtId);
          postData.billing_address = billingAddress;
        } else if (postData.shipping_address) {
          postData.billing_address = Object.assign(
            {},
            postData.shipping_address,
          );
          postData.billing_address.email =
            jQuery('input[type="email"]').first().val() || "";
        }
        if (districtName) {
          postData.additional_fields[
            "kiriminaja-official/kiriof_destination_area_name"
          ] = String(districtName);
        }

        const nonce =
          kiriofStoreApiNonce ||
          (window.wpApiSettings && window.wpApiSettings.nonce) ||
          (window.wp &&
            window.wp.apiFetch &&
            window.wp.apiFetch.nonceMiddleware &&
            window.wp.apiFetch.nonceMiddleware.nonce) ||
          "";
        const headers = {
          Accept: "application/json, */*;q=0.1",
          "Content-Type": "application/json",
        };
        if (nonce) {
          headers["Nonce"] = nonce;
        }

        if (window.console)
          console.log(
            "[KiriminAja] POST update-customer district=" +
              districtId +
              " nonce=" +
              (nonce ? "yes" : "no"),
          );

        fetch(kiriofStoreApiUpdateCustomerUrl, {
          method: "POST",
          credentials: "same-origin",
          headers: headers,
          body: JSON.stringify(postData),
        })
          .then(function (resp) {
            if (window.console)
              console.log("[KiriminAja] update-customer status=" + resp.status);
            if (resp.ok) {
              kiriofDispatchWooBlocksCartRefresh();
              setTimeout(kiriofSyncBlockDistrictWarningState, 80);
              setTimeout(kiriofSyncBlockDistrictWarningState, 600);
              setTimeout(kiriofRefreshBlockShippingRates, 700);
            }
          })
          .catch(function (err) {
            if (window.console)
              console.warn("[KiriminAja] update-customer error:", err);
          });
      } catch (e) {}
    }

    function kiriofBuildBlockCartUpdateKey(data) {
      return [
        data.shipping_metode_id || "",
        data.destination_id || "",
        data.destination_name || "",
        data.postcode || "",
        data.payment_method || "",
        data.insurance || 0,
        data.force_insurance || 0,
      ].join("|");
    }

    function kiriofPersistBlockDistrictSelection(data) {
      if (window.console)
        console.log("[KiriminAja] Persist district selection", data);
      const updateKey = kiriofBuildBlockCartUpdateKey(data);
      if (updateKey && updateKey === kiriofLastBlockCartUpdateKey) {
        if (window.console)
          console.log(
            "[KiriminAja] District already persisted (dedup), refreshing rates",
          );
        kiriofScheduleBlockShippingRatesRefresh(160);
        kiriofForceBlockCartUpdate(
          data.destination_name || "",
          data.destination_id || "",
        );
        return;
      }

      kiriofLastBlockCartUpdateKey = updateKey;

      function kiriofAfterBlockDistrictPersist() {
        kiriofScheduleBlockShippingRatesRefresh(80);

        // Save session data (insurance, payment_method, destination) via AJAX
        // as fallback when extensionCartUpdate is not available.
        const ajaxUrl =
          typeof kiriofAjax !== "undefined" && kiriofAjax.ajaxurl ?
            kiriofAjax.ajaxurl
          : kiriofBillingAddressConfig.ajaxUrl || "";
        const ajaxNonce =
          typeof kiriofAjax !== "undefined" && kiriofAjax.nonce ?
            kiriofAjax.nonce
          : kiriofBillingAddressConfig.nonce || "";

        const formData = new FormData();
        formData.append("action", "kiriof-session-save");
        formData.append("nonce", ajaxNonce);
        formData.append(
          "data",
          JSON.stringify({
            destination_id: data.destination_id || 0,
            destination_name: data.destination_name || "",
            postcode: data.postcode || "",
            payment_method: data.payment_method || "",
            insurance: data.insurance || 0,
            force_insurance: data.force_insurance || 0,
            shipping_metode_id: data.shipping_metode_id || "",
          }),
        );

        fetch(ajaxUrl, {
          method: "POST",
          credentials: "same-origin",
          body: formData,
        })
          .then(function () {
            kiriofForceBlockCartUpdate(
              data.destination_name || "",
              data.destination_id || "",
            );
          })
          .catch(function () {
            kiriofForceBlockCartUpdate(
              data.destination_name || "",
              data.destination_id || "",
            );
          });
      }

      const result = kiriofBlockExtensionCartUpdate(data);
      if (result && typeof result.then === "function") {
        result
          .then(function () {
            kiriofAfterBlockDistrictPersist();
          })
          .catch(function () {
            kiriofScheduleBlockShippingRatesRefresh(160);
            kiriofForceBlockCartUpdate(
              data.destination_name || "",
              data.destination_id || "",
            );
          });
      } else {
        kiriofAfterBlockDistrictPersist();
      }
    }

    let kiriofLastDistrictResults = [];
    let kiriofPendingDistrictRestore = false;
    let kiriofDistrictResultsLoading = false;
    let kiriofDistrictLookupRequestId = 0;
    let kiriofPostcodeLookupTimer = null;

    function kiriofNormalizePostcode(postcode) {
      return String(postcode || "")
        .replace(/\s+/g, "")
        .trim();
    }

    function kiriofRenderBlockDistrictSelect(results) {
      if (!results || !results.length) return false;
      kiriofLastDistrictResults = results;

      const $field = kiriofGetBlockDistrictField();
      if (!$field.length) return false;

      // Woo Blocks renders this as a React-controlled text input.
      // Hide the React source field and render our selectable UI at
      // the block field wrapper so React re-renders do not remove it.
      kiriofSyncBlockDistrictSourceField($field.val(), undefined);

      let $wrapper = $field
        .closest(
          ".wc-block-components-text-input, .wc-block-components-address-form__input",
        )
        .first();
      if (!$wrapper.length) {
        $wrapper = $field.parent();
      }
      $wrapper.addClass("kiriof-block-district-source-wrapper").hide();

      let $select = jQuery(".kiriof-block-district-select");
      if (!$select.length) {
        const $fieldWrapper = jQuery(
          '<div class="wc-block-components-address-form__state wc-block-components-state-input kiriof-block-district-field-wrapper"></div>',
        );
        const $selectWrapper = jQuery(
          '<div class="wc-blocks-components-select kiriof-block-district-select-wrapper"></div>',
        );
        const $container = jQuery(
          '<div class="wc-blocks-components-select__container kiriof-block-district-select-container"></div>',
        );
        const $label = jQuery(
          '<label for="kiriof-block-district-select" class="wc-blocks-components-select__label">' +
            (kiriofBillingAddressConfig.i18n.district || "District") +
            "</label>",
        );
        $select = jQuery(
          '<select size="1" class="wc-blocks-components-select__select kiriof-block-district-select" id="kiriof-block-district-select" aria-invalid="false" autocomplete="section-shipping shipping address-level3"></select>',
        );
        const $expand = jQuery(
          '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24" class="wc-blocks-components-select__expand" aria-hidden="true" focusable="false"><path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path></svg>',
        );
        $container.append($label, $select, $expand);
        $selectWrapper.append($container);
        $fieldWrapper.append($selectWrapper);
        $wrapper.after($fieldWrapper);

        // Direct change handler on the select for reliability.
        // The delegated handler on `document` may not fire in WooCommerce
        // blocks (React 18 captures native events before they reach document).
        $select.on("change.kiriofBlockDistrictDirect", function () {
          if (window.console)
            console.log("[KiriminAja] Select changed", jQuery(this).val());
          try {
            const districtVal = jQuery(this).val();
            const districtLabel = jQuery(this).find("option:selected").text();
            const postcode = kiriofGetCurrentPostcodeKey();

            kiriofSyncBlockDistrictSourceField(
              districtVal,
              districtLabel || "",
              { silentEvents: true },
            );
            kiriofRememberDistrictForPostcode(
              postcode,
              districtVal,
              districtLabel,
            );
            kiriofPendingDistrictRestore = false;
            kiriofDistrictResultsLoading = false;
            kiriofSyncBlockDistrictWarningState();

            const persistData = {
              destination_id: parseInt(String(districtVal || "0")) || 0,
              destination_name: districtLabel || "",
              postcode: postcode,
              payment_method: kiriofGetPaymentMethod(),
              insurance: kiriofBillingAddressConfig.globalInsurance ? 1 : 0,
              force_insurance: parseInt(
                jQuery("[name=kiriof_force_insurance]").val() || "0",
              ),
            };

            kiriofPersistBlockDistrictSelection(persistData);
          } catch (e) {
            if (window.console) {
              console.error("[KiriminAja] District change handler error:", e);
            }
          }
        });
      }

      const currentPostcode = kiriofGetCurrentPostcodeKey();
      const savedDistrict = kiriofGetSavedDistrictForPostcode(currentPostcode);
      // MutationObserver may fire before React has applied the controlled value to
      // the input's DOM property, so $field.val() can be '' even for logged-in users
      // who have a saved district in their WC customer meta. Fall back to the WC
      // cart data store (getBillingAddress/getShippingAddress) which always reflects
      // the customer's persisted address, including additional address fields.
      let currentValue =
        $field.val() ||
        kiriofGetStoredDistrictIdFromWcStore() ||
        ($select.length ? $select.val() : "") ||
        (savedDistrict ? String(savedDistrict.destination_id || "") : "");
      let currentName =
        jQuery('[name="kiriof_destination_area_name"]').val() ||
        (savedDistrict ? String(savedDistrict.destination_name || "") : "");
      let hasMatchingSavedDistrict = false;
      const placeholderSelected = currentValue ? "" : " selected";
      let html =
        '<option value="" data-alternate-values="[' +
        (kiriofBillingAddressConfig.i18n.selectDistrict || "Select District") +
        ']" disabled' +
        placeholderSelected +
        ">" +
        (kiriofBillingAddressConfig.i18n.selectDistrict || "Select District") +
        "</option>";
      results.forEach(function (d) {
        const selected = String(d.id) === String(currentValue) ? " selected" : "";
        html +=
          '<option value="' +
          d.id +
          '" data-alternate-values="[' +
          d.text +
          ']"' +
          selected +
          ">" +
          d.text +
          "</option>";
        if (String(d.id) === String(currentValue)) {
          currentName = d.text;
          hasMatchingSavedDistrict = true;
        }
      });

      if (currentValue && !hasMatchingSavedDistrict) {
        currentValue = "";
        currentName = "";
      }

      $select.html(html).val(currentValue);
      kiriofSyncBlockDistrictSourceField(currentValue, currentName);

      if (currentValue && currentName) {
        kiriofUpdateCheckoutAdditionalFields(String(currentValue));
        kiriofRememberDistrictForPostcode(
          currentPostcode,
          currentValue,
          currentName,
        );
      } else {
        kiriofSyncBlockDistrictSourceField("", "");
      }

      return true;
    }

    function kiriofRestoreSavedCheckoutState() {
      kiriofRestoreSavedPostcodeField();
      if (
        kiriofLastDistrictResults.length &&
        !jQuery(".kiriof-block-district-select").length
      ) {
        kiriofRenderBlockDistrictSelect(kiriofLastDistrictResults);
      }
      kiriofRestoreSavedDistrictForCurrentPostcode();
      kiriofSyncBlockDistrictWarningState();
    }

    if (typeof MutationObserver !== "undefined") {
      let kiriofDistrictObserverTimer = null;
      const kiriofDistrictObserver = new MutationObserver(function () {
        if (kiriofDistrictObserverTimer) {
          clearTimeout(kiriofDistrictObserverTimer);
        }

        kiriofDistrictObserverTimer = setTimeout(function () {
          kiriofDistrictObserverTimer = null;
          if (
            kiriofLastDistrictResults.length &&
            !jQuery(".kiriof-block-district-select").length
          ) {
            kiriofRenderBlockDistrictSelect(kiriofLastDistrictResults);
            kiriofRestoreSavedCheckoutState();
          }
          kiriofResyncSelectedBlockDistrictSource();
        }, 200);
      });

      const districtObserverTarget = jQuery(
        ".wp-block-woocommerce-checkout, .wc-block-checkout, form.wc-block-checkout__form",
      )
        .first()
        .get(0);

      if (districtObserverTarget) {
        kiriofDistrictObserver.observe(districtObserverTarget, {
          childList: true,
          subtree: true,
        });
      }
    }

    function kiriofScheduleFetchDistricts(postcode, delay = 0) {
      if (kiriofPostcodeLookupTimer) {
        clearTimeout(kiriofPostcodeLookupTimer);
      }
      kiriofPostcodeLookupTimer = setTimeout(
        function () {
          kiriofPostcodeLookupTimer = null;
          kiriofFetchDistricts(postcode);
        },
        typeof delay === "number" ? delay : 250,
      );
    }

    function kiriofFetchDistricts(postcode) {
      postcode = kiriofNormalizePostcode(postcode);
      const activePostcode = kiriofNormalizePostcode(
        kiriofGetCheckoutPostcodeFromDom(),
      );
      if (activePostcode && postcode && activePostcode !== postcode) {
        const recentlyTyped =
          kiriofLastTypedPostcodeAt &&
          Date.now() - kiriofLastTypedPostcodeAt < 2500;
        if (recentlyTyped || kiriofGetFocusedPostcodeInput().length) {
          postcode = activePostcode;
        }
      }
      if (!postcode || postcode === kiriofLastPostcode || postcode.length < 3)
        return;
      kiriofLastPostcode = postcode;
      const requestId = ++kiriofDistrictLookupRequestId;
      // Also check WC store so the "loading" state is shown for logged-in users
      // whose district is saved in customer meta but not yet in kiriofSavedDistrictByPostcode.
      kiriofPendingDistrictRestore =
        !!kiriofGetSavedDistrictForPostcode(postcode) ||
        !!kiriofGetStoredDistrictIdFromWcStore();
      kiriofDistrictResultsLoading = true;
      kiriofResetBlockDistrictState({
        silentWarning: kiriofPendingDistrictRestore,
        skipCheckoutSync: true,
        skipStoreSync: true,
      });

      const kiriofAjaxUrl =
        typeof kiriofAjax !== "undefined" && kiriofAjax.ajaxurl ?
          kiriofAjax.ajaxurl
        : kiriofBillingAddressConfig.ajaxUrl || "";
      const kiriofAjaxNonce =
        typeof kiriofAjax !== "undefined" && kiriofAjax.nonce ?
          kiriofAjax.nonce
        : kiriofBillingAddressConfig.nonce || "";

      jQuery.ajax({
        type: "post",
        url: kiriofAjaxUrl,
        data: {
          action: "kiriminaja_subdistrict_search",
          data: { term: postcode },
          nonce: kiriofAjaxNonce,
        },
        success: function (response) {
          const currentPostcode = kiriofNormalizePostcode(
            kiriofGetCurrentPostcodeKey(),
          );
          if (
            requestId !== kiriofDistrictLookupRequestId ||
            currentPostcode !== postcode
          ) {
            return;
          }
          kiriofDistrictResultsLoading = false;
          if (!response || !response.data || !response.data.length) {
            kiriofPendingDistrictRestore = false;
            kiriofSyncBlockDistrictWarningState();
            return;
          }
          kiriofRenderBlockDistrictSelect(response.data);
          kiriofRestoreSavedDistrictForCurrentPostcode();
          kiriofSyncBlockDistrictWarningState();
        },
        error: function () {
          const currentPostcode = kiriofNormalizePostcode(
            kiriofGetCurrentPostcodeKey(),
          );
          if (
            requestId !== kiriofDistrictLookupRequestId ||
            currentPostcode !== postcode
          ) {
            return;
          }
          kiriofDistrictResultsLoading = false;
          kiriofPendingDistrictRestore = false;
          kiriofSyncBlockDistrictWarningState();
        },
      });
    }

    function kiriofGetCurrentPostcodeKey() {
      const recentlyTyped =
        kiriofLastTypedPostcodeAt &&
        Date.now() - kiriofLastTypedPostcodeAt < 5000;
      if (recentlyTyped) {
        return kiriofNormalizePostcode(kiriofLastTypedPostcode);
      }

      let postcode = kiriofGetCheckoutPostcodeFromDom();
      if (!postcode && kiriofSavedCheckoutPostcode) {
        postcode = kiriofSavedCheckoutPostcode;
      }
      if (!postcode && typeof wp !== "undefined" && wp.data && wp.data.select) {
        try {
          const store = wp.data.select("wc/store/cart");
          const shipping =
            store && store.getShippingAddress ? store.getShippingAddress() : {};
          const billing =
            store && store.getBillingAddress ? store.getBillingAddress() : {};
          postcode =
            (shipping && shipping.postcode) ||
            (billing && billing.postcode) ||
            "";
        } catch (e) {}
      }

      return kiriofNormalizePostcode(postcode);
    }

    function kiriofGetSavedDistrictForPostcode(postcode) {
      postcode = kiriofNormalizePostcode(postcode);
      if (
        !postcode ||
        !kiriofSavedDistrictByPostcode ||
        !kiriofSavedDistrictByPostcode[postcode]
      ) {
        return null;
      }

      return kiriofSavedDistrictByPostcode[postcode];
    }

    function kiriofRememberDistrictForPostcode(
      postcode,
      destinationId,
      destinationName,
    ) {
      postcode = kiriofNormalizePostcode(postcode);
      if (!postcode || !destinationId) {
        return;
      }

      kiriofSavedDistrictByPostcode[postcode] = {
        destination_id: String(destinationId),
        destination_name: destinationName || "",
      };
    }

    /**
     * Read the saved kiriof_destination_area value from the WC cart data store.
     * For logged-in users, WC persists additional address fields to customer meta,
     * so this works even when the WC session is fresh (e.g. after session expiry).
     */
    function kiriofGetStoredDistrictIdFromWcStore() {
      try {
        if (typeof wp === "undefined" || !wp.data || !wp.data.select) return "";
        const cartStore = wp.data.select("wc/store/cart");
        if (!cartStore) return "";
        const billing =
          cartStore.getBillingAddress ? cartStore.getBillingAddress() : {};
        const shipping =
          cartStore.getShippingAddress ? cartStore.getShippingAddress() : {};
        return String(
          (shipping && shipping[kiriofFieldId]) ||
            (billing && billing[kiriofFieldId]) ||
            "",
        );
      } catch (e) {
        return "";
      }
    }

    function kiriofGetBlockShippingOptionsContainer() {
      return jQuery(
        ".wp-block-woocommerce-checkout-shipping-methods-block, .wc-block-components-shipping-rates-control, .wc-block-checkout__shipping-method, .wc-block-components-checkout-step--shipping-method",
      ).first();
    }

    function kiriofEnsureBlockDistrictWarning() {
      // Place the warning inside the shipping options step using the native
      // WC "no shipping address" structure so it blends with the WC UI.
      let $shippingMethodStep = jQuery(
        ".wp-block-woocommerce-checkout-shipping-methods-block, " +
          ".wc-block-checkout__shipping-option, " +
          ".wc-block-components-checkout-step--shipping-method",
      ).first();

      // Fall back to the shipping address section if the shipping methods
      // step is not in the DOM yet.
      if (!$shippingMethodStep.length) {
        $shippingMethodStep = jQuery(
          ".wp-block-woocommerce-checkout-shipping-address-block, " +
            ".wc-block-components-checkout-step--shipping-address, " +
            ".wc-block-checkout__shipping-address",
        ).first();
      }

      if (!$shippingMethodStep.length) {
        return jQuery();
      }

      let $warning = $shippingMethodStep.find(".kiriof-block-district-warning");
      if (!$warning.length) {
        // Use the exact same markup & classes WooCommerce uses for its
        // "Enter a shipping address to view shipping options." message
        // so the notice looks completely native.
        $warning = jQuery(
          '<div class="wc-block-components-shipping-rates-control kiriof-block-district-warning" role="alert">' +
            '<div class="wc-block-components-shipping-rates-control__package">' +
            '<p role="status" aria-live="polite" ' +
            'class="wc-block-components-shipping-rates-control__no-shipping-address-message">' +
            "" +
            (kiriofBillingAddressConfig.i18n.districtWarning ||
              "Please select your District to view shipping options.") +
            "" +
            "</p>" +
            "</div>" +
            "</div>",
        ).hide();

        // Inject inside the step content so it appears where rates normally live.
        const $stepContent = $shippingMethodStep
          .find(".wc-block-components-checkout-step__content")
          .first();
        if ($stepContent.length) {
          $stepContent.prepend($warning);
        } else {
          const $headingContainer = $shippingMethodStep
            .find(".wc-block-components-checkout-step__heading-container")
            .first();
          if ($headingContainer.length) {
            $warning.insertAfter($headingContainer);
          } else {
            $shippingMethodStep.prepend($warning);
          }
        }
      }

      return $warning;
    }

    function kiriofHasValidBlockDistrict() {
      const districtId = kiriofGetDestinationId(
        jQuery('[name="ship_to_different_address"]:checked').length,
      );
      if (
        districtId &&
        String(districtId).trim() !== "" &&
        String(districtId) !== "0"
      ) {
        return true;
      }
      // Logged-in users show a compact address summary (no visible form inputs).
      // Fall back to the saved district map for the current postcode.
      const postcode = kiriofGetCurrentPostcodeKey();
      if (postcode) {
        const saved = kiriofGetSavedDistrictForPostcode(postcode);
        if (
          saved &&
          saved.destination_id &&
          String(saved.destination_id) !== "0"
        ) {
          return true;
        }
      }
      // Final fallback: WC store holds the persisted additional address field value
      // for logged-in users even before kiriofSavedDistrictByPostcode is populated.
      const storedId = kiriofGetStoredDistrictIdFromWcStore();
      if (storedId && String(storedId) !== "0") {
        return true;
      }
      return false;
    }

    function kiriofClearBlockShippingRatesFromStore() {
      // Guard against re-entrant calls (e.g. triggered by wp.data.subscribe).
      const clearBlockShippingRates = kiriofClearBlockShippingRatesFromStore as typeof kiriofClearBlockShippingRatesFromStore & { _pending?: boolean };
      if (clearBlockShippingRates._pending) {
        return;
      }
      clearBlockShippingRates._pending = true;
      window.setTimeout(function () {
        clearBlockShippingRates._pending = false;
      }, 800);

      // Clear the server-side destination_id session so calculate_shipping()
      // returns no rates — removes stale rates from the Order Summary when
      // the district warning is shown.
      kiriofBlockExtensionCartUpdate({
        destination_id: 0,
        destination_name: "",
        postcode: kiriofGetCurrentPostcodeKey(),
        payment_method: kiriofGetPaymentMethod(),
        insurance: kiriofBillingAddressConfig.globalInsurance ? 1 : 0,
        force_insurance: parseInt(
          jQuery("[name=kiriof_force_insurance]").val() || "0",
        ),
      });
      kiriofRefreshBlockShippingRates();
    }

    function kiriofClearBlockShippingMethodSelection() {
      jQuery(
        '.wc-block-components-radio-control__input[name*="shipping"], .wc-block-components-radio-control__input[value*="kiriminaja-official"], input.shipping_method',
      ).prop("checked", false);
    }

    function kiriofGetBlockPaymentOptionsContainer() {
      return jQuery(
        ".wp-block-woocommerce-checkout-payment-block, .wc-block-components-checkout-step--payment-method, .wc-block-checkout__payment",
      ).first();
    }

    // Split warning state sync into two concerns:
    // - kiriofSyncBlockDistrictWarningState: pure UI (body class + warning element)
    //   safe to call from anywhere including wp.data.subscribe
    // - kiriofClearBlockShippingRatesFromStore: triggers store dispatch, must ONLY
    //   be called from explicit user interactions, never from subscribe handlers
    function kiriofGetPlaceOrderButtonSelector() {
      return [
        "button.wc-block-components-checkout-place-order-button",
        ".wc-block-components-checkout-place-order button",
        ".wp-block-woocommerce-checkout-place-order button",
      ].join(", ");
    }

    function kiriofGetPlaceOrderButton() {
      return jQuery(kiriofGetPlaceOrderButtonSelector());
    }

    function kiriofSetPlaceOrderDisabled(disabled) {
      const $btn = kiriofGetPlaceOrderButton();
      if (!$btn.length) return;
      if (disabled) {
        $btn
          .attr("data-kiriof-disabled", "true")
          .addClass("kiriof-place-order-soft-disabled");

        $btn.each(function () {
          const $currentButton = jQuery(this);
          if (
            !$currentButton.prop("disabled") &&
            $currentButton.attr("aria-disabled") !== "true"
          ) {
            $currentButton
              .attr("data-kiriof-aria-disabled", "true")
              .attr("aria-disabled", "true");
          }
        });
      } else {
        $btn
          .removeAttr("data-kiriof-disabled")
          .removeClass("kiriof-place-order-soft-disabled");

        $btn.each(function () {
          const $currentButton = jQuery(this);
          if (
            $currentButton.attr("data-kiriof-aria-disabled") === "true" &&
            !$currentButton.prop("disabled")
          ) {
            $currentButton.removeAttr("aria-disabled");
          }
          $currentButton.removeAttr("data-kiriof-aria-disabled");
        });
      }
    }

    function kiriofCommitSelectedBlockDistrict() {
      const $districtSelect = jQuery(".kiriof-block-district-select").first();
      if (!$districtSelect.length) {
        return false;
      }

      const districtValue = String($districtSelect.val() || "");
      if (!districtValue) {
        return false;
      }

      const districtLabel =
        $districtSelect.find("option:selected").text() ||
        jQuery('[name="kiriof_destination_area_name"]').val() ||
        "";
      const postcode = kiriofGetCurrentPostcodeKey();

      kiriofSyncBlockDistrictSourceField(districtValue, districtLabel, {
        silentEvents: true,
      });
      kiriofUpdateCheckoutAdditionalFields(districtValue);
      kiriofRememberDistrictForPostcode(postcode, districtValue, districtLabel);
      kiriofPendingDistrictRestore = false;
      kiriofDistrictResultsLoading = false;

      return true;
    }

    if (!window.kiriofBlockPlaceOrderCaptureBound) {
      document.addEventListener(
        "click",
        function (event) {
          const target = event.target;
          if (!target || typeof target.closest !== "function") {
            return;
          }

          const button = target.closest(kiriofGetPlaceOrderButtonSelector());
          if (!button) {
            return;
          }

          kiriofCommitSelectedBlockDistrict();
        },
        true,
      );
      window.kiriofBlockPlaceOrderCaptureBound = true;
    }

    jQuery(document)
      .off("click.kiriofBlockPlaceOrder")
      .on(
        "click.kiriofBlockPlaceOrder",
        kiriofGetPlaceOrderButtonSelector(),
        function (event) {
          const $button = jQuery(this);
          kiriofCommitSelectedBlockDistrict();

          if ($button.attr("data-kiriof-disabled") !== "true") {
            return;
          }

          event.preventDefault();
          event.stopImmediatePropagation();

          kiriofSyncBlockDistrictWarningState();

          const $warning = kiriofEnsureBlockDistrictWarning();
          if ($warning.length) {
            jQuery("html, body").animate(
              {
                scrollTop: Math.max($warning.offset().top - 120, 0),
              },
              200,
            );
          }

          const $districtSelect = jQuery(".kiriof-block-district-select").first();
          if ($districtSelect.length) {
            $districtSelect.trigger("focus");
          }
        },
      );

    function kiriofSyncBlockDistrictWarningState() {
      const hasValidDistrict = kiriofHasValidBlockDistrict();
      const $warning = kiriofEnsureBlockDistrictWarning();
      const $shippingOptions = kiriofGetBlockShippingOptionsContainer();

      if (kiriofDistrictResultsLoading || kiriofPendingDistrictRestore) {
        $warning.hide();
        // If we already know the district is valid (e.g. from the WC cart store
        // for a logged-in user), keep shipping options visible so there is no
        // blank gap while the district select is being fetched and restored.
        if (hasValidDistrict) {
          $shippingOptions.removeClass("kiriof-shipping-options-blocked");
          jQuery("body").removeClass("kiriof-no-district");
          kiriofSetCheckoutTokenValue(true);
          kiriofSetPlaceOrderDisabled(false);
        } else {
          $shippingOptions.addClass("kiriof-shipping-options-blocked");
          jQuery("body").addClass("kiriof-no-district");
          kiriofSetCheckoutTokenValue(false);
          kiriofSetPlaceOrderDisabled(true);
        }
        return;
      }

      if (hasValidDistrict) {
        kiriofResyncSelectedBlockDistrictSource();
        jQuery(".kiriof-block-district-warning").hide();
        $shippingOptions.removeClass("kiriof-shipping-options-blocked");
        jQuery("body").removeClass("kiriof-no-district");
        kiriofSetCheckoutTokenValue(true);
        kiriofSetPlaceOrderDisabled(false);
        return;
      }

      // For non-logged-in users on a fresh form (no postcode entered yet),
      // showing "District required" immediately is bad UX — the user hasn't
      // done anything wrong. Suppress warning and blocking until they have
      // actually typed a postcode (or there is a saved one from a prior visit).
      const hasPostcode = !!(
        kiriofGetCurrentPostcodeKey() ||
        kiriofSavedCheckoutPostcode ||
        kiriofLastTypedPostcode
      );
      if (!hasPostcode) {
        $warning.hide();
        $shippingOptions.removeClass("kiriof-shipping-options-blocked");
        jQuery("body").removeClass("kiriof-no-district");
        kiriofSetCheckoutTokenValue(false);
        kiriofSetPlaceOrderDisabled(false);
        return;
      }

      kiriofClearBlockShippingMethodSelection();
      $warning.show();
      $shippingOptions.addClass("kiriof-shipping-options-blocked");
      jQuery("body").addClass("kiriof-no-district");
      kiriofSetCheckoutTokenValue(false);
      kiriofSetPlaceOrderDisabled(true);
    }

    // Call this version (with store clear) only from explicit user interactions.
    // Skips the clear when a district fetch or restore is already in progress to
    // prevent the "jank" where WC rates disappear and reappear on first page load.
    function kiriofSyncBlockDistrictWarningStateWithClear() {
      if (!kiriofDistrictResultsLoading && !kiriofPendingDistrictRestore) {
        kiriofClearBlockShippingRatesFromStore();
      }
      kiriofSyncBlockDistrictWarningState();
    }

    function kiriofResetBlockDistrictState(options) {
      options = options || {};
      const $sourceFields = kiriofGetBlockDistrictFields();
      const $select = jQuery(".kiriof-block-district-select");

      $sourceFields.val("");
      if (!options.skipCheckoutSync) {
        $sourceFields.trigger("input");
      }
      if ($select.length) {
        $select.val("");
      }

      jQuery('[name="kiriof_destination_area_name"]').val("");
      jQuery('[name="kiriof_shipping_destination_area_name"]').val("");
      kiriofEnsureLegacyBlockDistrictMirror().val("");
      kiriofSetCheckoutTokenValue(false);
      if (!options.skipCheckoutSync) {
        kiriofUpdateCheckoutAdditionalFields("");
      }
      if (!options.skipStoreSync) {
        kiriofBlockExtensionCartUpdate({
          shipping_metode_id: "",
          destination_id: 0,
          destination_name: "",
          postcode: kiriofGetCurrentPostcodeKey(),
          payment_method: kiriofGetPaymentMethod(),
          insurance: kiriofBillingAddressConfig.globalInsurance ? 1 : 0,
          force_insurance: parseInt(
            jQuery("[name=kiriof_force_insurance]").val() || "0",
          ),
        });
        kiriofRefreshBlockShippingRates();
      }
      if (!options.silentWarning) {
        kiriofSyncBlockDistrictWarningState();
      }
    }

    function kiriofRestoreSavedDistrictForCurrentPostcode() {
      const postcode = kiriofGetCurrentPostcodeKey();
      let savedDistrict = kiriofGetSavedDistrictForPostcode(postcode);

      // For logged-in users with a fresh WC session (e.g. after session expiry),
      // kiriofSavedDistrictByPostcode is empty but the district_id is preserved in
      // WC customer meta. Read it from the cart store and populate the local map
      // so subsequent calls work without hitting the store again.
      if (!savedDistrict) {
        const storedId = kiriofGetStoredDistrictIdFromWcStore();
        if (storedId) {
          let storedName = "";
          for (let ri = 0; ri < kiriofLastDistrictResults.length; ri++) {
            if (String(kiriofLastDistrictResults[ri].id) === String(storedId)) {
              storedName = kiriofLastDistrictResults[ri].text;
              break;
            }
          }
          savedDistrict = {
            destination_id: storedId,
            destination_name: storedName,
          };
          if (postcode) {
            kiriofRememberDistrictForPostcode(postcode, storedId, storedName);
          }
        }
      }

      const $select = jQuery(".kiriof-block-district-select");
      const $sourceField = kiriofGetBlockDistrictField();

      if (!savedDistrict || !$select.length) {
        kiriofPendingDistrictRestore = false;
        return;
      }

      if (
        !$select.find(
          'option[value="' + String(savedDistrict.destination_id) + '"]',
        ).length
      ) {
        kiriofPendingDistrictRestore = false;
        return;
      }

      if (
        String($select.val() || "") !== String(savedDistrict.destination_id)
      ) {
        $select.val(String(savedDistrict.destination_id));
      }

      kiriofSyncBlockDistrictSourceField(
        String(savedDistrict.destination_id),
        String(savedDistrict.destination_name || ""),
      );
      kiriofUpdateCheckoutAdditionalFields(
        String(savedDistrict.destination_id),
      );
      kiriofPendingDistrictRestore = false;
      kiriofSyncBlockDistrictWarningState();

      kiriofPersistBlockDistrictSelection({
        destination_id: parseInt(String(savedDistrict.destination_id || "0")) || 0,
        destination_name: savedDistrict.destination_name || "",
        postcode: postcode,
        payment_method: kiriofGetPaymentMethod(),
        insurance: kiriofBillingAddressConfig.globalInsurance ? 1 : 0,
        force_insurance: parseInt(
          jQuery("[name=kiriof_force_insurance]").val() || "0",
        ),
      });
    }

    jQuery(document)
      .off(
        "change.kiriofBlockDistrict",
        '[name="' + kiriofFieldId + '"], .kiriof-block-district-select',
      )
      .on(
        "change.kiriofBlockDistrict",
        '[name="' + kiriofFieldId + '"], .kiriof-block-district-select',
        function () {
          const val = jQuery(this).val();
          const label = jQuery(this).find("option:selected").text();
          const postcode = kiriofGetCurrentPostcodeKey();
          kiriofSyncBlockDistrictSourceField(val, label || "", {
            silentEvents: true,
          });
          kiriofRememberDistrictForPostcode(postcode, val, label);
          kiriofPendingDistrictRestore = false;
          kiriofDistrictResultsLoading = false;
          kiriofSyncBlockDistrictWarningState();

          kiriofPersistBlockDistrictSelection({
            destination_id: parseInt(String(val || "0")) || 0,
            destination_name: label || "",
            postcode: postcode,
            payment_method: kiriofGetPaymentMethod(),
            insurance: kiriofBillingAddressConfig.globalInsurance ? 1 : 0,
            force_insurance: parseInt(
              jQuery("[name=kiriof_force_insurance]").val() || "0",
            ),
          });
        },
      );

    // Watch for postcode changes via direct DOM events too. Some Woo
    // checkout blocks debounce address writes through /wc/store/v1/batch,
    // so the cart data store can lag behind the visible postcode input.
    jQuery(document)
      .off(
        "input.kiriofBlockPostcode change.kiriofBlockPostcode",
        kiriofBlockPostcodeSelectors,
      )
      .on(
        "input.kiriofBlockPostcode change.kiriofBlockPostcode",
        kiriofBlockPostcodeSelectors,
        function () {
          const newPostcode = kiriofNormalizePostcode(jQuery(this).val());

          // When postcode is cleared, reset all district state immediately so
          // the shipping options section is blocked until a new district is chosen.
          if (!newPostcode) {
            kiriofLastTypedPostcode = "";
            kiriofLastTypedPostcodeAt = Date.now();
            kiriofSavedCheckoutPostcode = "";
            kiriofLastPostcode = ""; // allow re-fetch when user types again
            kiriofSchedulePostcodeReapply("");
            kiriofDistrictLookupRequestId++;
            if (kiriofPostcodeLookupTimer) {
              clearTimeout(kiriofPostcodeLookupTimer);
              kiriofPostcodeLookupTimer = null;
            }
            kiriofResetBlockDistrictState({
              skipCheckoutSync: true,
              skipStoreSync: true,
            });
            kiriofSyncBlockDistrictWarningState();
            return;
          }

          kiriofLastTypedPostcode = newPostcode;
          kiriofLastTypedPostcodeAt = Date.now();
          kiriofSavedCheckoutPostcode = kiriofLastTypedPostcode;
          kiriofUpdateBlockCheckoutPostcode(kiriofLastTypedPostcode);
          kiriofSchedulePostcodeReapply(kiriofLastTypedPostcode);
          kiriofResetBlockDistrictState({
            silentWarning: newPostcode.length < 3,
            skipCheckoutSync: true,
            skipStoreSync: true,
          });
          kiriofSyncBlockDistrictWarningState();
          kiriofScheduleFetchDistricts(kiriofLastTypedPostcode);
        },
      );
    setTimeout(function () {
      kiriofRestoreSavedCheckoutState();
      kiriofFetchDistricts(kiriofGetCurrentPostcodeKey());
      kiriofSyncBlockDistrictWarningState();
    }, 300);
    // Retry for block themes (e.g. ShopVerse) that hydrate form inputs after initial render
    setTimeout(function () {
      kiriofRestoreSavedCheckoutState();
      kiriofFetchDistricts(kiriofGetCurrentPostcodeKey());
      kiriofSyncBlockDistrictWarningState();
    }, 1500);

    // Watch for postcode changes via data store — use UI-only sync (no store dispatch)
    // to avoid triggering an infinite subscribe → dispatch → subscribe loop.
    // Debounced at 150ms to reduce DOM thrashing on rapid store updates.
    let kiriofSubscribeDebounceTimer = null;
    wp.data.subscribe(function () {
      if (kiriofSubscribeDebounceTimer)
        clearTimeout(kiriofSubscribeDebounceTimer);
      kiriofSubscribeDebounceTimer = setTimeout(function () {
        try {
          const store = wp.data.select("wc/store/cart");
          if (!store) return;
          const shipping =
            store.getShippingAddress ? store.getShippingAddress() : {};
          const billing =
            store.getBillingAddress ? store.getBillingAddress() : {};
          const domPostcode = kiriofNormalizePostcode(
            kiriofGetCheckoutPostcodeFromDom(),
          );
          const recentlyTyped =
            kiriofLastTypedPostcodeAt &&
            Date.now() - kiriofLastTypedPostcodeAt < 2500;
          let postcode;
          if (domPostcode || recentlyTyped) {
            // DOM has a value or user recently typed — trust DOM + last-typed.
            postcode = domPostcode || kiriofLastTypedPostcode;
          } else {
            // DOM is empty and no recent typing — fall back to store.
            postcode = kiriofNormalizePostcode(
              (shipping && shipping.postcode) ||
                (billing && billing.postcode) ||
                kiriofLastTypedPostcode,
            );
          }

          // If every source is empty the user has cleared the postcode field.
          // Reset saved state so hasPostcode check suppresses the warning correctly.
          if (!postcode && !kiriofLastTypedPostcode) {
            kiriofSavedCheckoutPostcode = "";
          }

          if (postcode !== kiriofLastObservedBlockPostcode) {
            kiriofLastObservedBlockPostcode = postcode;
            kiriofFetchDistricts(postcode);
          }
          if (
            postcode ||
            kiriofLastTypedPostcode ||
            kiriofSavedCheckoutPostcode
          ) {
            kiriofSyncBlockDistrictWarningState();
          }
        } catch (e) {}
      }, 150);
    });

    // Logged-in compact address: when user clicks "Edit" the full form
    // expands. Re-fetch districts and re-sync warning state after it renders.
    jQuery(document).on(
      "click.kiriofAddressEdit",
      '.wc-block-components-address-card__edit, [class*="address-card__edit"], button[class*="edit"]',
      function () {
        setTimeout(function () {
          kiriofRestoreSavedCheckoutState();
          kiriofFetchDistricts(kiriofGetCurrentPostcodeKey());
          kiriofSyncBlockDistrictWarningStateWithClear();
        }, 400);
        setTimeout(function () {
          kiriofRestoreSavedCheckoutState();
          kiriofFetchDistricts(kiriofGetCurrentPostcodeKey());
          kiriofSyncBlockDistrictWarningState();
        }, 1200);
        setTimeout(function () {
          kiriofRestoreSavedCheckoutState();
          kiriofFetchDistricts(kiriofGetCurrentPostcodeKey());
          kiriofSyncBlockDistrictWarningState();
        }, 2200);
      },
    );
  }
}

// Script-scope fallback: kiriofGetCurrentPostcodeKey must be accessible from
// all script-level functions (e.g. kiriofCodInsurance). On block-checkout,
// the richer version defined inside kiriofInitBlockCheckoutCompatibility()
// shadows this via var-hoisting within that function scope, so the inner
// version is used for block-checkout code and this one for classic checkout.
function kiriofGetCurrentPostcodeKey() {
  let postcode =
    jQuery('input[name="billing_postcode"], input[name="shipping_postcode"]')
      .filter(":visible")
      .first()
      .val() || "";
  if (!postcode && typeof kiriofSavedCheckoutPostcode !== "undefined") {
    postcode = kiriofSavedCheckoutPostcode || "";
  }
  if (!postcode && typeof wp !== "undefined" && wp.data && wp.data.select) {
    try {
      const store = wp.data.select("wc/store/cart");
      const shipping =
        store && store.getShippingAddress ? store.getShippingAddress() : {};
      const billing =
        store && store.getBillingAddress ? store.getBillingAddress() : {};
      postcode =
        (shipping && shipping.postcode) || (billing && billing.postcode) || "";
    } catch (e) {}
  }
  return String(postcode).replace(/\D/g, "");
}

jQuery(document.body).on("updated_checkout", function () {
  if (kiriofTriggeredInitialShippingUpdate) {
    return false;
  }
  const different_address = jQuery(
    `[name="ship_to_different_address"]:checked`,
  ).length;
  const destination_id =
    String(different_address) === "0" ?
      jQuery("#kiriof_destination_area option:selected").val()
    : jQuery("#kiriof_shipping_destination_area option:selected").val();

  if (
    !destination_id ||
    destination_id === "undefined" ||
    String(destination_id) === "0"
  ) {
    return false;
  }

  if (jQuery("#shipping_method .shipping_method:checked").length == 0) {
    kiriofTriggeredInitialShippingUpdate = true;
    jQuery(document.body).trigger("update_checkout", {
      update_shipping_method: true,
    });
  }
});

jQuery(document.body).one("updated_checkout", function () {
  /**
   * set chosen shipping method from local storage
   * remove local storage
   */
  if (localStorage.getItem("chosen_shipping_method")) {
    const $methodInput = jQuery(
      'input[name="shipping_method[0]"][value="' +
        localStorage.getItem("chosen_shipping_method") +
        '"]',
    );
    if ($methodInput.length) {
      $methodInput.prop("checked", true);
      kiriofHandleCodInsurance();
    }
  }

  localStorage.removeItem("chosen_shipping_method");
});

function kiriofChangeCodPayment() {
  jQuery(document)
    .off(
      "change.kiriofPaymentRefresh",
      '[name="payment_method"], #kiriof_insurance, #kiriof_shipping_insurance',
    )
    .on(
      "change.kiriofPaymentRefresh",
      '[name="payment_method"], #kiriof_insurance, #kiriof_shipping_insurance',
      function () {
        if (this.name === "payment_method" && !jQuery(this).is(":checked")) {
          return;
        }
        kiriofHandleCodInsurance();
      },
    );
}

function kiriofChangeDifferentAddress() {
  jQuery(document)
    .off("change.kiriofDifferentAddress", '[name="ship_to_different_address"]')
    .on(
      "change.kiriofDifferentAddress",
      '[name="ship_to_different_address"]',
      function () {
        if (jQuery(this).is(":checked")) {
          jQuery("#kiriof_destination_area")
            .val(
              jQuery("#kiriof_shipping_destination_area option:selected").val(),
            )
            .trigger("change");
        } else {
          jQuery("#kiriof_destination_area")
            .val(jQuery("#kiriof_destination_area option:selected").val())
            .trigger("change");
        }
      },
    );
}

function kiriofGetClassicInsuranceValue() {
  return jQuery("#kiriof_insurance:checked").length ? 1 : 0;
}

function kiriofInitClassicShippingMethodSelect() {
  const select2 = jQuery.fn.selectWoo || jQuery.fn.select2;

  jQuery(".kiriof-classic-shipping-method-select").each(function () {
    const $select = jQuery(this);
    const index = String($select.data("index") || "0");
    const $checkedMethod = jQuery(
      'input.shipping_method[data-index="' + index + '"]:checked',
    ).first();

    if (
      $checkedMethod.length &&
      String($select.val() || "") !== String($checkedMethod.val() || "")
    ) {
      $select.val($checkedMethod.val());
    }

    if (!select2) {
      return;
    }

    if ($select.data("select2") || $select.data("selectWoo")) {
      select2.call($select, "destroy");
    }

    select2.call($select, {
      width: "100%",
      minimumResultsForSearch: 8,
    });

    $select.addClass("kiriof-classic-shipping-method-select--enhanced");
  });
}

function kiriofScheduleClassicShippingMethodSelectInit() {
  kiriofInitClassicShippingMethodSelect();

  jQuery.each([50, 250, 750], function (_, delay) {
    window.setTimeout(kiriofInitClassicShippingMethodSelect, delay);
  });
}

jQuery(document)
  .off(
    "init_checkout.kiriofClassicShippingMethodSelect updated_checkout.kiriofClassicShippingMethodSelect updated_shipping_method.kiriofClassicShippingMethodSelect wc_fragments_refreshed.kiriofClassicShippingMethodSelect",
  )
  .on(
    "init_checkout.kiriofClassicShippingMethodSelect updated_checkout.kiriofClassicShippingMethodSelect updated_shipping_method.kiriofClassicShippingMethodSelect wc_fragments_refreshed.kiriofClassicShippingMethodSelect",
    function () {
      kiriofScheduleClassicShippingMethodSelectInit();
    },
  );

jQuery(document)
  .off(
    "change.kiriofClassicShippingMethodSelect",
    ".kiriof-classic-shipping-method-select",
  )
  .on(
    "change.kiriofClassicShippingMethodSelect",
    ".kiriof-classic-shipping-method-select",
    function () {
      const $select = jQuery(this);
      const selectedMethod = String($select.val() || "");
      const index = String($select.data("index") || "0");
      const $method = jQuery(
        'input.shipping_method[data-index="' + index + '"]',
      )
        .filter(function () {
          return String(jQuery(this).val() || "") === selectedMethod;
        })
        .first();

      if (!$method.length) {
        return;
      }

      $method.prop("checked", true).trigger("change");
    },
  );

function kiriofHandleCodInsurance() {
  if (kiriofUpdatingCheckoutLock) {
    kiriofPendingFeeRefresh = true;
    return;
  }
  if (kiriofBillingAddressConfig.isCheckout) {
    if (kiriofHasWpBlockCheckoutContext()) {
      kiriofCodInsurance();
      return;
    }
    jQuery(document.body)
      .off("updated_checkout.kiriofFeeRefresh")
      .one("updated_checkout.kiriofFeeRefresh", function () {
        kiriofCodInsurance();
      });
    jQuery(document.body).trigger("update_checkout", {
      update_shipping_method: true,
    });
  }
}

function kiriofSetFeeSkeletonLoading(isLoading) {
  jQuery("#order_review").toggleClass("kiriof-fee-loading", !!isLoading);
}

function kiriofIsKiriminajaShippingMethod(method) {
  return (
    typeof method === "string" && method.indexOf("kiriminaja-official") === 0
  );
}

function kiriofRememberSelectedShippingMethod(method) {
  if (!kiriofIsKiriminajaShippingMethod(method)) {
    return;
  }

  kiriofPendingShippingMethod = method;
  kiriofPendingShippingMethodAt = Date.now();
  localStorage.setItem("chosen_shipping_method", method);
}

function kiriofGetPendingShippingMethod() {
  if (
    !kiriofPendingShippingMethod ||
    Date.now() - kiriofPendingShippingMethodAt > 3000
  ) {
    return "";
  }

  return kiriofPendingShippingMethod;
}

function kiriofRefreshBlockShippingRates() {
  if (typeof wp === "undefined" || !wp.data || !wp.data.dispatch) {
    return;
  }

  try {
    const cartDispatch = wp.data.dispatch("wc/store/cart");
    if (
      cartDispatch &&
      typeof cartDispatch.invalidateResolutionForStoreSelector === "function"
    ) {
      cartDispatch.invalidateResolutionForStoreSelector("getShippingRates");
    }
    if (
      cartDispatch &&
      typeof cartDispatch.invalidateResolutionForStore === "function"
    ) {
      cartDispatch.invalidateResolutionForStore();
    }
  } catch (e) {}

  try {
    const coreDataDispatch = wp.data.dispatch("core/data");
    if (
      coreDataDispatch &&
      typeof coreDataDispatch.invalidateResolution === "function"
    ) {
      coreDataDispatch.invalidateResolution(
        "wc/store/cart",
        "getShippingRates",
        [],
      );
    }
  } catch (e) {}
}

function kiriofScheduleBlockShippingRatesRefresh(delay) {
  delay = typeof delay === "number" ? delay : 120;
  if (kiriofBlockRatesRefreshTimer) {
    clearTimeout(kiriofBlockRatesRefreshTimer);
  }
  kiriofBlockRatesRefreshTimer = setTimeout(function () {
    kiriofBlockRatesRefreshTimer = null;
    kiriofRefreshBlockShippingRates();
  }, delay);
}

function kiriofIsBlockCheckoutContext() {
  return (
    jQuery(
      ".wp-block-woocommerce-checkout, .wc-block-checkout, .wc-block-components-sidebar-layout",
    ).length > 0
  );
}

function kiriofFindBlockShippingPackageId(rateId) {
  if (!rateId || typeof wp === "undefined" || !wp.data || !wp.data.select) {
    return null;
  }

  try {
    const store = wp.data.select("wc/store/cart");
    if (!store || typeof store.getShippingRates !== "function") {
      return null;
    }

    const packages = store.getShippingRates() || [];
    for (let i = 0; i < packages.length; i++) {
      const pkg = packages[i];
      const packageRates = pkg && pkg.shipping_rates ? pkg.shipping_rates : [];
      for (let j = 0; j < packageRates.length; j++) {
        if (packageRates[j] && packageRates[j].rate_id === rateId) {
          return pkg.package_id || pkg.packageId || pkg.key || i;
        }
      }
    }
  } catch (e) {}

  if (
    kiriofIsBlockCheckoutContext() &&
    jQuery('.wc-block-components-radio-control__input[value="' + rateId + '"]')
      .length
  ) {
    return 0;
  }

  return null;
}

function kiriofSelectBlockShippingRate(rateId) {
  if (
    !kiriofIsKiriminajaShippingMethod(rateId) ||
    typeof wp === "undefined" ||
    !wp.data ||
    !wp.data.dispatch
  ) {
    return;
  }

  try {
    const cartDispatch = wp.data.dispatch("wc/store/cart");
    const packageId = kiriofFindBlockShippingPackageId(rateId);

    if (cartDispatch && typeof cartDispatch.selectShippingRate === "function") {
      if (packageId !== null && typeof packageId !== "undefined") {
        cartDispatch.selectShippingRate(rateId, packageId);
      } else {
        cartDispatch.selectShippingRate(rateId);
      }
    } else if (
      cartDispatch &&
      typeof cartDispatch.setSelectedShippingRate === "function"
    ) {
      cartDispatch.setSelectedShippingRate(rateId);
    }
  } catch (e) {}
}

function kiriofScheduleBlockCartDataRefresh(refreshKey, delay) {
  delay = typeof delay === "number" ? delay : 240;

  if (
    refreshKey &&
    refreshKey === kiriofLastBlockCartRefreshKey &&
    Date.now() - kiriofLastBlockCartRefreshAt < 1200
  ) {
    return;
  }

  if (kiriofBlockCartRefreshTimer) {
    clearTimeout(kiriofBlockCartRefreshTimer);
  }

  kiriofBlockCartRefreshTimer = setTimeout(function () {
    kiriofBlockCartRefreshTimer = null;
    kiriofLastBlockCartRefreshKey = refreshKey || "";
    kiriofLastBlockCartRefreshAt = Date.now();
    kiriofRefreshBlockCartData();
  }, delay);
}

function kiriofRefreshBlockCartData() {
  if (typeof wp !== "undefined" && wp.data && wp.data.dispatch) {
    try {
      const cartDispatch = wp.data.dispatch("wc/store/cart");
      if (
        cartDispatch &&
        typeof cartDispatch.invalidateResolutionForStoreSelector === "function"
      ) {
        cartDispatch.invalidateResolutionForStoreSelector("getCartData");
        cartDispatch.invalidateResolutionForStoreSelector("getCartTotals");
      }
    } catch (e) {}

    try {
      const coreDataDispatch = wp.data.dispatch("core/data");
      if (
        coreDataDispatch &&
        typeof coreDataDispatch.invalidateResolution === "function"
      ) {
        coreDataDispatch.invalidateResolution(
          "wc/store/cart",
          "getCartData",
          [],
        );
        coreDataDispatch.invalidateResolution(
          "wc/store/cart",
          "getCartTotals",
          [],
        );
      }
    } catch (e) {}
  }
}

function kiriofRefreshBlockPaymentMethodsData() {
  if (typeof wp === "undefined" || !wp.data || !wp.data.dispatch) {
    return;
  }

  const selectors = [
    "getPaymentMethods",
    "getAvailablePaymentMethods",
    "getActivePaymentMethod",
    "getPaymentMethodData",
  ];

  try {
    const paymentDispatch = wp.data.dispatch("wc/store/payment");
    if (
      paymentDispatch &&
      typeof paymentDispatch.invalidateResolutionForStoreSelector === "function"
    ) {
      selectors.forEach(function (selector) {
        paymentDispatch.invalidateResolutionForStoreSelector(selector);
      });
    }
  } catch (e) {}

  try {
    const coreDataDispatch = wp.data.dispatch("core/data");
    if (
      coreDataDispatch &&
      typeof coreDataDispatch.invalidateResolution === "function"
    ) {
      selectors.forEach(function (selector) {
        coreDataDispatch.invalidateResolution("wc/store/payment", selector, []);
      });
    }
  } catch (e) {}
}

if (!jQuery("#kiriof-fee-skeleton-style").length) {
  jQuery("head").append(
    '<style id="kiriof-fee-skeleton-style">#order_review.kiriof-fee-loading .shop_table{opacity:.65;position:relative}#order_review.kiriof-fee-loading .shop_table:after{content:"";position:absolute;inset:0;pointer-events:none;background:linear-gradient(90deg,rgba(255,255,255,0) 0%,rgba(255,255,255,.35) 50%,rgba(255,255,255,0) 100%);animation:kiriofFeeSkeletonShimmer 1.2s ease-in-out infinite}@keyframes kiriofFeeSkeletonShimmer{0%{transform:translateX(-100%)}100%{transform:translateX(100%)}}</style>',
  );
}

function kiriofBlockExtensionCartUpdate(data) {
  if (typeof wp === "undefined" || !wp.data || !wp.data.dispatch) {
    if (window.console)
      console.warn("[KiriminAja] wp.data.dispatch not available");
    return null;
  }

  try {
    const cartDispatch = wp.data.dispatch("wc/store/cart");
    if (
      cartDispatch &&
      typeof cartDispatch.extensionCartUpdate === "function"
    ) {
      if (window.console)
        console.log("[KiriminAja] Calling extensionCartUpdate", data);
      const result = cartDispatch.extensionCartUpdate({
        namespace: "kiriminaja-official",
        data: {
          shipping_metode_id: data.shipping_metode_id,
          destination_id: data.destination_id,
          destination_name: data.destination_name,
          postcode: data.postcode,
          payment_method: data.payment_method,
          insurance: data.insurance,
          force_insurance: data.force_insurance,
        },
      });
      return result;
    }

    if (
      cartDispatch &&
      typeof cartDispatch.invalidateResolutionForStore === "function"
    ) {
      cartDispatch.invalidateResolutionForStore();
    }
  } catch (e) {
    if (window.console)
      console.error("[KiriminAja] extensionCartUpdate error:", e);
  }

  return null;
}

function kiriofGetDestinationId(different_address) {
  const blockDestinationSource = jQuery(
    '[name="kiriminaja-official/kiriof_destination_area"], input[name*="kiriof_destination_area"], textarea[name*="kiriof_destination_area"]',
  )
    .not("select, .kiriof-block-district-select")
    .first();
  const blockDestinationId =
    jQuery(".kiriof-block-district-select").val() ||
    blockDestinationSource.val();

  if (blockDestinationId) {
    return blockDestinationId;
  }

  return different_address == "0" ?
      jQuery("#kiriof_destination_area option:selected").val() ||
        jQuery('[name="kiriof_destination_area"]').val()
    : jQuery("#kiriof_shipping_destination_area option:selected").val() ||
        jQuery('[name="kiriof_shipping_destination_area"]').val();
}

function kiriofRememberPendingPaymentMethod(paymentMethod) {
  paymentMethod = kiriofNormalizePaymentMethod(paymentMethod);
  if (!paymentMethod) {
    return;
  }

  kiriofPendingPaymentMethod = paymentMethod;
  kiriofPendingPaymentMethodAt = Date.now();
}

function kiriofGetPendingPaymentMethod() {
  if (
    !kiriofPendingPaymentMethod ||
    Date.now() - kiriofPendingPaymentMethodAt > 2500
  ) {
    return "";
  }

  return kiriofPendingPaymentMethod;
}

function kiriofGetPaymentMethodFromElement(element) {
  const $element = jQuery(element);
  let paymentMethod = kiriofNormalizePaymentMethod(
    $element.val() ||
      $element.attr("value") ||
      $element.data("paymentMethod") ||
      $element.attr("data-payment-method") ||
      "",
  );

  if (paymentMethod) {
    return paymentMethod;
  }

  const $input = $element
    .find(
      'input[name="payment_method"], input[name="radio-control-wc-payment-method-options"], input[type="radio"][value]',
    )
    .addBack(
      'input[name="payment_method"], input[name="radio-control-wc-payment-method-options"], input[type="radio"][value]',
    )
    .first();

  paymentMethod = kiriofNormalizePaymentMethod(
    $input.val() || $input.attr("value") || "",
  );
  if (paymentMethod) {
    return paymentMethod;
  }

  const identifiers = [
    $element.attr("id") || "",
    $element.attr("for") || "",
    $element.attr("aria-labelledby") || "",
  ].join(" ");
  const match = identifiers.match(
    /(?:payment_method_|payment-method-options[-_]|wc-payment-method-options[-_])([a-z0-9_-]+)/i,
  );

  return match ? kiriofNormalizePaymentMethod(match[1]) : "";
}

function kiriofGetPaymentMethod() {
  let payment_method = kiriofNormalizePaymentMethod(
    jQuery("[name=payment_method]:checked").val() || "",
  );

  if (!payment_method) {
    payment_method = kiriofGetPendingPaymentMethod();
  }

  if (
    !payment_method &&
    typeof wp !== "undefined" &&
    wp.data &&
    wp.data.select
  ) {
    try {
      const paymentStore = wp.data.select("wc/store/payment");
      if (
        paymentStore &&
        typeof paymentStore.getActivePaymentMethod === "function"
      ) {
        payment_method = kiriofNormalizePaymentMethod(
          paymentStore.getActivePaymentMethod(),
        );
      }
      if (
        !payment_method &&
        paymentStore &&
        paymentStore.getActivePaymentMethod
      ) {
        payment_method = kiriofNormalizePaymentMethod(
          paymentStore.getActivePaymentMethod,
        );
      }
      if (
        !payment_method &&
        paymentStore &&
        typeof paymentStore.getPaymentMethodData === "function"
      ) {
        const paymentData = paymentStore.getPaymentMethodData() || {};
        payment_method = kiriofNormalizePaymentMethod(
          paymentData.payment_method ||
            paymentData.paymentMethod ||
            paymentData.gateway ||
            "",
        );
      }
    } catch (e) {}
  }

  if (!payment_method) {
    const $codInput = jQuery('[name=payment_method][value="cod"]');
    const $checkedCodInput = $codInput.filter(":checked");
    const $activeCodWrapper = $codInput.closest(
      '[aria-checked="true"], .is-active, .wc-block-components-radio-control-accordion-option--checked',
    );
    if ($checkedCodInput.length || $activeCodWrapper.length) {
      payment_method = "cod";
    }
  }

  return payment_method || "";
}

function kiriofGetSelectedBlockShippingMethod() {
  if (typeof wp === "undefined" || !wp.data || !wp.data.select) {
    return "";
  }

  try {
    const cartStore = wp.data.select("wc/store/cart");
    const rates =
      cartStore && typeof cartStore.getShippingRates === "function" ?
        cartStore.getShippingRates()
      : [];

    if (!rates || !rates.length) {
      return "";
    }

    for (let i = 0; i < rates.length; i++) {
      const pkg = rates[i];
      const packageRates = pkg && pkg.shipping_rates ? pkg.shipping_rates : [];
      for (let j = 0; j < packageRates.length; j++) {
        if (packageRates[j] && packageRates[j].selected) {
          return packageRates[j].rate_id || packageRates[j].id || "";
        }
      }
    }
  } catch (e) {}

  return "";
}

function kiriofBuildFeeRefreshKey(data) {
  return [
    data.shipping_metode_id || "",
    data.destination_id || "",
    data.destination_name || "",
    data.postcode || "",
    data.payment_method || "",
    data.insurance || 0,
    data.force_insurance || 0,
  ].join("|");
}

function kiriofCodInsurance() {
  const different_address = jQuery(
    `[name="ship_to_different_address"]:checked`,
  ).length;

  // Read shipping method: traditional, block radio, or block data store
  let shipping_metode_id =
    kiriofGetPendingShippingMethod() ||
    (kiriofIsBlockCheckoutContext() ?
      kiriofGetSelectedBlockShippingMethod()
    : "");
  shipping_metode_id =
    shipping_metode_id ||
    jQuery("#shipping_method .shipping_method:checked").val() ||
    jQuery(".wc-block-components-radio-control__input:checked").val();
  shipping_metode_id = shipping_metode_id || "";

  const destination_id = kiriofGetDestinationId(different_address);
  const destination_name =
    jQuery(".kiriof-block-district-select option:selected").text() ||
    jQuery('[name="kiriof_destination_area_name"]').val() ||
    jQuery('[name="kiriof_shipping_destination_area_name"]').val() ||
    "";

  // Global insurance forced = always true
  const insurance =
    kiriofBillingAddressConfig.globalInsurance ? 1
    : kiriofIsBlockCheckoutContext() ? 0
    : String(different_address) === "0" ? kiriofGetClassicInsuranceValue()
    : kiriofGetClassicInsuranceValue();

  const payment_method = kiriofGetPaymentMethod();

  const data = {
    action: "kiriof_get_data_after_update_checkout",
    nonce:
      typeof kiriofAjax !== "undefined" && kiriofAjax.update_checkout_nonce ?
        kiriofAjax.update_checkout_nonce
      : kiriofBillingAddressConfig.updateCheckoutNonce || "",
    shipping_metode_id:
      typeof shipping_metode_id === "undefined" ? "" : shipping_metode_id,
    destination_id,
    destination_name,
    postcode: kiriofGetCurrentPostcodeKey(),
    payment_method,
    insurance: typeof insurance === "undefined" ? 0 : parseInt(String(insurance)),
    force_insurance: parseInt(
      jQuery("[name=kiriof_force_insurance]").val() || "0",
    ),
  };

  const refreshKey = kiriofBuildFeeRefreshKey(data);
  const isBlockCheckout = kiriofIsBlockCheckoutContext();

  if (
    isBlockCheckout &&
    refreshKey === kiriofLastCompletedFeeRefreshKey &&
    Date.now() - kiriofLastCompletedFeeRefreshAt < 1200
  ) {
    return;
  }

  if (kiriofUpdatingCheckoutLock) {
    if (refreshKey === kiriofInFlightFeeRefreshKey) {
      return;
    }

    kiriofPendingFeeRefresh = true;
    kiriofPendingFeeRefreshKey = refreshKey;

    if (kiriofFeeRefreshRequest && kiriofFeeRefreshRequest.readyState !== 4) {
      try {
        kiriofFeeRefreshRequest.abort();
      } catch (e) {}
    }

    return;
  }

  kiriofUpdatingCheckoutLock = true;
  kiriofInFlightFeeRefreshKey = refreshKey;
  kiriofPendingFeeRefresh = false;
  kiriofPendingFeeRefreshKey = "";

  if (isBlockCheckout) {
    const blockResult = kiriofBlockExtensionCartUpdate(data);
    const completeBlockRefresh = function () {
      kiriofUpdatingCheckoutLock = false;
      kiriofInFlightFeeRefreshKey = "";
      kiriofLastCompletedFeeRefreshKey = refreshKey;
      kiriofLastCompletedFeeRefreshAt = Date.now();

      kiriofScheduleBlockShippingRatesRefresh(180);
      kiriofScheduleBlockCartDataRefresh(refreshKey, 260);
      kiriofRefreshBlockPaymentMethodsData();

      if (
        kiriofPendingFeeRefresh &&
        kiriofPendingFeeRefreshKey &&
        kiriofPendingFeeRefreshKey !== refreshKey
      ) {
        kiriofPendingFeeRefresh = false;
        kiriofPendingFeeRefreshKey = "";
        window.setTimeout(kiriofCodInsurance, 150);
      } else {
        kiriofPendingFeeRefresh = false;
        kiriofPendingFeeRefreshKey = "";
      }
    };

    if (blockResult && typeof blockResult.then === "function") {
      blockResult.then(completeBlockRefresh).catch(completeBlockRefresh);
    } else {
      completeBlockRefresh();
    }
    return;
  }

  kiriofFeeRefreshRequest = jQuery.ajax({
    url:
      typeof kiriofAjax !== "undefined" && kiriofAjax.ajaxurl ?
        kiriofAjax.ajaxurl
      : kiriofBillingAddressConfig.ajaxUrl || "",
    type: "post",
    data: data,
    dataType: "JSON",
    dataFilter: function (raw) {
      return kiriofExtractJsonResponseText(raw);
    },
    beforeSend: function () {
      jQuery("#order_review").find(".shop_table").block({ message: null });
      kiriofSetFeeSkeletonLoading(true);
    },
    success: function (response) {
      jQuery("[name=kiriof_force_insurance]").val(
        response?.data?.force_insurance,
      );
      if (!kiriofIsBlockCheckoutContext()) {
        jQuery(document.body).trigger("update_checkout", {
          update_shipping_method: false,
        });
      }

      // Block checkout: the React sidebar (order summary) reads from the
      // Store API, not from classic checkout fragments. After the server
      // session is updated, tell WC blocks to re-fetch the cart so the
      // selected shipping method and fees appear in the summary sidebar.
      if (kiriofIsBlockCheckoutContext()) {
        kiriofScheduleBlockShippingRatesRefresh(80);
        kiriofScheduleBlockCartDataRefresh(refreshKey, 120);
      }
    },
    error: function (xhr, textStatus) {
      if (textStatus === "abort") {
        return;
      }
      if (window.console) {
        console.warn("[KiriminAja] Checkout fee refresh AJAX failed", {
          status: xhr.status,
          textStatus: textStatus,
        });
      }
      if (String(xhr.status) !== "200") {
        alert("Sorry System Trouble Error Code : " + xhr.status);
      }
    },
    complete: function () {
      kiriofSetFeeSkeletonLoading(false);
      jQuery("#order_review").find(".shop_table").unblock();
      kiriofUpdatingCheckoutLock = false;
      kiriofFeeRefreshRequest = null;
      kiriofInFlightFeeRefreshKey = "";
      kiriofLastCompletedFeeRefreshKey = refreshKey;
      kiriofLastCompletedFeeRefreshAt = Date.now();

      if (kiriofPendingFeeRefresh) {
        const pendingKey = kiriofPendingFeeRefreshKey;
        kiriofPendingFeeRefresh = false;
        kiriofPendingFeeRefreshKey = "";

        if (!pendingKey || pendingKey !== refreshKey) {
          window.setTimeout(kiriofCodInsurance, 150);
        }
      }
    },
  });
}
