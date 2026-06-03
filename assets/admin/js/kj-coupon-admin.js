(function ($) {
  "use strict";

  var state = {
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
    var config = getConfig();
    var shippingTypes =
      Array.isArray(config.discountTypes) ? config.discountTypes : [];
    var isShipping = shippingTypes.indexOf($("#discount_type").val()) !== -1;
    $(".kiriof-shipping-discount-options").toggle(isShipping);

    var $tabs = $(
      ".coupon_data_tabs .kiriof_courier_restrictions_options, .coupon_data_tabs .kiriof_usage_combinations_options",
    );
    var $panels = $(
      "#kiriof_courier_restrictions_coupon_data, #kiriof_usage_combinations_coupon_data",
    );

    $tabs.toggle(isShipping);
    $panels.toggleClass("hidden", !isShipping);

    // Show/hide the Area Restrictions metabox.
    $("#kiriof_area_restrictions_metabox").toggle(isShipping);

    if (!isShipping) {
      var $activeTab = $(".coupon_data_tabs li.active");
      if (
        $activeTab.hasClass("kiriof_courier_restrictions_options") ||
        $activeTab.hasClass("kiriof_usage_combinations_options")
      ) {
        $('.coupon_data_tabs a[href="#usage_restriction_coupon_data"]').trigger(
          "click",
        );
      }
    }
  }


  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function buildIndexes() {
    state.tree =
      Array.isArray(getConfig().regionTree) ? getConfig().regionTree : [];
    state.provinceIndex = {};
    state.islandIndex = {};
    state.totals = { islands: 0, provinces: 0, cities: 0 };

    state.tree.forEach(function (island) {
      var provinceIds = [];
      state.totals.islands += 1;

      (island.provinces || []).forEach(function (province) {
        var cityIds = [];
        var cityNames = {};
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
    var province = state.provinceIndex[String(provinceId)];
    if (!province) {
      return 0;
    }

    return province.cityIds.filter(function (cityId) {
      return !!selectedCityMap(provinceId)[cityId];
    }).length;
  }

  function provinceSelectionState(provinceId) {
    var province = state.provinceIndex[String(provinceId)];
    var count = selectedCityCount(provinceId);
    var total = province ? province.cityIds.length : 0;

    return {
      checked: total > 0 && count === total,
      indeterminate: count > 0 && count < total,
      selectedCount: count,
      totalCount: total,
    };
  }

  function islandSelectionState(islandId) {
    var island = state.islandIndex[String(islandId)];
    var total = 0;
    var selected = 0;

    if (!island) {
      return {
        checked: false,
        indeterminate: false,
        selectedCount: 0,
        totalCount: 0,
      };
    }

    island.provinceIds.forEach(function (provinceId) {
      var provinceState = provinceSelectionState(provinceId);
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
    var province = state.provinceIndex[String(provinceId)];
    var selection = selectedCityMap(provinceId);

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
    var island = state.islandIndex[String(islandId)];
    if (!island) {
      return;
    }

    island.provinceIds.forEach(function (provinceId) {
      setProvinceSelection(provinceId, checked);
    });
  }

  function setCitySelection(provinceId, cityId, checked) {
    var selection = selectedCityMap(provinceId);
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
    var payload = [];

    if (state.scope === "selected") {
      state.tree.forEach(function (island) {
        (island.provinces || []).forEach(function (province) {
          var provinceId = String(province.id);
          var provinceState = provinceSelectionState(provinceId);

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
    var islands = 0;
    var provinces = 0;
    var cities = 0;

    if (state.scope === "all") {
      return { islands: 0, provinces: 0, cities: 0 };
    }

    Object.keys(state.islandIndex).forEach(function (islandId) {
      var islandSeen = false;

      state.islandIndex[islandId].provinceIds.forEach(function (provinceId) {
        var provinceCount = selectedCityCount(provinceId);
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

  function updateStats() {
    var strings = getStrings();
    var selected = selectedTotals();
    var isAll = state.scope === "all";

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
      var $input = $(this);
      var kind = $input.data("kind");
      var selectionState;

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
    var strings = getStrings();
    var term = String(state.searchTerm || "")
      .trim()
      .toLowerCase();
    var html = [];
    var hasMatches = false;

    state.tree.forEach(function (island) {
      var islandMatches = matchesSearch(term, [island.name]);
      var provinceHtml = [];

      (island.provinces || []).forEach(function (province) {
        var provinceMatches =
          islandMatches || matchesSearch(term, [province.name]);
        var cityHtml = [];
        var visibleCities = 0;

        (province.cities || []).forEach(function (city) {
          var cityMatches = provinceMatches || matchesSearch(term, [city.name]);
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
    var isAll = state.scope === "all";
    $("input[name='kiriof_coupon_region_scope'][value='all']").prop("checked", isAll);
    $("input[name='kiriof_coupon_region_scope'][value='selected']").prop("checked", !isAll);
    $(".kiriof-region-picker")
      .toggleClass("is-all-scope", isAll)
      .toggleClass("is-selected-scope", !isAll);
    // Force inline style to override any cached CSS
    $(".kiriof-region-picker-tree, .kiriof-region-picker-toolbar")
      .css("display", isAll ? "none" : "");
    $(".kiriof-region-picker-stats").css("display", isAll ? "none" : "");
    $(".kiriof-region-picker-tree :checkbox").prop("disabled", isAll);
    serializeRegions();
    updateStats();
  }

  function loadInitialRegions() {
    var raw;

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
      var provinceId = String(region.province_id || "");

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
    var strings = getStrings();
    var $button = $(".kiriof-refresh-regions-button");

    $.get(getConfig().ajaxurl, {
      action: "kiriof_get_coupon_region_status",
      nonce: getConfig().nonce,
    })
      .done(function (response) {
        var data = response && response.data ? response.data : {};
        var cacheState = data.status ? data.status.state : "";

        if (cacheState === "ready") {
          if (!silent) {
            window.alert(strings.cacheRefreshed || "Region data refreshed.");
          }
          window.location.reload();
          return;
        }

        if (cacheState === "error") {
          var errorMsg =
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
    var strings = getStrings();
    var $button = $(".kiriof-refresh-regions-button");
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
        var message =
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
    $(document).on("change", "input[name='kiriof_coupon_region_scope']", function () {
        state.scope = $(this).val() === "selected" ? "selected" : "all";
        syncScopeUi();
      });
    $(document).on("input", "#kiriof_coupon_region_search", function () {
      state.searchTerm = $(this).val() || "";
      renderTree();
    });
    $(document).on("change", ".kiriof-region-tree-checkbox", function () {
      var $input = $(this);
      var kind = $input.data("kind");
      var checked = $input.is(":checked");

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
      var strings = getStrings();
      var payload = serializeRegions();

      if (state.scope === "selected" && !payload.length) {
        event.preventDefault();
        window.alert(
          strings.selectRegionBeforeSave ||
            "Choose at least one city or switch back to all regions before saving this coupon.",
        );
      }
    });

    if (getConfig().isCacheStale && !state.totals.cities) {
      refreshCache(true);
    }
  });
})(jQuery);
