(function ($) {
  "use strict";

  var state = {
    regions: [],
  };

  function getConfig() {
    return window.kiriofCouponAdmin || { strings: {} };
  }

  function syncVisibility() {
    var config = getConfig();
    var isShipping = $("#discount_type").val() === config.discountType;
    $(".kiriof-shipping-discount-options").toggle(isShipping);
  }

  function syncBuilderVisibility() {
    var mode = $("#kiriof_coupon_region_mode").val();
    $(".kiriof-region-province-field").prop("hidden", mode === "all_province");
    $(".kiriof-region-city-field").prop("hidden", mode !== "specific_city");
  }

  function saveRegions() {
    $("#kiriof_coupon_regions").val(JSON.stringify(state.regions));
  }

  function regionKey(region) {
    return [region.type, region.province_id || "", region.city_id || ""].join(
      ":",
    );
  }

  function formatRegionLabel(region) {
    var strings = getConfig().strings || {};

    if (region.type === "all_province") {
      return strings.allProvinceLabel || "All provinces in Indonesia";
    }

    if (region.type === "all_city_in_province") {
      return (strings.allCitiesLabel || "All cities in %s").replace(
        "%s",
        region.province_name || "",
      );
    }

    var template = strings.specificCityLabel || "%1$s, %2$s";
    return template
      .replace("%1$s", region.city_name || "")
      .replace("%2$s", region.province_name || "");
  }

  function renderRegions() {
    var strings = getConfig().strings || {};
    var $list = $(".kiriof-region-chip-list");
    $list.empty();

    state.regions.forEach(function (region, index) {
      var $chip = $("<span />", { class: "kiriof-region-chip" });
      $chip.append(document.createTextNode(formatRegionLabel(region)));
      $chip.append(
        $("<button />", {
          type: "button",
          class: "kiriof-remove-chip",
          "data-index": index,
          "aria-label": strings.removeRegion || "Remove region",
          text: "×",
        }),
      );
      $list.append($chip);
    });

    saveRegions();
  }

  function loadInitialRegions() {
    try {
      state.regions = JSON.parse($("#kiriof_coupon_regions").val() || "[]");
      if (!Array.isArray(state.regions)) {
        state.regions = [];
      }
    } catch (error) {
      state.regions = [];
    }
    renderRegions();
  }

  function fetchCities(provinceId) {
    return $.post(getConfig().ajaxurl, {
      action: "kiriof_get_coupon_region_cities",
      nonce: getConfig().nonce,
      province_id: provinceId,
    });
  }

  function populateCities(provinceId) {
    var strings = getConfig().strings || {};
    var $cities = $("#kiriof_coupon_region_cities");
    $cities.empty();

    if (!provinceId) {
      return $.Deferred().resolve();
    }

    return fetchCities(provinceId)
      .done(function (response) {
        if (!response || !response.success || !Array.isArray(response.data)) {
          return;
        }

        response.data.forEach(function (city) {
          var option = new Option(city.text, city.id, false, false);
          $cities.append(option);
        });

        $cities.trigger("change");
      })
      .fail(function () {
        window.alert(
          strings.cacheRefreshFailed || "Failed to refresh region data.",
        );
      });
  }

  function addRegion() {
    var strings = getConfig().strings || {};
    var mode = $("#kiriof_coupon_region_mode").val();
    var $province = $("#kiriof_coupon_region_province");
    var provinceId = $province.val();
    var provinceName = $province.find("option:selected").text();
    var additions = [];

    if (mode === "all_province") {
      additions.push({
        type: "all_province",
        province_id: "all",
        province_name: strings.allProvinceLabel || "All provinces in Indonesia",
      });
    } else if (!provinceId) {
      window.alert(strings.chooseProvince || "Please choose a province first.");
      return;
    } else if (mode === "all_city_in_province") {
      additions.push({
        type: "all_city_in_province",
        province_id: provinceId,
        province_name: provinceName,
      });
    } else {
      var cityIds = $("#kiriof_coupon_region_cities").val() || [];
      if (!cityIds.length) {
        window.alert(strings.chooseCity || "Please choose at least one city.");
        return;
      }

      cityIds.forEach(function (cityId) {
        var cityName = $(
          '#kiriof_coupon_region_cities option[value="' + cityId + '"]',
        ).text();
        additions.push({
          type: "specific_city",
          province_id: provinceId,
          province_name: provinceName,
          city_id: cityId,
          city_name: cityName,
        });
      });
    }

    additions.forEach(function (region) {
      var exists = state.regions.some(function (item) {
        return regionKey(item) === regionKey(region);
      });
      if (!exists) {
        state.regions.push(region);
      }
    });

    renderRegions();
    $(".kiriof-region-builder").prop("hidden", true);
  }

  function refreshCache(silent) {
    var strings = getConfig().strings || {};
    var $button = $(".kiriof-refresh-regions-button");
    $button
      .prop("disabled", true)
      .text(strings.cacheRefreshing || "Refreshing region data…");

    return $.post(getConfig().ajaxurl, {
      action: "kiriof_refresh_coupon_regions",
      nonce: getConfig().nonce,
    })
      .done(function () {
        if (!silent) {
          window.alert(strings.cacheRefreshed || "Region data refreshed.");
        }
        window.location.reload();
      })
      .fail(function () {
        if (!silent) {
          window.alert(
            strings.cacheRefreshFailed || "Failed to refresh region data.",
          );
        }
      })
      .always(function () {
        $button.prop("disabled", false).text("Refresh Region Data");
      });
  }

  $(function () {
    if (!$("#discount_type").length) {
      return;
    }

    $("#kiriof_coupon_couriers, #kiriof_coupon_region_cities").select2();
    loadInitialRegions();
    syncVisibility();
    syncBuilderVisibility();

    $(document).on("change", "#discount_type", syncVisibility);
    $(document).on("click", ".kiriof-add-region-button", function () {
      $(".kiriof-region-builder").prop("hidden", function (_, value) {
        return !value;
      });
    });
    $(document).on(
      "change",
      "#kiriof_coupon_region_mode",
      syncBuilderVisibility,
    );
    $(document).on("change", "#kiriof_coupon_region_province", function () {
      populateCities($(this).val());
    });
    $(document).on("click", ".kiriof-confirm-region-button", addRegion);
    $(document).on("click", ".kiriof-remove-chip", function () {
      var index = parseInt($(this).data("index"), 10);
      state.regions.splice(index, 1);
      renderRegions();
    });
    $(document).on("click", ".kiriof-refresh-regions-button", function () {
      refreshCache(false);
    });

    if (getConfig().isCacheStale) {
      refreshCache(true);
    }
  });
})(jQuery);
