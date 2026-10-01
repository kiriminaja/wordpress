(function ($) {
  "use strict";

  var config = window.kiriofSettings || {};
  var i18n = config.i18n || {};
  var ajaxurl =
    config.ajaxurl ||
    (window.kiriofAjax && kiriofAjax.ajaxurl) ||
    window.ajaxurl;

  function nonce() {
    return config.nonce || (window.kiriofAjax && kiriofAjax.nonce) || "";
  }

  function route() {
    return typeof window.kiriofAjaxRoute === "function"
      ? kiriofAjaxRoute()
      : ajaxurl;
  }

  function parse(response) {
    if (response && typeof response === "object" && !response.responseText) {
      response = response.data !== undefined ? response.data : response;
      return response && typeof response === "object"
        ? response
        : { status: 0, message: i18n.unexpectedResponse };
    }
    if (typeof window.kiriofParseAjaxResponse === "function") {
      return window.kiriofParseAjaxResponse(response);
    }
    try {
      var body = JSON.parse(response.responseText);
      body = body && body.data !== undefined ? body.data : body;
      return body && typeof body === "object"
        ? body
        : { status: 0, message: i18n.unexpectedResponse };
    } catch (e) {
      return { status: 0, message: i18n.invalidResponse };
    }
  }

  $(document).on("heartbeat-send", function (event, data) {
    data.kiriof_nonce_check = true;
  });
  $(document).on("heartbeat-tick", function (event, data) {
    if (data.kiriof_new_nonce) {
      config.nonce = data.kiriof_new_nonce;
      if (window.kiriofAjax) {
        kiriofAjax.nonce = data.kiriof_new_nonce;
      }
    }
  });

  function saveToggle(action, values, element) {
    element.prop("disabled", true);
    $.ajax({
      type: "post",
      url: route(),
      data: { action: action, data: $.extend({ nonce: nonce() }, values) },
    })
      .fail(function () {
        element.prop("checked", !element.is(":checked"));
      })
      .always(function (response) {
        element.prop("disabled", false);
        var result = parse(response);
        if (!result || result.status !== 200) {
          element.prop("checked", !element.is(":checked"));
        }
      });
  }

  var $cod = $("#kiriof_cod_toggle");
  var $insurance = $("#kiriof_insurance_toggle");
  if ($cod.length || $insurance.length) {
    $cod.on("change", function () {
      saveToggle(
        "kiriof_store_config_data",
        { enable_cod: this.checked ? "yes" : "no" },
        $(this),
      );
    });
    $insurance.on("change", function () {
      saveToggle(
        "kiriof_store_insurance_data",
        { enable_insurance: this.checked ? "yes" : "no" },
        $(this),
      );
    });
  }

  $("#kiriof-setup-key-connect").on("click", function () {
    var $button = $(this),
      key = $.trim($("#kiriof-setup-key-input").val()),
      $message = $("#kiriof-connect-msg");
    if (!key) {
      $message.show().css("color", "#d63638").text(i18n.enterSetupKey);
      return;
    }
    $button.prop("disabled", true).text(i18n.connecting);
    $message.hide();
    $.ajax({
      type: "post",
      url: route(),
      data: {
        action: "kiriof_store_integration_data",
        data: { setup_key: key, nonce: nonce() },
      },
    }).always(function (response) {
      var result = parse(response);
      if (result && result.status === 200) {
        window.location.reload();
        return;
      }
      $button.prop("disabled", false).text(i18n.connect);
      $message
        .show()
        .css("color", "#d63638")
        .text(
          result && result.message ? result.message : i18n.connectionFailed,
        );
    });
  });
  $("body").on("click", ".kj-disconnect", function () {
    if (!window.confirm(i18n.disconnectConfirm)) {
      return;
    }
    $.ajax({
      type: "post",
      url: route(),
      data: {
        action: "kiriof_disconnect_integration",
        data: { nonce: nonce() },
      },
    })
      .fail(function () {
        window.alert(i18n.networkError);
      })
      .always(function (response) {
        var result = parse(response);
        if (result && result.status === 200) {
          window.location.reload();
          return;
        }
        if (result && result.message) {
          window.alert(result.message);
        } else {
          window.alert(i18n.disconnectFailed);
        }
      });
  });

  var $courierList = $("#kiriof-courier-list");
  if ($courierList.length) {
    var $status = $(".kj-courier-status"),
      couriers = [],
      enabled = {};
    function escapeHtml(value) {
      return $("<div>")
        .text(value || "")
        .html();
    }
    function escapeAttr(value) {
      return escapeHtml(value).replace(/"/g, "&quot;");
    }
    function updateStatus() {
      $status.text(
        (i18n.courierCount || "%1$s of %2$s enabled")
          .replace("%1$s", Object.keys(enabled).length)
          .replace("%2$s", couriers.length),
      );
    }
    function render() {
      var html = "";
      $.each(couriers, function (_, courier) {
        var active = Object.prototype.hasOwnProperty.call(
          enabled,
          courier.code,
        );
        html +=
          '<div class="kj-courier-item"><div class="kj-courier-item-info"><div class="kj-courier-item-name">' +
          escapeHtml(courier.name) +
          '</div><div class="kj-courier-item-type">' +
          escapeHtml(courier.type) +
          '</div></div><label class="kj-ios-toggle"><input type="checkbox" class="kj-courier-toggle" data-code="' +
          escapeAttr(courier.code) +
          '" data-name="' +
          escapeAttr(courier.name) +
          '" ' +
          (active ? "checked" : "") +
          '><span class="kj-ios-toggle-track"><span class="kj-ios-toggle-thumb"></span></span></label></div>';
      });
      $courierList.html(
        html ||
          '<div style="padding:1rem;color:#787c82">' +
            escapeHtml(i18n.noCouriers) +
            "</div>",
      );
      updateStatus();
    }
    function error(message) {
      $courierList.html(
        '<div class="notice notice-error inline" style="margin:0"><p>' +
          escapeHtml(message || i18n.courierLoadFailed) +
          "</p></div>",
      );
      $status.text("");
    }
    function save() {
      var ids = Object.keys(enabled);
      return $.ajax({
        type: "post",
        url: route(),
        data: {
          action: "kiriof_store_courier_whitelist",
          data: {
            whitelist_ids: ids.join(","),
            whitelist_names: $.map(ids, function (id) {
              return enabled[id];
            }).join(","),
            nonce: nonce(),
          },
        },
      });
    }
    $.post(route(), {
      action: "kiriof_get_courier_whitelist",
      data: { nonce: nonce() },
    }).always(function (response) {
      var result = parse(response);
      if (!result || result.status !== 200 || !result.data) {
        error(result && result.message);
        return;
      }
      couriers = result.data.couriers || [];
      $.each(result.data.whitelist_ids || [], function (_, id) {
        enabled[id] = true;
      });
      $.each(couriers, function (_, courier) {
        if (enabled[courier.code]) {
          enabled[courier.code] = courier.name;
        }
      });
      render();
    });
    $courierList.on("change", ".kj-courier-toggle", function () {
      var $toggle = $(this),
        old = $.extend({}, enabled),
        code = String($toggle.data("code"));
      if ($toggle.is(":checked")) {
        enabled[code] = String($toggle.data("name"));
      } else {
        delete enabled[code];
      }
      updateStatus();
      save().fail(function (response) {
        enabled = old;
        render();
        $status
          .css("color", "#b32d2e")
          .text(parse(response).message || i18n.courierSaveFailed);
      });
    });
    $(".kj-courier-enable-all").on("click", function () {
      var old = $.extend({}, enabled);
      $.each(couriers, function (_, c) {
        enabled[c.code] = c.name;
      });
      render();
      save().fail(function (r) {
        enabled = old;
        render();
        $status
          .css("color", "#b32d2e")
          .text(parse(r).message || i18n.courierSaveFailed);
      });
    });
    $(".kj-courier-disable-all").on("click", function () {
      var old = $.extend({}, enabled);
      enabled = {};
      render();
      save().fail(function (r) {
        enabled = old;
        render();
        $status
          .css("color", "#b32d2e")
          .text(parse(r).message || i18n.courierSaveFailed);
      });
    });
  }

  var $webhookButton = $(".kj-detail .kj-submit-btn");
  $webhookButton.on("click", function () {
    var $button = $(this);
    $button.prop("disabled", true);
    $.ajax({
      type: "post",
      url: route(),
      data: {
        action: "kiriof_store_call_back_data",
        data: {
          callback_url: $('[name="callback_url"]').val(),
          nonce: nonce(),
        },
      },
    }).always(function (response) {
      var result = parse(response);
      $button.prop("disabled", false);
      window.alert(
        result && result.status === 200
          ? i18n.saved
          : result && result.message
            ? result.message
            : i18n.saveFailed,
      );
    });
  });

  var $setupForm = $("#setup-form");
  $setupForm.find(".kj-submit").on("click", function () {
    $setupForm.find(".alert").addClass("kj-hidden");
    $setupForm.find(".kj-btn-group").addClass("kj-hidden");
    $setupForm.find(".kj-loader").removeClass("kj-hidden");
    $.post(route(), {
      action: "kiriof_store_integration_data",
      data: {
        setup_key: $setupForm.find('[name="setup_key"]').val(),
        nonce: nonce(),
      },
    }).always(function (response) {
      var result = parse(response);
      if (result && result.status === 200) {
        window.location.reload();
        return;
      }
      $setupForm
        .find(".alert")
        .removeClass("kj-hidden")
        .find(".msg")
        .text(
          result && result.message ? result.message : i18n.connectionFailed,
        );
      $setupForm.find(".kj-btn-group").removeClass("kj-hidden");
      $setupForm.find(".kj-loader").addClass("kj-hidden");
    });
  });

  function formatDate(date) {
    function pad(value) {
      return String(value).padStart(2, "0");
    }
    return (
      date.getFullYear() +
      "-" +
      pad(date.getMonth() + 1) +
      "-" +
      pad(date.getDate()) +
      " " +
      pad(date.getHours()) +
      ":" +
      pad(date.getMinutes()) +
      ":" +
      pad(date.getSeconds())
    );
  }
  if ($("#kiriof-revalidate-btn").length) {
    var $refresh = $("#kiriof-revalidate-btn"),
      $refreshMsg = $("#kiriof-revalidate-msg"),
      timer;
    function poll() {
      $.get(ajaxurl, {
        action: "kiriof_get_coupon_region_status",
        nonce: nonce(),
      }).done(function (response) {
        var data = response && response.data ? response.data : {},
          state = data.status ? data.status.state : "";
        if (state === "ready" || state === "error") {
          clearInterval(timer);
          $refresh.prop("disabled", false).text(i18n.revalidate);
          if (state === "ready") {
            $refreshMsg.css("color", "#00a32a").text(i18n.cacheUpdated);
            $("#kiriof-cache-provinces").text(data.province_count || 0);
            $("#kiriof-cache-cities").text(data.city_count || 0);
            $("#kiriof-cache-updated").text(
              data.status.last_completed_at || formatDate(new Date()),
            );
            $("#kiriof-cache-valid-until").text(i18n.manualRefreshOnly);
            $("#kiriof-cache-state").html(
              '<span style="display:inline-block;padding:2px 10px;border-radius:999px;background:#00a32a;color:#fff;font-size:12px;font-weight:600">Ready</span>',
            );
          } else {
            $refreshMsg
              .css("color", "#d63638")
              .text(data.status.last_error || i18n.revalidateFailed);
          }
        } else {
          $refreshMsg.css("color", "#646970").text(i18n.refreshing);
        }
      });
    }
    $refresh.on("click", function () {
      $refresh.prop("disabled", true).text(i18n.scheduling);
      $refreshMsg.text("");
      $.post(ajaxurl, {
        action: "kiriof_refresh_coupon_regions",
        nonce: nonce(),
      })
        .done(function () {
          $refreshMsg.text(i18n.refreshing);
          timer = setInterval(poll, 3000);
        })
        .fail(function () {
          $refresh.prop("disabled", false).text(i18n.revalidate);
          $refreshMsg.css("color", "#d63638").text(i18n.requestFailed);
        });
    });
  }
  var $flush = $("#kiriof-flush-couriers-btn");
  if ($flush.length) {
    $flush.on("click", function () {
      var $button = $(this),
        $message = $("#kiriof-flush-couriers-msg");
      $button.prop("disabled", true).text(i18n.flushing);
      $.post(ajaxurl, { action: "kiriof_flush_couriers_cache", nonce: nonce() })
        .done(function (response) {
          $button.prop("disabled", false).text(i18n.flushCouriers);
          if (response && response.success) {
            var count =
                response.data && response.data.count ? response.data.count : 0,
              tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            $message
              .css("color", "#00a32a")
              .text(
                i18n.cacheRefreshed + " (" + count + " " + i18n.couriers + ")",
              );
            $("#kiriof-couriers-cache-count").text(count);
            $("#kiriof-couriers-cache-updated").text(formatDate(new Date()));
            $("#kiriof-couriers-cache-valid-until").text(formatDate(tomorrow));
            $("#kiriof-couriers-cache-state").html(
              '<span style="display:inline-block;padding:2px 10px;border-radius:999px;background:#00a32a;color:#fff;font-size:12px;font-weight:600">' +
                i18n.cached +
                "</span>",
            );
          } else {
            $message
              .css("color", "#d63638")
              .text(
                response && response.data && response.data.message
                  ? response.data.message
                  : i18n.flushFailed,
              );
          }
        })
        .fail(function () {
          $button.prop("disabled", false).text(i18n.flushCouriers);
          $message.css("color", "#d63638").text(i18n.requestFailed);
        });
    });
  }

  function extractPostcode(item) {
    if (!item || typeof item !== "object") {
      return "";
    }
    var sources = [
      item,
      item.data || {},
      item.attributes || {},
      item.raw || {},
    ];
    var keys = [
      "postcode",
      "postal_code",
      "zipcode",
      "zip_code",
      "kode_pos",
      "kodepos",
      "postalCode",
    ];
    for (var sourceIndex = 0; sourceIndex < sources.length; sourceIndex++) {
      for (var keyIndex = 0; keyIndex < keys.length; keyIndex++) {
        if (sources[sourceIndex][keys[keyIndex]]) {
          return String(sources[sourceIndex][keys[keyIndex]])
            .replace(/\s+/g, "")
            .trim();
        }
      }
    }
    var match = String(item.text || item.name || item.label || "").match(
      /\b\d{5}\b/,
    );
    return match ? match[0] : "";
  }

  var select2 = $.fn.selectWoo || $.fn.select2;
  var $wcArea = $("#kiriof_wc_origin_area");
  if ($wcArea.length && select2) {
    var $country = $("#woocommerce_default_country"),
      $areaRow = $wcArea.closest("tr");
    if ($wcArea.data("select2") || $wcArea.data("selectWoo")) {
      select2.call($wcArea, "destroy");
    }
    select2.call($wcArea, {
      width: "350px",
      minimumInputLength: 3,
      placeholder: i18n.selectOption,
      allowClear: true,
      ajax: {
        url: ajaxurl,
        dataType: "json",
        type: "POST",
        delay: 250,
        data: function (params) {
          return {
            data: params,
            nonce: nonce(),
            action: "kiriminaja_subdistrict_search",
          };
        },
        processResults: function (response) {
          return {
            results: $.map(response.data || [], function (item) {
              return {
                text: item.text,
                id: item.id,
                postcode: extractPostcode(item),
              };
            }),
          };
        },
        cache: true,
      },
    });
    $wcArea.on("select2:select", function (event) {
      var selected = event.params && event.params.data ? event.params.data : {},
        postcode = extractPostcode(selected),
        label = selected.text || $wcArea.find("option:selected").text() || "";
      if (postcode) {
        $('#woocommerce_store_postcode, [name="woocommerce_store_postcode"]')
          .val(postcode)
          .trigger("input")
          .trigger("change");
      }
      $("#kiriof_wc_origin_area_name").val(label);
      $wcArea.find("option:selected").text(label);
    });
    $wcArea.on("select2:clear", function () {
      $("#kiriof_wc_origin_area_name").val("");
    });
    function toggleArea() {
      var value = String($country.val() || "");
      if (!$country.length || value === "ID" || value.indexOf("ID:") === 0) {
        $areaRow.show();
      } else {
        $areaRow.hide();
        $wcArea.val(null).trigger("change");
        $("#kiriof_wc_origin_area_name").val("");
      }
    }
    $country.on("change", toggleArea);
    toggleArea();
  }

  var mapElement = document.getElementById("kiriof-wc-origin-map");
  if (mapElement && window.L) {
    var $lat = $("#kiriof_wc_origin_latitude"),
      $lng = $("#kiriof_wc_origin_longitude"),
      $coords = $("#kiriof-wc-map-coords"),
      $mapError = $("#kiriof-wc-map-error");
    var defaultLat = parseFloat($lat.val()) || -6.2088,
      defaultLng = parseFloat($lng.val()) || 106.8456;
    var originMap = L.map(mapElement).setView([defaultLat, defaultLng], 15);
    L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
    }).addTo(originMap);
    function showMapError(message) {
      $mapError.text(message).show();
      setTimeout(function () {
        $mapError.fadeOut();
      }, 5000);
    }
    function updateCoordinates(lat, lng) {
      if (
        isNaN(lat) ||
        isNaN(lng) ||
        lat < -90 ||
        lat > 90 ||
        lng < -180 ||
        lng > 180
      ) {
        showMapError(i18n.invalidCoordinates);
        return;
      }
      $lat.val(lat.toFixed(7));
      $lng.val(lng.toFixed(7));
      $coords.attr("data-tip", lat.toFixed(7) + ", " + lng.toFixed(7));
      $mapError.hide();
    }
    originMap.on("moveend", function () {
      var center = originMap.getCenter();
      updateCoordinates(center.lat, center.lng);
    });
    updateCoordinates(defaultLat, defaultLng);
    setTimeout(function () {
      originMap.invalidateSize();
    }, 200);
    $("#kiriof-wc-use-my-location").on("click", function () {
      var $button = $(this);
      if (!navigator.geolocation) {
        showMapError(i18n.geolocationUnsupported);
        return;
      }
      $button.prop("disabled", true);
      navigator.geolocation.getCurrentPosition(
        function (position) {
          originMap.setView(
            [position.coords.latitude, position.coords.longitude],
            17,
          );
          $button.prop("disabled", false);
        },
        function (error) {
          var messages = [
            i18n.permissionDenied,
            i18n.locationUnavailable,
            i18n.timeout,
          ];
          $button.prop("disabled", false);
          showMapError(messages[error.code - 1] || i18n.unknownError);
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
      );
    });
  }

  $(".kiriof-wc-location-card__body").each(function () {
    var $card = $(this),
      $area = $card.find(".kiriof-wc-origin-area-select"),
      $name = $card.find(".kiriof-wc-location-area-name"),
      $zip = $card.find(".kiriof-wc-location-zip");
    if (select2 && $area.length) {
      select2.call($area, {
        width: "100%",
        minimumInputLength: 3,
        placeholder: $area.data("placeholder") || i18n.selectOption,
        allowClear: true,
        ajax: {
          url: ajaxurl,
          type: "POST",
          dataType: "json",
          delay: 300,
          data: function (params) {
            return {
              action: "kiriminaja_subdistrict_search",
              nonce: nonce(),
              term: params.term,
              data: { term: params.term, search: params.term },
            };
          },
          processResults: function (response) {
            return { results: response && response.data ? response.data : [] };
          },
          cache: true,
        },
      });
      $area.on("select2:select", function (event) {
        var data = event.params && event.params.data ? event.params.data : null,
          label = data ? data.text || data.name || "" : "",
          postcode = extractPostcode(data);
        if (label) {
          $name.val(label);
        }
        if (postcode) {
          $zip.val(postcode).trigger("input").trigger("change");
        }
      });
      $area.on("select2:clear", function () {
        $name.val("");
      });
    }
    var $locationMap = $card.find(".kiriof-wc-origin-map");
    if ($locationMap.length && window.L) {
      var rawLat = $locationMap.data("lat"),
        rawLng = $locationMap.data("lng"),
        hasPin =
          rawLat !== undefined &&
          rawLat !== "" &&
          rawLng !== undefined &&
          rawLng !== "";
      var lat = hasPin ? parseFloat(rawLat) : -6.2,
        lng = hasPin ? parseFloat(rawLng) : 106.817;
      var locationMap = L.map($locationMap[0]).setView(
          [lat, lng],
          hasPin ? 15 : 11,
        ),
        marker = hasPin ? L.marker([lat, lng]) : null;
      L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap contributors",
      }).addTo(locationMap);
      if (marker) {
        marker.addTo(locationMap);
      }
      function setPin(la, ln) {
        $card.find(".kiriof-wc-location-latitude").val(la);
        $card.find(".kiriof-wc-location-longitude").val(ln);
        if (marker) {
          marker.setLatLng([la, ln]);
        } else {
          marker = L.marker([la, ln]).addTo(locationMap);
        }
        locationMap.setView([la, ln], 15);
      }
      locationMap.on("click", function (event) {
        setPin(event.latlng.lat, event.latlng.lng);
      });
      $card.find(".kiriof-wc-origin-my-location").on("click", function (event) {
        event.preventDefault();
        if (navigator.geolocation) {
          navigator.geolocation.getCurrentPosition(function (position) {
            setPin(position.coords.latitude, position.coords.longitude);
          });
        }
      });
      setTimeout(function () {
        locationMap.invalidateSize();
      }, 0);
    }
  });

  $(document).on("click", ".kiriof-wc-location-delete", function (event) {
    event.preventDefault();
    if (!window.confirm(i18n.confirmDelete)) {
      return;
    }
    var $form = $("#mainform");
    if (!$form.length) {
      return;
    }
    var key = $(this).data("location-key");
    $('<input type="hidden" name="save" value="1">').appendTo($form);
    $('<input type="hidden">')
      .attr("name", "kiriof_locations[" + key + "][remove]")
      .val("1")
      .appendTo($form);
    $form.submit();
  });
})(jQuery);
