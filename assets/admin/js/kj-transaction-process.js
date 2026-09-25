/* global jQuery, kiriofAjax, kiriofAjaxRoute */
(function ($, config) {
  "use strict";

  // Heartbeat nonce auto-refresh
  $(document).on("heartbeat-send", function (e, data) {
    data.kiriof_nonce_check = true;
  });
  $(document).on("heartbeat-tick", function (e, data) {
    if (data.kiriof_new_nonce) {
      kiriofAjax.nonce = data.kiriof_new_nonce;
    }
  });

  // Cache jQuery selectors
  let orderIds = [];
  const $checkAllTop = $("#check_order_id_all_top");
  const $checkAllBottom = $("#check_order_id_all_bottom");
  const $transactionCheckboxes = () => $('[name="transaction_id[]"]');
  const $requestPickupBtn = $("#kj-request-pickup-btn");
  const $printBtn = $("#kj-print-btn");
  const i18n = config.i18n || {};
  const pinCache = config.pinCache || {};
  const urls = config.urls || {};
  const kjRequestPickupLabel = i18n.requestPickup || "Request Pickup";
  const kjPrintLabel = i18n.print || "Print";
  const kjPickScheduleLabel = i18n.pickSchedule || "Pick Schedule";
  const kjConfirmPinLabel = i18n.confirmPin || "Confirm PIN";
  const kjValidateLabel = i18n.validate || "Validate";
  const kjPinCacheConfig = {
    key: pinCache.key || "",
    ttl: pinCache.ttl || 0,
    userHash: pinCache.userHash || "",
    siteHash: pinCache.siteHash || "",
    rememberLabel: i18n.pinRemembered || "",
    expiredLabel: i18n.pinExpired || "",
    invalidatedLabel: i18n.pinInvalidated || "",
    unsupportedLabel: i18n.pinUnsupported || "",
  };
  const kjUpdateRequestPickupCount = () => {
    const pickupCount = $transactionCheckboxes().filter(
      ':checked:not(:disabled)[data-can-pickup="1"]',
    ).length;
    const printCount = $transactionCheckboxes().filter(
      ':checked:not(:disabled)[data-can-print="1"]',
    ).length;
    $requestPickupBtn.text(
      pickupCount > 0
        ? `${kjRequestPickupLabel} (${pickupCount})`
        : kjRequestPickupLabel,
    );
    $printBtn.text(
      printCount > 0 ? `${kjPrintLabel} (${printCount})` : kjPrintLabel,
    );
  };

  // Make functions globally accessible
  const kiriofApplySearch = function (key, value) {
    if ($(`#table-form [name="${key}"]`).length > 0) {
      $(`#table-form [name="${key}"]`).val(value);
    }
    // Clear search when switching status tabs
    if (key === "status" && value) {
      $(`#table-form [name="key"]`).val("");
      $("#kiriof-search-input").val("");
    }
    $(`#table-form [name="cpage"]`).val("1");
    $(`#table-form`).trigger("submit");
  };

  const kiriofSubmitFilters = function () {
    $(`#table-form [name="month"]`).val(
      document.getElementById("month_search_1").value,
    );
    $(`#table-form [name="cod"]`).val(
      document.getElementById("cod_search_1").value,
    );
    $(`#table-form [name="courier"]`).val(
      document.getElementById("courier_search_1").value,
    );
    $(`#table-form [name="print_status"]`).val(
      document.getElementById("print_status_search_1").value,
    );
    $(`#table-form [name="cpage"]`).val("1");
    $(`#table-form`).trigger("submit");
  };

  const kiriofSubmitFiltersBottom = function () {
    document.getElementById("month_search_1").value =
      document.getElementById("month_search_2").value;
    document.getElementById("cod_search_1").value =
      document.getElementById("cod_search_2").value;
    document.getElementById("courier_search_1").value =
      document.getElementById("courier_search_2").value;
    document.getElementById("print_status_search_1").value =
      document.getElementById("print_status_search_2").value;
    $(`#table-form [name="month"]`).val(
      document.getElementById("month_search_2").value,
    );
    $(`#table-form [name="cod"]`).val(
      document.getElementById("cod_search_2").value,
    );
    $(`#table-form [name="courier"]`).val(
      document.getElementById("courier_search_2").value,
    );
    $(`#table-form [name="print_status"]`).val(
      document.getElementById("print_status_search_2").value,
    );
    $(`#table-form [name="cpage"]`).val("1");
    $(`#table-form`).trigger("submit");
  };

  const kiriofGoToPage = function (page) {
    $(`#table-form [name="cpage"]`).val(page);
    $(`#table-form`).trigger("submit");
  };

  $(document).on(
    "change",
    "#check_order_id_all_top, #check_order_id_all_bottom",
    function () {
      const is_checked = $(this).prop("checked");
      $checkAllTop.prop("checked", is_checked);
      $checkAllBottom.prop("checked", is_checked);
      // Skip disabled rows (e.g. On Hold / Pending Payment) so they
      // can never be batched into a pickup request via "select all".
      $transactionCheckboxes().not(":disabled").prop("checked", is_checked);
      kjUpdateRequestPickupCount();
    },
  );

  $(document).on("change", '[name="transaction_id[]"]', function () {
    kjUpdateRequestPickupCount();
  });

  $(document).on("keypress", ".current-page", function (e) {
    if (e.which === 13) {
      e.preventDefault();
      var $this = $(this);
      var page = parseInt($this.val(), 10) || 1;
      var max = parseInt(
        $this
          .closest(".tablenav-pages")
          .find(".total-pages")
          .text()
          .replace(/,/g, ""),
        10,
      );
      if (page >= 1 && page <= max) {
        kiriofGoToPage(page);
      }
    }
  });

  var kiriofSearchTimer;
  $(document).on("keyup", "#kiriof-search-input", function () {
    clearTimeout(kiriofSearchTimer);
    var $input = $(this);
    kiriofSearchTimer = setTimeout(function () {
      kiriofApplySearch("key", $input.val());
    }, 400);
  });

  function kiriofGetRequestPickupModal() {
    return $(".kiriof-request-pickup-modal");
  }

  function kiriofGetCancelModal() {
    return $(".kiriof-cancel-transaction-modal");
  }

  function kiriofSetModalState($modal, state) {
    $modal.find(".kiriof-modal-state").hide();
    $modal.find(".err_msg").hide().text("");
    $modal.data("kiriofStep", state);

    if (state === "loading") {
      $modal.find(".kiriof-modal-state-loading").show();
    } else if (state === "error") {
      $modal.find(".kiriof-modal-state-error").show();
    } else if (state === "pin") {
      $modal.find(".kiriof-modal-state-pin").show();
      $modal.find("#btn-next").text(kjValidateLabel);
      kjPrepareCreditPinStep($modal);
    } else {
      $modal.find(".kiriof-modal-state-content").show();
    }
  }

  function kiriofUpdateCancelReasonCounter() {
    const $modal = kiriofGetCancelModal();
    $modal
      .find(".kiriof-cancel-reason-count")
      .text(($modal.find(".kiriof-cancel-reason").val() || "").length);
  }

  function kjEnsurePinInputReady($modal) {
    const widget = $modal.find("#kiriof-pin-widget").get(0);
    const $fallback = $modal.find("#kiriof-pin-fallback");
    const hasRenderedWidget = !!(
      widget &&
      widget.shadowRoot &&
      widget.shadowRoot.childNodes.length > 0
    );

    if (hasRenderedWidget) {
      $modal.find("#kiriof-pin-widget").show();
      $fallback.hide();
      return "widget";
    }

    $modal.find("#kiriof-pin-widget").hide();
    $fallback.show();
    return "fallback";
  }

  function kjFocusPinInput($modal) {
    const mode = kjEnsurePinInputReady($modal);
    if ("widget" === mode) {
      $modal.find("#kiriof-pin-widget").trigger("focus");
      return;
    }

    $modal.find("#kiriof-pin-fallback").trigger("focus");
  }

  function kjSetPinValue($modal, value) {
    const normalizedValue = (value || "")
      .toString()
      .replace(/\D/g, "")
      .substring(0, 6);
    $modal.find("#kiriof-pin-input").val(normalizedValue);
    $modal.find("#kiriof-pin-fallback").val(normalizedValue);
    $modal.find("#kiriof-pin-widget").attr("value", normalizedValue);
  }

  function kjGetPinValue($modal) {
    return ($modal.find("#kiriof-pin-input").val() || "").toString();
  }

  function kjResetPinRetryState($modal) {
    const timer = $modal.data("kiriofPinCooldownTimer");
    if (timer) {
      clearInterval(timer);
    }
    $modal.data("kiriofPinAttempts", 0);
    $modal.data("kiriofPinMaxAttempts", 3);
    $modal.data("kiriofPinLockUntil", "");
    $modal.data("kiriofPinCooldownTimer", null);
  }

  function kjGetPinRememberCheckbox($modal) {
    return $modal.find("#kiriof-pin-remember");
  }

  function kjGetPinCacheNotice($modal) {
    return $modal.find(".kiriof-pin-cache-notice");
  }

  function kjSetPinCacheNotice($modal, message, tone) {
    const $notice = kjGetPinCacheNotice($modal);
    if (!message) {
      $notice.hide().text("").removeAttr("data-tone");
      return;
    }

    $notice
      .attr("data-tone", tone || "info")
      .text(message)
      .show();
  }

  function kjCanUsePinCache() {
    return (
      window.isSecureContext &&
      window.crypto &&
      window.crypto.subtle &&
      window.localStorage
    );
  }

  async function kjGetPinCacheKeyMaterial() {
    const rawKey = await window.crypto.subtle.digest(
      "SHA-256",
      new TextEncoder().encode(
        `${kjPinCacheConfig.userHash}:${kjPinCacheConfig.siteHash}`,
      ),
    );
    return window.crypto.subtle.importKey("raw", rawKey, "AES-GCM", false, [
      "encrypt",
      "decrypt",
    ]);
  }

  function kjGetPinCacheRecord() {
    if (!kjCanUsePinCache()) {
      return null;
    }

    try {
      const rawValue = window.localStorage.getItem(kjPinCacheConfig.key);
      if (!rawValue) {
        return null;
      }

      const parsedValue = JSON.parse(rawValue);
      if (
        !parsedValue ||
        parsedValue.userHash !== kjPinCacheConfig.userHash ||
        parsedValue.siteHash !== kjPinCacheConfig.siteHash
      ) {
        window.localStorage.removeItem(kjPinCacheConfig.key);
        return null;
      }

      if (!parsedValue.expiresAt || Date.now() >= parsedValue.expiresAt) {
        window.localStorage.removeItem(kjPinCacheConfig.key);
        return { expired: true };
      }

      return parsedValue;
    } catch (error) {
      window.localStorage.removeItem(kjPinCacheConfig.key);
      return null;
    }
  }

  async function kjDecryptCachedPin(record) {
    if (!record || !record.ciphertext || !record.iv) {
      return "";
    }

    const key = await kjGetPinCacheKeyMaterial();
    const iv = Uint8Array.from(atob(record.iv), (character) =>
      character.charCodeAt(0),
    );
    const ciphertext = Uint8Array.from(atob(record.ciphertext), (character) =>
      character.charCodeAt(0),
    );
    const decrypted = await window.crypto.subtle.decrypt(
      { name: "AES-GCM", iv },
      key,
      ciphertext,
    );
    return new TextDecoder()
      .decode(decrypted)
      .replace(/\D/g, "")
      .substring(0, 6);
  }

  async function kjPersistCachedPin($modal, pin) {
    if (!kjCanUsePinCache()) {
      kjSetPinCacheNotice($modal, kjPinCacheConfig.unsupportedLabel, "warning");
      return false;
    }

    const rememberPin = kjGetPinRememberCheckbox($modal).is(":checked");
    if (!rememberPin || !pin || pin.length !== 6) {
      return false;
    }

    const key = await kjGetPinCacheKeyMaterial();
    const iv = window.crypto.getRandomValues(new Uint8Array(12));
    const encrypted = await window.crypto.subtle.encrypt(
      { name: "AES-GCM", iv },
      key,
      new TextEncoder().encode(pin),
    );
    const payload = {
      userHash: kjPinCacheConfig.userHash,
      siteHash: kjPinCacheConfig.siteHash,
      expiresAt: Date.now() + kjPinCacheConfig.ttl * 1000,
      iv: btoa(String.fromCharCode(...iv)),
      ciphertext: btoa(String.fromCharCode(...new Uint8Array(encrypted))),
    };
    window.localStorage.setItem(kjPinCacheConfig.key, JSON.stringify(payload));
    kjSetPinCacheNotice($modal, kjPinCacheConfig.rememberLabel, "success");
    return true;
  }

  function kjClearCachedPin($modal, reason) {
    if (window.localStorage) {
      window.localStorage.removeItem(kjPinCacheConfig.key);
    }
    kjGetPinRememberCheckbox($modal).prop("checked", false);
    if (reason === "expired") {
      kjSetPinCacheNotice($modal, kjPinCacheConfig.expiredLabel, "warning");
    } else if (reason === "invalid") {
      kjSetPinCacheNotice($modal, kjPinCacheConfig.invalidatedLabel, "warning");
    } else if (!reason) {
      kjSetPinCacheNotice($modal, "", "info");
    }
  }

  async function kjRestoreCachedPin($modal) {
    if (!kjCanUsePinCache()) {
      return false;
    }

    const record = kjGetPinCacheRecord();
    if (!record) {
      return false;
    }

    if (record.expired) {
      kjClearCachedPin($modal, "expired");
      return false;
    }

    try {
      const pin = await kjDecryptCachedPin(record);
      if (!pin || pin.length !== 6) {
        kjClearCachedPin($modal, "invalid");
        return false;
      }

      kjSetPinValue($modal, pin);
      kjGetPinRememberCheckbox($modal).prop("checked", true);
      kjSetPinCacheNotice($modal, kjPinCacheConfig.rememberLabel, "success");
      return true;
    } catch (error) {
      kjClearCachedPin($modal, "invalid");
      return false;
    }
  }

  function kjPrepareCreditPinStep($modal) {
    kjEnsurePinInputReady($modal);
    if (!kjCanUsePinCache()) {
      kjSetPinCacheNotice($modal, kjPinCacheConfig.unsupportedLabel, "warning");
    }
    window.setTimeout(function () {
      void kjRestoreCachedPin($modal).finally(function () {
        kjFocusPinInput($modal);
        kjUpdatePickupButton($modal);
      });
    }, 0);
  }

  function kjNormalizePinErrorData($modal, data) {
    const normalized = Object.assign({}, data || {});
    const error = normalized.error || "";
    const maxAttempts = Math.max(
      parseInt(
        normalized.max_attempt || $modal.data("kiriofPinMaxAttempts") || 3,
        10,
      ) || 3,
      1,
    );
    const localAttempts =
      parseInt($modal.data("kiriofPinAttempts") || 0, 10) || 0;
    let attempts = parseInt(normalized.attempt || 0, 10) || 0;

    if ("PIN_INVALID" === error || normalized.valid === false) {
      attempts = Math.max(attempts, localAttempts + 1);
    }

    if ("PIN_MAX_ATTEMPT_REACHED" === error) {
      attempts = Math.max(attempts, maxAttempts);
    }

    normalized.attempt = Math.min(attempts, maxAttempts);
    normalized.max_attempt = maxAttempts;

    let lockUntil = normalized.lock_until || "";
    if (!lockUntil && normalized.attempt >= maxAttempts) {
      lockUntil = new Date(Date.now() + 60 * 60 * 1000).toISOString();
      normalized.error = "PIN_MAX_ATTEMPT_REACHED";
    }

    normalized.lock_until = lockUntil;
    $modal.data("kiriofPinAttempts", normalized.attempt);
    $modal.data("kiriofPinMaxAttempts", normalized.max_attempt);
    $modal.data("kiriofPinLockUntil", lockUntil);

    return normalized;
  }

  const kjRequestPickupSchedule = function () {
    orderIds = [];
    $(
      'input[name="transaction_id[]"][data-can-pickup="1"]:checked:not(:disabled)',
    ).each(function () {
      orderIds.push($(this).val());
    });

    if (orderIds.length === 0) {
      alert(i18n.noSelectedTransaction);
      return;
    }

    $(document.body).WCBackboneModal({
      template: "kiriof-modal-request-pickup",
      variable: {},
    });

    const $modal = kiriofGetRequestPickupModal();
    kjResetPinRetryState($modal);
    kjSetPinValue($modal, "");
    kjGetPinRememberCheckbox($modal).prop("checked", false);
    kjSetPinCacheNotice($modal, "", "info");
    kiriofSetModalState($modal, "loading");
    $modal.find("#btn-next").prop("disabled", true);

    $.ajax({
      type: "post",
      url: kiriofAjaxRoute(),
      data: {
        action: "kiriof_request_pickup_schedule",
        data: {
          order_ids: orderIds,
          nonce: kiriofAjax.nonce,
        },
      },
      complete: function (response) {
        const resp = JSON.parse(response.responseText).data;

        if (resp?.status !== 200) {
          kiriofSetModalState($modal, "error");
          $modal
            .find(".kiriof-backbone-modal-error-text")
            .text(resp?.message ?? i18n.genericError);
          return;
        }

        if (resp?.data?.pickup_number) {
          const pickupNumber = encodeURIComponent(
            resp?.data?.pickup_number || "",
          );
          const redirectBase = `${urls.pickup}&pickup_number=${pickupNumber}`;
          const shouldOpenPayment =
            resp?.data?.open_payment === true ||
            resp?.data?.open_payment === 1 ||
            resp?.data?.open_payment === "1";
          window.location.href = shouldOpenPayment
            ? `${redirectBase}&open_payment=1`
            : redirectBase;
          return;
        }

        const schedules = resp?.data?.schedules ?? [];
        const transaction_summary = resp?.data?.transaction_summary ?? {};
        const sum_cod_fee = transaction_summary?.sum_fee_cod ?? 0;
        const sum_non_cod_fee = transaction_summary?.sum_fee_non_cod ?? 0;
        const count_non_cod = parseInt(
          transaction_summary?.count_non_cod ?? 0,
          10,
        );
        const hasNonCodFee = parseInt(sum_non_cod_fee || 0, 10) > 0;
        const total_fee =
          parseInt(sum_cod_fee || 0, 10) + parseInt(sum_non_cod_fee || 0, 10);

        $modal
          .find(".kiriof-summary-cod")
          .text(
            `Rp${kiriofMoneyFormat(transaction_summary?.sum_fee_cod ?? 0)}`,
          );
        $modal
          .find(".kiriof-summary-non-cod")
          .text(
            `Rp${kiriofMoneyFormat(transaction_summary?.sum_fee_non_cod ?? 0)}`,
          );
        $modal
          .find(".kiriof-summary-total")
          .text(`Rp${kiriofMoneyFormat(total_fee)}`);

        const $scheduleSelect = $modal.find(".kiriof-schedule-select");
        $scheduleSelect.find("option:not(:first)").remove();
        $.each(schedules, function (idx, schedule) {
          $scheduleSelect.append(
            $("<option>", { value: schedule?.clock, text: schedule?.label }),
          );
        });

        kiriofSetModalState($modal, "content");
        $modal.data("kiriofPaymentConfigLoaded", false);
        $modal.data("kiriofPaymentRequired", true);
        $modal.data("kiriofCountNonCod", count_non_cod);
        $modal.data("kiriofHasNonCodFee", hasNonCodFee);
        $modal.find("#btn-next").prop("disabled", true);
        if (schedules.length === 0) {
          $modal
            .find(".err_msg")
            .text("*" + i18n.noSchedule)
            .show();
        }

        kjLoadPaymentMethodConfig($modal, parseInt(total_fee || 0, 10));
      },
    });
  };

  function kjLoadPaymentMethodConfig($modal, totalFee) {
    $.ajax({
      type: "post",
      url: kiriofAjaxRoute(),
      data: {
        action: "kiriof_get_payment_method_config",
        nonce: kiriofAjax.nonce,
      },
      complete: function (response) {
        const resp = JSON.parse(response.responseText).data;
        if (resp?.status !== 200) {
          return;
        }

        const isTop = resp?.data?.is_top === true;
        const hasPin = resp?.data?.has_pin === true;
        const isKaCreditEnabled = resp?.data?.ka_credit_enabled === true;
        const countNonCod = parseInt($modal.data("kiriofCountNonCod") || 0, 10);
        const hasNonCodFee = $modal.data("kiriofHasNonCodFee") === true;
        const $stateBanner = $modal.find(".kiriof-pm-state-banner");

        $stateBanner.hide().text("");

        if (countNonCod <= 0 || !hasNonCodFee) {
          $modal.find(".kiriof-payment-method-section").hide();
          $modal.data("kiriofPaymentConfigLoaded", true);
          $modal.data("kiriofPaymentRequired", false);
          $stateBanner.text(i18n.codOnlyNoPayment).show();
          kjUpdatePickupButton($modal);
          return;
        }

        if (isTop) {
          $modal.find(".kiriof-payment-method-section").hide();
          $modal.data("kiriofPaymentConfigLoaded", true);
          $modal.data("kiriofPaymentRequired", false);
          $stateBanner.text(i18n.topNoPayment).show();
          kjUpdatePickupButton($modal);
          return;
        }

        $modal.find(".kiriof-payment-method-section").show();
        $modal.data("kiriofPaymentConfigLoaded", true);
        $modal.data("kiriofPaymentRequired", true);

        const $creditOpt = $modal.find(
          '.kiriof-payment-method-option[data-method="credit"]',
        );
        const $creditWarning = $modal.find(".kiriof-pm-credit-warning");
        const $balanceLabel = $modal.find(".kiriof-pm-balance");

        if (!isKaCreditEnabled) {
          $creditOpt.hide();
        } else if (!hasPin) {
          $creditOpt.addClass("kiriof-pm-disabled");
          $modal.find("#kiriof-pm-credit").prop("disabled", true);
          $creditWarning
            .html(
              i18n.pinNotConfigured +
                ' <a href="https://app.kiriminaja.com/settings/profile?tab=keamanan&action=pin" target="_blank">' +
                i18n.configurePin +
                "</a>",
            )
            .show();
        }

        if (!isKaCreditEnabled) {
          kjUpdatePickupButton($modal);
          return;
        }

        $.ajax({
          type: "post",
          url: kiriofAjaxRoute(),
          data: {
            action: "kiriof_get_credit_balance",
            nonce: kiriofAjax.nonce,
          },
          complete: function (balanceResp) {
            const bResp = JSON.parse(balanceResp.responseText).data;
            const balance = bResp?.data?.balance ?? 0;
            $balanceLabel.text(kiriofMoneyFormat(balance));

            if (balance < totalFee) {
              $creditOpt.addClass("kiriof-pm-disabled");
              $modal.find("#kiriof-pm-credit").prop("disabled", true);
              if (hasPin) {
                $creditWarning
                  .html(
                    i18n.insufficientCredit +
                      ' <a href="https://app.kiriminaja.com/credit/top-up" target="_blank">' +
                      i18n.topUpNow +
                      "</a>",
                  )
                  .show();
              }
            } else if (hasPin) {
              $creditWarning.hide();
            }

            if (totalFee > 10000000) {
              $modal
                .find('.kiriof-payment-method-option[data-method="qris"]')
                .addClass("kiriof-pm-disabled");
              $modal.find("#kiriof-pm-qris").prop("disabled", true);
            }

            kjUpdatePickupButton($modal);
          },
        });

        kjUpdatePickupButton($modal);
      },
    });

    $modal.on("change", 'input[name="payment_method"]', function () {
      const method = $(this).val();
      $modal.data("kiriofSelectedPaymentMethod", method || "");
      if (method !== "credit") {
        kjSetPinValue($modal, "");
        kjResetPinRetryState($modal);
        $modal.find("#kiriof-pin-widget").removeAttr("invalid");
        $modal.find(".kiriof-pin-error").hide().text("");
        kjSetPinCacheNotice($modal, "", "info");
      } else {
        void kjRestoreCachedPin($modal).finally(function () {
          kjUpdatePickupButton($modal);
        });
      }
      kjUpdatePickupButton($modal);
    });

    $modal.on("click", ".kiriof-payment-method-option", function (event) {
      if ($(this).hasClass("kiriof-pm-disabled") || $(event.target).is("a")) {
        return;
      }

      const $input = $(this).find(
        'input[name="payment_method"]:not(:disabled)',
      );
      if ($input.length) {
        $input.prop("checked", true).trigger("change");
      }
    });

    $modal.on(
      "pin-change pin-complete",
      "#kiriof-pin-widget",
      function (event) {
        const value = (event.originalEvent?.detail?.value || "")
          .replace(/\D/g, "")
          .substring(0, 6);
        kjSetPinValue($modal, value);
        $(this).removeAttr("invalid");
        $modal.find(".kiriof-pin-error").hide().text("");
        kjUpdatePickupButton($modal);
      },
    );

    $modal.on("input", "#kiriof-pin-fallback", function () {
      kjSetPinValue($modal, $(this).val());
      $modal.find("#kiriof-pin-widget").removeAttr("invalid");
      $modal.find(".kiriof-pin-error").hide().text("");
      kjUpdatePickupButton($modal);
    });

    $modal.on("change", 'select[name="schedule_opt"]', function () {
      kjUpdatePickupButton($modal);
    });
  }

  const kjPrintBulk = function () {
    const selectedOrderIds = [];
    $(
      'input[name="transaction_id[]"][data-can-print="1"]:checked:not(:disabled)',
    ).each(function () {
      selectedOrderIds.push($(this).val());
    });

    if (selectedOrderIds.length === 0) {
      alert(i18n.noPrintSelection);
      return;
    }

    const $form = $("#kiriof-print-bulk-form");
    $form.find('input[name="oids[]"]').remove();
    selectedOrderIds.forEach(function (orderId) {
      $("<input>", { type: "hidden", name: "oids[]", value: orderId }).appendTo(
        $form,
      );
    });
    $form.trigger("submit");
  };

  function kjUpdatePickupButton($modal) {
    const step = $modal.data("kiriofStep") || "content";
    const paymentConfigLoaded =
      $modal.data("kiriofPaymentConfigLoaded") === true;
    const paymentRequired = $modal.data("kiriofPaymentRequired") === true;
    const method = $modal
      .find('input[name="payment_method"]:checked:not(:disabled)')
      .val();
    const scheduleSelected = !!$modal.find('select[name="schedule_opt"]').val();
    const pin = kjGetPinValue($modal);

    if (step === "pin") {
      $modal
        .find("#btn-next")
        .text(kjValidateLabel)
        .prop("disabled", !pin || pin.length !== 6);
      return;
    }

    let enabled = scheduleSelected && paymentConfigLoaded;
    if (paymentRequired) {
      if (!method) {
        enabled = false;
      }
    }
    $modal
      .find("#btn-next")
      .text(method === "credit" ? kjConfirmPinLabel : kjPickScheduleLabel)
      .prop("disabled", !enabled);
  }

  function kjFormatPinLockCountdown(lockUntil) {
    if (!lockUntil) {
      return "";
    }

    const remainingSeconds = Math.max(
      0,
      Math.floor((new Date(lockUntil).getTime() - Date.now()) / 1000),
    );
    const minutes = Math.floor(remainingSeconds / 60);
    const seconds = remainingSeconds % 60;
    return `${minutes}m ${seconds}s`;
  }

  function kjRenderPinLockError($modal, lockUntil, message) {
    const cooldown = kjFormatPinLockCountdown(lockUntil);
    const description = cooldown
      ? `${i18n.pinWait} <span class="kiriof-pin-cooldown">${cooldown}</span> ${i18n.toTryAgain}`
      : message || i18n.pinLocked;

    $modal
      .find(".kiriof-pin-error")
      .attr("data-tone", "critical")
      .html(
        "<strong>" +
          i18n.tooManyAttempts +
          "</strong><br>" +
          description,
      )
      .show();
  }

  function kjStartPinCooldown($modal, lockUntil, message) {
    const existingTimer = $modal.data("kiriofPinCooldownTimer");
    if (existingTimer) {
      clearInterval(existingTimer);
    }

    $modal.find("#kiriof-pin-widget, #kiriof-pin-fallback").hide();
    $modal.find("#btn-next").text(i18n.back).prop("disabled", false);
    kjRenderPinLockError($modal, lockUntil, message);

    const timer = setInterval(function () {
      const remainingSeconds = Math.max(
        0,
        Math.floor((new Date(lockUntil).getTime() - Date.now()) / 1000),
      );
      if (remainingSeconds <= 0) {
        clearInterval(timer);
        kjResetPinRetryState($modal);
        $modal
          .find(".kiriof-pin-error")
          .hide()
          .text("")
          .removeAttr("data-tone");
        kjFocusPinInput($modal);
        kjUpdatePickupButton($modal);
        return;
      }
      kjRenderPinLockError($modal, lockUntil, message);
    }, 1000);

    $modal.data("kiriofPinCooldownTimer", timer);
  }

  function kjShowPinError($modal, data, message) {
    const normalizedData = kjNormalizePinErrorData($modal, data);
    const error = normalizedData?.error || "";
    if (error === "PIN_MAX_ATTEMPT_REACHED") {
      kjStartPinCooldown($modal, normalizedData?.lock_until, message);
      $modal.find("#kiriof-pin-widget").attr("invalid", "");
      return;
    }

    const attempt = parseInt(normalizedData?.attempt || 0, 10);
    const maxAttempt = parseInt(normalizedData?.max_attempt || 0, 10);
    const remaining = Math.max(0, maxAttempt - attempt);
    const retryText =
      attempt > 0 && maxAttempt > 0
        ? '<div class="kiriof-pin-retry-text">' +
          i18n.pinRemainingPrefix +
          " <strong>" +
          remaining +
          "</strong> " +
          i18n.pinRemainingSuffix +
          "</div>"
        : "";

    $modal
      .find(".kiriof-pin-error")
      .attr("data-tone", "critical")
      .html(
        "<strong>" +
          i18n.incorrectPin +
          "</strong><br>" +
          (message || i18n.checkPin) +
          retryText,
      )
      .show();
    $modal.find("#kiriof-pin-widget").attr("invalid", "");
  }

  function kjSubmitPickup(
    $modal,
    ids,
    schedule,
    paymentMethod,
    pin,
    closeModal,
  ) {
    const $errMsg = $modal.find(".err_msg");
    $.ajax({
      type: "post",
      url: kiriofAjaxRoute(),
      data: {
        action: "kiriof_request_pickup_transaction",
        data: {
          schedule: schedule,
          order_ids: ids,
          payment_method: paymentMethod,
          pin: pin,
          nonce: kiriofAjax.nonce,
        },
      },
      complete: function (response) {
        const resp = JSON.parse(response.responseText).data;
        if (resp?.status !== 200) {
          const errCode = resp?.data?.error_code;
          if (errCode === "BALANCE_NOT_ENOUGH") {
            kiriofSetModalState($modal, "content");
            $errMsg.text("*" + i18n.insufficientBalance).show();
          } else if (
            errCode === "PIN_INVALID" ||
            errCode === "PIN_MAX_ATTEMPT_REACHED"
          ) {
            kjClearCachedPin($modal, "invalid");
            kiriofSetModalState($modal, "pin");
            kjShowPinError($modal, resp?.data || {}, resp?.message || errCode);
          } else {
            kiriofSetModalState($modal, "content");
            $errMsg
              .text("*" + (resp?.message || i18n.somethingWrong))
              .show();
          }
          kjUpdatePickupButton($modal);
          return;
        }

        closeModal();
        const pickupNumber = encodeURIComponent(
          resp?.data?.pickup_number || "",
        );
        const shouldOpenPayment =
          resp?.data?.open_payment === true ||
          resp?.data?.open_payment === 1 ||
          resp?.data?.open_payment === "1";
        const redirectBase = `${urls.pickup}&pickup_number=${pickupNumber}`;
        window.location.href = shouldOpenPayment
          ? `${redirectBase}&open_payment=1`
          : redirectBase;
      },
    });
  }

  const kjShowCancelModal = function (orderId) {
    $(document.body).WCBackboneModal({
      template: "kiriof-modal-cancel-transaction",
      variable: {
        order_id: orderId,
      },
    });
    kiriofUpdateCancelReasonCounter();
  };

  $(document).on("input", ".kiriof-cancel-reason", function () {
    kiriofUpdateCancelReasonCounter();
  });

  $(document).on("click", "[data-kj-action]", function (event) {
    const $target = $(this);
    const action = $target.data("kjAction");
    if (action === "request-pickup") {
      kjRequestPickupSchedule();
    }
    if (action === "print-bulk") {
      kjPrintBulk();
    }
    if (action === "cod-adjust") {
      window.kjShowCodAdjustModal(this);
    }
    if (action === "cancel-deficit") {
      window.kjShowCancelDeficitModal(this);
    }
    if (action === "cancel") {
      kjShowCancelModal($target.data("orderId"));
    }
    if (
      action === "request-pickup" ||
      action === "print-bulk" ||
      action === "cod-adjust" ||
      action === "cancel-deficit" ||
      action === "cancel"
    ) {
      event.preventDefault();
    }
  });

  $(document).on("click", "[data-search-key], [data-page]", function (event) {
    event.preventDefault();
    const $target = $(this);
    if ($target.is("[data-search-key]")) {
      kiriofApplySearch($target.data("searchKey"), $target.data("searchValue"));
    } else {
      kiriofGoToPage(parseInt($target.data("page"), 10) || 1);
    }
  });

  $(document).on("submit", "[data-kj-search-form]", function (event) {
    event.preventDefault();
  });
  $(document).on("change", "[data-kj-search-by]", function () {
    if ($("#kiriof-search-input").val().trim()) {
      kiriofApplySearch("search_by", this.value);
    }
  });
  $(document).on("click", "[data-kj-filter-submit]", function () {
    const suffix = $(this).data("kjFilterSubmit");
    if (suffix === "_2") {
      kiriofSubmitFiltersBottom();
    } else {
      kiriofSubmitFilters();
    }
  });

  $(document.body).on(
    "wc_backbone_modal_next_response",
    function (event, target, data, closeModal) {
      if (target === "kiriof-modal-request-pickup") {
        const $modal = kiriofGetRequestPickupModal();
        const $errMsg = $modal.find(".err_msg");
        const step = $modal.data("kiriofStep") || "content";
        const schedule = data?.schedule_opt;
        const paymentMethod =
          $modal.find('input[name="payment_method"]:checked').val() ||
          $modal.data("kiriofSelectedPaymentMethod") ||
          "";
        const pin = kjGetPinValue($modal);

        if (!schedule) {
          $errMsg.text("*" + i18n.selectSchedule).show();
          return;
        }

        if ($modal.data("kiriofPaymentRequired") === true && !paymentMethod) {
          $errMsg.text("*" + i18n.selectPayment).show();
          return;
        }

        if (paymentMethod === "credit" && step !== "pin") {
          kiriofSetModalState($modal, "pin");
          kjUpdatePickupButton($modal);
          return;
        }

        if (
          paymentMethod === "credit" &&
          step === "pin" &&
          $modal.data("kiriofPinLockUntil")
        ) {
          kiriofSetModalState($modal, "content");
          kjUpdatePickupButton($modal);
          return;
        }

        if (paymentMethod === "credit" && (!pin || pin.length !== 6)) {
          $modal
            .find(".kiriof-pin-error")
            .text("*" + i18n.sixDigitPin)
            .show();
          kjUpdatePickupButton($modal);
          return;
        }

        $errMsg.hide().text("");
        $modal.find(".kiriof-pin-error").hide().text("");
        kiriofSetModalState($modal, "loading");
        $modal.find("#btn-next").prop("disabled", true);

        if (paymentMethod === "credit") {
          $.ajax({
            type: "post",
            url: kiriofAjaxRoute(),
            data: {
              action: "kiriof_validate_pin",
              nonce: kiriofAjax.nonce,
              pin: pin,
            },
            complete: function (pinResp) {
              const pinData = JSON.parse(pinResp.responseText).data;
              if (pinData?.status !== 200) {
                kjClearCachedPin($modal, "invalid");
                kiriofSetModalState($modal, "pin");
                const pinErr = pinData?.data;
                if (pinErr?.error === "PIN_MAX_ATTEMPT_REACHED") {
                  kjShowPinError(
                    $modal,
                    pinErr || {},
                    pinData?.message || i18n.pinMaxAttempts,
                  );
                  $modal.find("#kiriof-pm-credit").prop("disabled", true);
                } else {
                  kjShowPinError(
                    $modal,
                    pinErr || {},
                    pinData?.message || i18n.incorrectPinPeriod,
                  );
                }
                kjUpdatePickupButton($modal);
                return;
              }
              kjResetPinRetryState($modal);
              void kjPersistCachedPin($modal, pin).finally(function () {
                kjSubmitPickup(
                  $modal,
                  orderIds,
                  schedule,
                  paymentMethod,
                  pin,
                  closeModal,
                );
              });
            },
          });
          return;
        }

        kjSubmitPickup(
          $modal,
          orderIds,
          schedule,
          paymentMethod,
          pin,
          closeModal,
        );
        return;
      }

      if (target === "kiriof-modal-cancel-transaction") {
        const $modal = kiriofGetCancelModal();
        const $errMsg = $modal.find(".err_msg");
        const $loader = $modal.find(".kiriof-modal-state-loading");
        const reason = (data?.reason || "").trim();
        const orderId = data?.order_id || "";

        if (reason.length < 5) {
          $errMsg.text(i18n.reasonMin).show();
          return;
        }
        if (reason.length > 200) {
          $errMsg.text(i18n.reasonMax).show();
          return;
        }

        if (!confirm(i18n.confirmCancel)) {
          return;
        }

        $errMsg.hide().text("");
        $loader.show();
        $modal.find("form").css("opacity", 0.45);
        $modal.find("#btn-next").prop("disabled", true);

        $.ajax({
          type: "post",
          url: kiriofAjaxRoute(),
          data: {
            action: "kiriof_cancel_transaction",
            data: {
              order_id: orderId,
              reason: reason,
              nonce: kiriofAjax.nonce,
            },
          },
          complete: function (response) {
            const resp = JSON.parse(response.responseText).data;

            if (resp?.status !== 200) {
              $loader.hide();
              $modal.find("form").css("opacity", 1);
              $modal.find("#btn-next").prop("disabled", false);
              $errMsg
                .text("*" + (resp?.message ?? i18n.errorOccurred))
                .show();
              return;
            }

            closeModal();
            alert(resp?.message ?? i18n.cancelSuccess);
            window.location.reload();
          },
        });
      }
    },
  );
})(jQuery, window.kiriofTransactionProcess || {});
