(function ($) {
  "use strict";

  const state = {
    scope: "all",
    searchTerm: "",
    selectedCities: {},
    tree: [],
    provinceIndex: {},
    islandIndex: {},
    totals: {
      islands: 0,
      provinces: 0,
      cities: 0,
    },
  };

  function getConfig() {
    return window.kiriofCouponAdmin || { strings: {}, regionTree: [] };
  }

  function getStrings() {
    return getConfig().strings || {};
  }

  function syncVisibility() {
    const config = getConfig();
    const shippingTypes =
      Array.isArray(config.discountTypes) ? config.discountTypes : [];
    const isShipping = shippingTypes.indexOf($("#discount_type").val()) !== -1;
    $(".kiriof-shipping-discount-options").toggle(isShipping);

    // Hide WooCommerce's "Allow free shipping" field — not applicable for shipping discounts.
    $(".free_shipping_field").toggle(!isShipping);

    // "Individual use only" stays visible — combinations react to it instead.
    syncCombinationsAvailability();

    // Show/hide the three KiriminAja metaboxes.
    $("#kiriof_area_restrictions_metabox").toggle(isShipping);
    $("#kiriof_courier_restrictions_metabox").toggle(isShipping);
    $("#kiriof_usage_combinations_metabox").toggle(isShipping);
  }

  function syncCombinationsAvailability() {
    const isIndividualUse = $('input[name="individual_use"]').is(":checked");
    const $options = $(".kiriof-combination-options");
    const $notice = $("#kiriof-individual-use-active-notice");

    $options.find('input[type="checkbox"]').prop("disabled", isIndividualUse);
    $options.css("opacity", isIndividualUse ? "0.4" : "1");
    $notice.toggle(isIndividualUse);
  }

  function escapeHtml(value: string | null | undefined): string {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function buildIndexes() {
    state.tree =
      Array.isArray(getConfig().regionTree) ? getConfig().regionTree : [];
    state.provinceIndex = {};
    state.islandIndex = {};
    state.totals = { islands: 0, provinces: 0, cities: 0 };

    state.tree.forEach(function (island) {
      const provinceIds = [];
      state.totals.islands += 1;

      (island.provinces || []).forEach(function (province) {
        const cityIds = [];
        const cityNames = {};
        state.totals.provinces += 1;
        provinceIds.push(String(province.id));

        (province.cities || []).forEach(function (city) {
          cityIds.push(String(city.id));
          cityNames[String(city.id)] = city.name;
          state.totals.cities += 1;
        });

        state.provinceIndex[String(province.id)] = {
          id: String(province.id),
          name: province.name,
          islandId: String(island.id),
          cityIds: cityIds,
          cityNames: cityNames,
        };
      });

      state.islandIndex[String(island.id)] = {
        id: String(island.id),
        name: island.name,
        provinceIds: provinceIds,
      };
    });
  }

  function selectedCityMap(provinceId) {
    provinceId = String(provinceId);
    if (!state.selectedCities[provinceId]) {
      state.selectedCities[provinceId] = {};
    }
    return state.selectedCities[provinceId];
  }

  function selectedCityCount(provinceId) {
    const province = state.provinceIndex[String(provinceId)];
    if (!province) {
      return 0;
    }

    return province.cityIds.filter(function (cityId) {
      return !!selectedCityMap(provinceId)[cityId];
    }).length;
  }

  function provinceSelectionState(provinceId) {
    const province = state.provinceIndex[String(provinceId)];
    const count = selectedCityCount(provinceId);
    const total = province ? province.cityIds.length : 0;

    return {
      checked: total > 0 && count === total,
      indeterminate: count > 0 && count < total,
      selectedCount: count,
      totalCount: total,
    };
  }

  function islandSelectionState(islandId) {
    const island = state.islandIndex[String(islandId)];
    let total = 0;
    let selected = 0;

    if (!island) {
      return {
        checked: false,
        indeterminate: false,
        selectedCount: 0,
        totalCount: 0,
      };
    }

    island.provinceIds.forEach(function (provinceId) {
      const provinceState = provinceSelectionState(provinceId);
      total += provinceState.totalCount;
      selected += provinceState.selectedCount;
    });

    return {
      checked: total > 0 && selected === total,
      indeterminate: selected > 0 && selected < total,
      selectedCount: selected,
      totalCount: total,
    };
  }

  function setProvinceSelection(provinceId, checked) {
    const province = state.provinceIndex[String(provinceId)];
    const selection = selectedCityMap(provinceId);

    if (!province) {
      return;
    }

    province.cityIds.forEach(function (cityId) {
      if (checked) {
        selection[cityId] = true;
      } else {
        delete selection[cityId];
      }
    });

    if (!Object.keys(selection).length) {
      delete state.selectedCities[String(provinceId)];
    }
  }

  function setIslandSelection(islandId, checked) {
    const island = state.islandIndex[String(islandId)];
    if (!island) {
      return;
    }

    island.provinceIds.forEach(function (provinceId) {
      setProvinceSelection(provinceId, checked);
    });
  }

  function setCitySelection(provinceId, cityId, checked) {
    const selection = selectedCityMap(provinceId);
    cityId = String(cityId);

    if (checked) {
      selection[cityId] = true;
    } else {
      delete selection[cityId];
    }

    if (!Object.keys(selection).length) {
      delete state.selectedCities[String(provinceId)];
    }
  }

  function serializeRegions() {
    const payload = [];

    if (state.scope === "selected") {
      state.tree.forEach(function (island) {
        (island.provinces || []).forEach(function (province) {
          const provinceId = String(province.id);
          const provinceState = provinceSelectionState(provinceId);

          if (!provinceState.selectedCount) {
            return;
          }

          if (provinceState.checked) {
            payload.push({
              type: "all_city_in_province",
              province_id: provinceId,
              province_name: province.name,
            });
            return;
          }

          (province.cities || []).forEach(function (city) {
            if (!selectedCityMap(provinceId)[String(city.id)]) {
              return;
            }

            payload.push({
              type: "specific_city",
              province_id: provinceId,
              province_name: province.name,
              city_id: String(city.id),
              city_name: city.name,
            });
          });
        });
      });
    }

    $("#kiriof_coupon_regions").val(JSON.stringify(payload));
    return payload;
  }

  function selectedTotals() {
    let islands = 0;
    let provinces = 0;
    let cities = 0;

    if (state.scope === "all") {
      return { islands: 0, provinces: 0, cities: 0 };
    }

    Object.keys(state.islandIndex).forEach(function (islandId) {
      let islandSeen = false;

      state.islandIndex[islandId].provinceIds.forEach(function (provinceId) {
        const provinceCount = selectedCityCount(provinceId);
        if (!provinceCount) {
          return;
        }

        provinces += 1;
        cities += provinceCount;

        if (!islandSeen) {
          islands += 1;
          islandSeen = true;
        }
      });
    });

    return { islands: islands, provinces: provinces, cities: cities };
  }

  function normalizeCouponAmountValue(value) {
    const raw = String(value || "").trim();
    if (!raw) {
      return raw;
    }

    let normalized = raw.replace(/\s+/g, "").replace(",", ".");
    if (
      !/^-?\d*(?:\.\d*)?$/.test(normalized) ||
      isNaN(parseFloat(normalized))
    ) {
      return raw;
    }

    const negative = normalized.charAt(0) === "-";
    if (negative) {
      normalized = normalized.slice(1);
    }

    const parts = normalized.split(".");
    let integer = (parts[0] || "").replace(/^0+(?=\d)/, "");
    if (!integer) {
      integer = "0";
    }
    if (negative && integer !== "0") {
      integer = "-" + integer;
    }

    return parts.length > 1 ?
        integer + "." + parts.slice(1).join(".")
      : integer;
  }

  function normalizeCouponAmountField() {
    const $amount = $("#coupon_amount");
    const normalized = normalizeCouponAmountValue($amount.val());
    if ($amount.val() !== normalized) {
      $amount.val(normalized);
    }
    return normalized;
  }

  function updateStats() {
    const strings = getStrings();
    const selected = selectedTotals();
    const isAll = state.scope === "all";

    $(".kiriof-region-picker-stats").toggle(!isAll);

    if (isAll) {
      return;
    }

    $(".kiriof-region-stat[data-kind='islands']").text(
      selected.islands +
        "/" +
        state.totals.islands +
        " " +
        (strings.islands || "Islands"),
    );
    $(".kiriof-region-stat[data-kind='provinces']").text(
      selected.provinces +
        "/" +
        state.totals.provinces +
        " " +
        (strings.provinces || "Provinces"),
    );
    $(".kiriof-region-stat[data-kind='cities']").text(
      selected.cities +
        "/" +
        state.totals.cities +
        " " +
        (strings.cities || "Cities"),
    );
  }

  function applyCheckboxStates() {
    $(".kiriof-region-tree-checkbox").each(function () {
      const $input = $(this);
      const kind = $input.data("kind");
      let selectionState;

      if (kind === "island") {
        selectionState = islandSelectionState($input.data("islandId"));
      } else if (kind === "province") {
        selectionState = provinceSelectionState($input.data("provinceId"));
      } else {
        selectionState = {
          checked: !!selectedCityMap($input.data("provinceId"))[
            String($input.data("cityId"))
          ],
          indeterminate: false,
        };
      }

      $input.prop("checked", selectionState.checked);
      $input.prop("indeterminate", selectionState.indeterminate);
    });
  }

  function matchesSearch(term, values) {
    if (!term) {
      return true;
    }

    return values.some(function (value) {
      return (
        String(value || "")
          .toLowerCase()
          .indexOf(term) !== -1
      );
    });
  }

  function renderTree() {
    const strings = getStrings();
    const term = String(state.searchTerm || "")
      .trim()
      .toLowerCase();
    const html = [];
    let hasMatches = false;

    state.tree.forEach(function (island) {
      const islandMatches = matchesSearch(term, [island.name]);
      const provinceHtml = [];

      (island.provinces || []).forEach(function (province) {
        const provinceMatches =
          islandMatches || matchesSearch(term, [province.name]);
        const cityHtml = [];
        let visibleCities = 0;

        (province.cities || []).forEach(function (city) {
          const cityMatches = provinceMatches || matchesSearch(term, [city.name]);
          if (!cityMatches) {
            return;
          }

          visibleCities += 1;
          cityHtml.push(
            '<label class="kiriof-region-city">' +
              '<input class="kiriof-region-tree-checkbox" type="checkbox" data-kind="city" data-province-id="' +
              escapeHtml(province.id) +
              '" data-city-id="' +
              escapeHtml(city.id) +
              '">' +
              "<span>" +
              escapeHtml(city.name) +
              "</span>" +
              "</label>",
          );
        });

        if (!provinceMatches && !visibleCities) {
          return;
        }

        hasMatches = true;
        provinceHtml.push(
          '<div class="kiriof-region-province-card">' +
            '<label class="kiriof-region-province">' +
            '<input class="kiriof-region-tree-checkbox" type="checkbox" data-kind="province" data-province-id="' +
            escapeHtml(province.id) +
            '">' +
            "<span>" +
            escapeHtml(province.name) +
            "</span>" +
            "</label>" +
            '<div class="kiriof-region-cities-grid">' +
            cityHtml.join("") +
            "</div>" +
            "</div>",
        );
      });

      if (!provinceHtml.length) {
        return;
      }

      html.push(
        '<section class="kiriof-region-island">' +
          '<label class="kiriof-region-island-label">' +
          '<input class="kiriof-region-tree-checkbox" type="checkbox" data-kind="island" data-island-id="' +
          escapeHtml(island.id) +
          '">' +
          "<span>" +
          escapeHtml(island.name) +
          "</span>" +
          "</label>" +
          '<div class="kiriof-region-province-list">' +
          provinceHtml.join("") +
          "</div>" +
          "</section>",
      );
    });

    if (!hasMatches) {
      html.push(
        '<p class="kiriof-region-empty-state">' +
          escapeHtml(
            strings.noRegionMatches || "No regions match your search.",
          ) +
          "</p>",
      );
    }

    $(".kiriof-region-picker-tree").html(html.join(""));
    applyCheckboxStates();
    updateStats();
  }

  function syncScopeUi() {
    const isAll = state.scope === "all";
    $("input[name='kiriof_coupon_region_scope'][value='all']").prop(
      "checked",
      isAll,
    );
    $("input[name='kiriof_coupon_region_scope'][value='selected']").prop(
      "checked",
      !isAll,
    );
    $(".kiriof-region-picker")
      .toggleClass("is-all-scope", isAll)
      .toggleClass("is-selected-scope", !isAll);
    // Force inline style to override any cached CSS
    $(".kiriof-region-picker-tree, .kiriof-region-picker-toolbar")
      .css("display", isAll ? "none" : "")
      .css("margin-top", isAll ? "" : "16px");
    $(".kiriof-region-picker-stats").css("display", isAll ? "none" : "");
    $(".kiriof-region-picker-tree :checkbox").prop("disabled", isAll);
    serializeRegions();
    updateStats();
  }

  function loadInitialRegions() {
    let raw;

    try {
      raw = JSON.parse($("#kiriof_coupon_regions").val() || "[]");
    } catch (error) {
      raw = [];
    }

    state.selectedCities = {};
    state.scope = "all";

    if (!Array.isArray(raw) || !raw.length) {
      return;
    }

    raw.forEach(function (region) {
      const provinceId = String(region.province_id || "");

      if (region.type === "all_province" && provinceId === "all") {
        state.scope = "all";
        return;
      }

      state.scope = "selected";

      if (region.type === "all_city_in_province") {
        setProvinceSelection(provinceId, true);
        return;
      }

      if (region.type === "specific_city" && region.city_id) {
        setCitySelection(provinceId, String(region.city_id), true);
      }
    });
  }

  function pollCacheStatus(silent) {
    const strings = getStrings();
    const $button = $(".kiriof-refresh-regions-button");

    $.get(getConfig().ajaxurl, {
      action: "kiriof_get_coupon_region_status",
      nonce: getConfig().nonce,
    })
      .done(function (response) {
        const data = response && response.data ? response.data : {};
        const cacheState = data.status ? data.status.state : "";

        if (cacheState === "ready") {
          if (!silent) {
            window.alert(strings.cacheRefreshed || "Region data refreshed.");
          }
          window.location.reload();
          return;
        }

        if (cacheState === "error") {
          const errorMsg =
            (data.status && data.status.last_error) ||
            strings.cacheRefreshFailed ||
            "Failed to refresh region data.";
          if (!silent) {
            window.alert(errorMsg);
          }
          $button
            .prop("disabled", false)
            .text(strings.refreshRegionData || "Refresh Region Data");
          return;
        }

        setTimeout(function () {
          pollCacheStatus(silent);
        }, 3000);
      })
      .fail(function () {
        setTimeout(function () {
          pollCacheStatus(silent);
        }, 5000);
      });
  }

  function refreshCache(silent) {
    const strings = getStrings();
    const $button = $(".kiriof-refresh-regions-button");
    $button
      .prop("disabled", true)
      .text(strings.cacheRefreshing || "Refreshing region data…");

    return $.post(getConfig().ajaxurl, {
      action: "kiriof_refresh_coupon_regions",
      nonce: getConfig().nonce,
    })
      .done(function () {
        setTimeout(function () {
          pollCacheStatus(silent);
        }, 3000);
      })
      .fail(function (xhr) {
        let message =
          strings.cacheRefreshFailed || "Failed to refresh region data.";

        if (xhr && xhr.responseJSON && xhr.responseJSON.data) {
          if (typeof xhr.responseJSON.data.message === "string") {
            message = xhr.responseJSON.data.message;
          } else if (typeof xhr.responseJSON.data === "string") {
            message = xhr.responseJSON.data;
          }
        }

        if (!silent) {
          window.alert(message);
        }
        $button
          .prop("disabled", false)
          .text(strings.refreshRegionData || "Refresh Region Data");
      });
  }

  $(function () {
    if (!$("#discount_type").length) {
      return;
    }

    if ($("#kiriof_coupon_couriers").length) {
      $("#kiriof_coupon_couriers").select2();
    }

    // Courier scope toggle
    $(document).on(
      "change",
      "input[name='kiriof_coupon_courier_scope']",
      function () {
        const isSelected = $(this).val() === "selected";
        $(".kiriof-courier-list")
          .toggle(isSelected)
          .css("margin-top", isSelected ? "16px" : "");
        $('input[name="_kiriof_coupon_couriers_scope"]').val($(this).val());
      },
    );

    // Inject critical layout styles inline to guarantee they win over cached CSS.
    if (!$("#kiriof-region-styles").length) {
      $("head").append(
        '<style id="kiriof-region-styles">' +
          ".kiriof-region-province-list{display:grid!important;grid-template-columns:repeat(3,1fr)!important;gap:10px!important;padding:12px!important}" +
          ".kiriof-region-province-card{border:1px solid #dcdcde!important;border-radius:8px!important;background:#fdfdfd!important;padding:10px 12px!important}" +
          ".kiriof-region-island{border:1px solid #c3c4c7!important;border-radius:10px!important;background:#fff!important;overflow:hidden!important;margin-bottom:12px!important}" +
          ".kiriof-region-island-label{display:flex!important;align-items:center!important;gap:8px!important;padding:10px 14px!important;background:#f0f0f1!important;border-bottom:1px solid #dcdcde!important;font-size:13px!important;font-weight:700!important;text-transform:uppercase!important}" +
          ".kiriof-region-province{display:flex!important;align-items:center!important;gap:7px!important;font-size:12px!important;font-weight:700!important;color:#2271b1!important;text-transform:uppercase!important;margin-bottom:8px!important;padding-bottom:7px!important;border-bottom:1px solid #ebebec!important}" +
          ".kiriof-region-cities-grid{display:grid!important;grid-template-columns:repeat(auto-fill,minmax(150px,1fr))!important;gap:5px 8px!important}" +
          ".kiriof-region-city{display:flex!important;align-items:flex-start!important;gap:5px!important;font-size:12px!important;line-height:1.4!important}" +
          ".kiriof-region-picker-tree{max-height:640px!important;overflow:auto!important;padding:16px!important;border:1px solid #dcdcde!important;border-radius:12px!important;background:#f6f7f7!important}" +
          "</style>",
      );
    }

    buildIndexes();
    loadInitialRegions();
    syncScopeUi(); // apply is-all-scope before rendering tree
    syncVisibility();
    renderTree();

    $(document).on("change", "#discount_type", syncVisibility);
    $(document).on(
      "change",
      'input[name="individual_use"]',
      syncCombinationsAvailability,
    );
    $(document).on(
      "change",
      "input[name='kiriof_coupon_region_scope']",
      function () {
        state.scope = $(this).val() === "selected" ? "selected" : "all";
        syncScopeUi();
      },
    );
    $(document).on("input", "#kiriof_coupon_region_search", function () {
      state.searchTerm = $(this).val() || "";
      renderTree();
    });
    $(document).on("change", ".kiriof-region-tree-checkbox", function () {
      const $input = $(this);
      const kind = $input.data("kind");
      const checked = $input.is(":checked");

      if (kind === "island") {
        setIslandSelection($input.data("islandId"), checked);
      } else if (kind === "province") {
        setProvinceSelection($input.data("provinceId"), checked);
      } else {
        setCitySelection(
          $input.data("provinceId"),
          $input.data("cityId"),
          checked,
        );
      }

      applyCheckboxStates();
      serializeRegions();
      updateStats();
    });
    $(document).on("click", ".kiriof-refresh-regions-button", function () {
      refreshCache(false);
    });

    $("#post").on("submit", function (event) {
      const strings = getStrings();
      const config = getConfig();
      const shippingTypes =
        Array.isArray(config.discountTypes) ? config.discountTypes : [];
      const currentType = $("#discount_type").val();

      // Block save if percentage shipping discount amount exceeds 100.
      if (
        shippingTypes.indexOf(currentType) !== -1 &&
        currentType.indexOf("percent") !== -1
      ) {
        const amount = parseFloat(normalizeCouponAmountField());
        if (!isNaN(amount) && amount > 100) {
          event.preventDefault();
          window.alert(
            strings.percentageExceeds100 ||
              "Discount amount cannot exceed 100% for a percentage shipping discount.",
          );
          $("#coupon_amount").focus();
          return;
        }
      }

      const payload = serializeRegions();

      if (state.scope === "selected" && !payload.length) {
        event.preventDefault();
        window.alert(
          strings.selectRegionBeforeSave ||
            "Choose at least one city or switch back to all regions before saving this coupon.",
        );
      }
    });

    // Normalize coupon_amount for every coupon and cap percentage shipping discounts at 100.
    $(document).on("change blur", "#coupon_amount", function () {
      const normalizedAmount = normalizeCouponAmountField();
      const config = getConfig();
      const shippingTypes =
        Array.isArray(config.discountTypes) ? config.discountTypes : [];
      const currentType = $("#discount_type").val();
      if (
        shippingTypes.indexOf(currentType) !== -1 &&
        currentType.indexOf("percent") !== -1
      ) {
        const val = parseFloat(normalizedAmount);
        if (!isNaN(val) && val > 100) {
          $(this).val("100");
        }
      }
    });

    if (getConfig().isCacheStale && !state.totals.cities) {
      refreshCache(true);
    }
  });
})(jQuery);
