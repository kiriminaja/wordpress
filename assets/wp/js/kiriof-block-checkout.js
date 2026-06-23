(function (wp, wc) {
  if (!window || !document) {
    return;
  }

  var SHIPPING_DISCOUNT_TYPES = [
    "kiriof_fixed_shipping_discount",
    "kiriof_percent_shipping_discount",
  ];

  var COUPON_FORM_ID = "coupon-form";
  var APPLIED_MARKER = "has been applied to your cart";

  function normalizeCouponCode(code) {
    return String(code || "").toLowerCase();
  }

  function getCartCoupons() {
    try {
      var cartData = wp.data.select("wc/store/cart").getCartData();
      return cartData && Array.isArray(cartData.coupons) ? cartData.coupons : [];
    } catch (e) {
      return [];
    }
  }

  function isShippingCouponCode(couponCode, coupons) {
    var normalized = normalizeCouponCode(couponCode);
    for (var i = 0; i < coupons.length; i++) {
      if (
        normalizeCouponCode(coupons[i].code) === normalized &&
        SHIPPING_DISCOUNT_TYPES.indexOf(coupons[i].discount_type) !== -1
      ) {
        return true;
      }
    }
    return false;
  }

  function getNativeCouponCodes(coupons) {
    var nativeCodes = [];
    for (var i = 0; i < coupons.length; i++) {
      if (typeof coupons[i] === "string") {
        nativeCodes.push(coupons[i]);
        continue;
      }
      if (SHIPPING_DISCOUNT_TYPES.indexOf(coupons[i].discount_type) === -1) {
        nativeCodes.push(coupons[i].code);
      }
    }
    return nativeCodes;
  }

  function getShippingCouponNotice(couponCode, coupons) {
    var nativeCodes = getNativeCouponCodes(coupons);
    if (nativeCodes.length > 0) {
      return (
        'Shipping discount "' +
        couponCode +
        '" applied and combined with: ' +
        nativeCodes.join(", ") +
        "."
      );
    }
    return 'Shipping discount "' + couponCode + '" applied to your cart.';
  }

  function createShippingCouponNotice(couponCode, context) {
    var coupons = getCartCoupons();
    if (!isShippingCouponCode(couponCode, coupons)) {
      return false;
    }

    try {
      wp.data.dispatch("core/notices").createNotice(
        "info",
        getShippingCouponNotice(couponCode, coupons),
        {
          id: COUPON_FORM_ID,
          type: "snackbar",
          context: context || "wc/cart",
        },
      );
      return true;
    } catch (e) {
      return false;
    }
  }

  (function registerCouponNoticeFilter() {
    if (
      wc &&
      wc.blocksCheckout &&
      typeof wc.blocksCheckout.registerCheckoutFilters === "function"
    ) {
      wc.blocksCheckout.registerCheckoutFilters("kiriminaja-official", {
        showApplyCouponNotice: function (defaultValue, extensions, args) {
          var couponCode = args && args.couponCode;
          if (couponCode && createShippingCouponNotice(couponCode, args.context)) {
            return false;
          }
          return defaultValue;
        },
      });
    }
  })();

  function fetchAppliedCouponScopes(couponCode, onComplete) {
    if (!window.kiriofAjax || !window.kiriofAjax.ajaxurl) {
      onComplete(null);
      return;
    }

    var body = new URLSearchParams();
    body.append("action", "kiriof_get_applied_coupon_scopes");
    body.append("nonce", window.kiriofAjax.nonce || "");
    body.append("coupon_code", couponCode || "");

    window
      .fetch(window.kiriofAjax.ajaxurl, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        body: body.toString(),
      })
      .then(function (r) {
        return r.json();
      })
      .then(function (payload) {
        onComplete(payload && payload.success ? payload.data : null);
      })
      .catch(function () {
        onComplete(null);
      });
  }

  (function replaceCouponNoticeFallback() {
    if (!wp || !wp.data || !wp.data.subscribe || !wp.data.select) {
      return;
    }

    var replacedNotices = {};
    var pendingNotices = {};

    function replaceCouponNotice() {
      try {
        var notices = wp.data.select("core/notices").getNotices();
        var toReplace = null;
        for (var i = 0; i < notices.length; i++) {
          var n = notices[i];
          if (
            n &&
            n.id === COUPON_FORM_ID &&
            typeof n.content === "string" &&
            n.content.indexOf(APPLIED_MARKER) !== -1 &&
            !replacedNotices[n.id + "|" + n.content]
          ) {
            toReplace = n;
            break;
          }
        }

        if (!toReplace) return;

        var couponCode = extractCouponCode(toReplace.content);
        if (!couponCode) return;

        var key = toReplace.id + "|" + toReplace.content;
        var coupons = getCartCoupons();
        if (isShippingCouponCode(couponCode, coupons)) {
          replacedNotices[key] = true;
          wp.data.dispatch("core/notices").removeNotice(toReplace.id, toReplace.context);
          wp.data.dispatch("core/notices").createNotice(
            toReplace.status || "info",
            getShippingCouponNotice(couponCode, coupons),
            {
              id: COUPON_FORM_ID,
              type: "snackbar",
              context: toReplace.context || "wc/cart",
            },
          );
          return;
        }

        if (pendingNotices[key]) return;
        pendingNotices[key] = true;

        fetchAppliedCouponScopes(couponCode, function (data) {
          if (!data || !data.is_shipping) return;

          replacedNotices[key] = true;
          try {
            wp.data.dispatch("core/notices").removeNotice(toReplace.id, toReplace.context);
            wp.data.dispatch("core/notices").createNotice(
              toReplace.status || "info",
              getShippingCouponNotice(couponCode, data.native || []),
              {
                id: COUPON_FORM_ID,
                type: "snackbar",
                context: toReplace.context || "wc/cart",
              },
            );
          } catch (e) {}
        });
      } catch (e) {}
    }

    try {
      wp.data.subscribe(replaceCouponNotice);
    } catch (e) {}

    function extractCouponCode(msg) {
      var match = msg.match(/Coupon code "([^"]+)" has been applied/);
      return match ? match[1] : null;
    }
  })();

  function formatFeeTotal(fee) {
    if (fee && fee.totals && fee.totals.currency_prefix) {
      const total = parseFloat(fee.totals.total || 0);
      return fee.totals.currency_prefix + total.toLocaleString("id-ID");
    }
    return "";
  }

  function fetchCurrentShippingDiscount(onComplete) {
    if (!window.kiriofAjax || !window.kiriofAjax.ajaxurl) {
      onComplete(null);
      return function () {};
    }

    const body = new URLSearchParams();
    body.append("action", "kiriof_get_current_shipping_discount");
    body.append("nonce", window.kiriofAjax.nonce || "");

    let active = true;

    window
      .fetch(window.kiriofAjax.ajaxurl, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        body: body.toString(),
      })
      .then(function (r) {
        return r.json();
      })
      .then(function (payload) {
        if (active && payload && payload.success && payload.data) {
          onComplete(payload.data);
        } else if (active) {
          onComplete(null);
        }
      })
      .catch(function () {
        if (active) {
          onComplete(null);
        }
      });

    return function () {
      active = false;
    };
  }

  // Injects a strikethrough original price into the Order Summary shipping totals row.
  // Scoped only to the totals section — does NOT touch the shipping options list.
  function syncShippingTotalsStrikethrough(discount) {
    if (
      !discount ||
      parseFloat(discount.amount || 0) <= 0 ||
      !discount.formatted_original_cost
    ) {
      document
        .querySelectorAll(".kiriof-shipping-totals-original")
        .forEach(function (n) {
          n.remove();
        });
      return;
    }

    const shippingRow = document.querySelector(
      ".wc-block-components-totals-shipping",
    );
    if (!shippingRow) {
      return;
    }

    const priceNode = shippingRow.querySelector(
      ".wc-block-formatted-money-amount, " +
        ".wc-block-components-formatted-money-amount, " +
        ".wc-block-components-totals-item__value",
    );
    if (!priceNode) {
      return;
    }

    let del = priceNode.parentNode.querySelector(
      ".kiriof-shipping-totals-original",
    );
    if (!del) {
      del = document.createElement("del");
      del.className = "kiriof-shipping-totals-original";
      priceNode.parentNode.insertBefore(del, priceNode);
    }
    if (del.textContent !== discount.formatted_original_cost) {
      del.textContent = discount.formatted_original_cost;
    }
  }

  function syncShippingOptionStrikethrough(discount) {
    const rates = discount && discount.rates ? discount.rates : {};
    const rateIds = Object.keys(rates);

    if (!rateIds.length) {
      document
        .querySelectorAll(".kiriof-shipping-option-original")
        .forEach(function (n) {
          n.remove();
        });
      return;
    }

    document
      .querySelectorAll(".wc-block-components-radio-control__option")
      .forEach(function (option) {
        const input = option.querySelector("input[type='radio']");
        const rateId = input ? String(input.value || "") : "";
        const rateDiscount = rateId ? rates[rateId] : null;
        const existing = option.querySelector(".kiriof-shipping-option-original");

        if (
          !rateDiscount ||
          parseFloat(rateDiscount.amount || 0) <= 0 ||
          !rateDiscount.formatted_original_cost
        ) {
          if (existing) {
            existing.remove();
          }
          return;
        }

        const priceContainer = option.querySelector(
          ".wc-block-components-radio-control__secondary-label",
        );
        if (!priceContainer) {
          return;
        }

        let del = existing;
        if (!del) {
          del = document.createElement("del");
          del.className = "kiriof-shipping-option-original";
          priceContainer.insertBefore(del, priceContainer.firstChild);
        }
        if (del.textContent !== rateDiscount.formatted_original_cost) {
          del.textContent = rateDiscount.formatted_original_cost;
        }
      });
  }

  function syncShippingDiscountTotalsRow(discount) {
    if (
      !discount ||
      parseFloat(discount.amount || 0) <= 0 ||
      !discount.formatted
    ) {
      document
        .querySelectorAll(".kiriof-block-shipping-discount__fallback-row")
        .forEach(function (n) {
          n.remove();
        });
      return;
    }

    const shippingRow = document.querySelector(
      ".wc-block-components-totals-shipping",
    );
    if (!shippingRow || shippingRow.closest(".wp-block-woocommerce-checkout")) {
      return;
    }

    let row = document.querySelector(
      ".kiriof-block-shipping-discount__fallback-row",
    );
    if (!row) {
      row = document.createElement("div");
      row.className =
        "wc-block-components-totals-item kiriof-block-shipping-discount__row kiriof-block-shipping-discount__fallback-row";
      row.appendChild(document.createElement("span"));
      row.appendChild(document.createElement("strong"));
      shippingRow.parentNode.insertBefore(row, shippingRow.nextSibling);
    }

    const label = row.querySelector("span");
    const value = row.querySelector("strong");
    const labelText = discount.label || "Shipping Discount";
    const valueText = "-" + discount.formatted;
    if (label && label.textContent !== labelText) {
      label.textContent = labelText;
    }
    if (value && value.textContent !== valueText) {
      value.textContent = valueText;
    }
  }

  function bootDomShippingDiscountSummary() {
    const retryDelays = [0, 400, 1000, 2000];

    function refresh() {
      fetchCurrentShippingDiscount(function (discount) {
        syncShippingTotalsStrikethrough(discount);
        syncShippingOptionStrikethrough(discount);
        syncShippingDiscountTotalsRow(discount);
      });
    }

    retryDelays.forEach(function (delay) {
      window.setTimeout(refresh, delay);
    });

    if (window.MutationObserver && document.body) {
      let pending = false;
      const observer = new MutationObserver(function () {
        if (pending) {
          return;
        }
        pending = true;
        window.setTimeout(function () {
          pending = false;
          refresh();
        }, 250);
      });
      observer.observe(document.body, {
        childList: true,
        subtree: true,
      });
    }
  }

  function invalidateBlockShippingRates() {
    if (!wp || !wp.data || !wp.data.dispatch) {
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

  const hasCheckoutSlotFill =
    wp &&
    wp.plugins &&
    wp.element &&
    wc &&
    wc.blocksCheckout &&
    wc.blocksCheckout.ExperimentalOrderMeta;

  if (!hasCheckoutSlotFill) {
    bootDomShippingDiscountSummary();
    return;
  }

  const { registerPlugin } = wp.plugins;
  const { createElement, Fragment, useEffect, useMemo, useRef, useState } =
    wp.element;
  const { ExperimentalOrderMeta } = wc.blocksCheckout;

  function KiriofOrderMetaFill(props) {
    const cart = props && props.cart ? props.cart : {};
    const previousCouponsRef = useRef("");
    const [shippingDiscount, setShippingDiscount] = useState(null);
    const fees = Array.isArray(cart.fees) ? cart.fees : [];
    const kiriofFees = fees.filter(function (fee) {
      return (
        fee &&
        (fee.key === "insurance" ||
          fee.name === "Insurance" ||
          fee.name === "COD Fee")
      );
    });

    const couponSignature = useMemo(
      function () {
        const coupons =
          Array.isArray(cart.coupons) ?
            cart.coupons.map(function (coupon) {
              return coupon && (coupon.code || coupon.label || "");
            })
          : [];
        return coupons.join("|");
      },
      [cart],
    );

    // Track which shipping rate is selected — re-fetch strikethrough when a
    // rate appears or changes (e.g. after district is first selected on load).
    const shippingRateSignature = useMemo(
      function () {
        const packages =
          Array.isArray(cart.shippingRates) ? cart.shippingRates : [];
        return packages
          .map(function (pkg) {
            const rates =
              Array.isArray(pkg.shipping_rates) ? pkg.shipping_rates : [];
            return rates
              .filter(function (r) {
                return r && r.selected;
              })
              .map(function (r) {
                return r.rate_id || r.id || "";
              })
              .join(",");
          })
          .join("|");
      },
      [cart],
    );

    useEffect(
      function () {
        if (previousCouponsRef.current !== couponSignature) {
          previousCouponsRef.current = couponSignature;
          invalidateBlockShippingRates();
        }
      },
      [couponSignature],
    );

    // Fetch shipping discount and schedule DOM sync retries to survive React re-renders.
    // Re-runs when coupons change OR when selected shipping rate changes so the
    // strikethrough appears as soon as the user (or autoload) picks a courier.
    useEffect(
      function () {
        const retryDelays = [0, 400, 1000, 2000];
        const timers = [];
        let cancelled = false;
        let cancelFetch = function () {};

        function runFetch() {
          cancelFetch();
          cancelFetch = fetchCurrentShippingDiscount(function (data) {
            if (!cancelled) {
              setShippingDiscount(data);
            }
          });
        }

        retryDelays.forEach(function (delay) {
          const t = window.setTimeout(function () {
            if (!cancelled) {
              runFetch();
            }
          }, delay);
          timers.push(t);
        });

        return function () {
          cancelled = true;
          cancelFetch();
          timers.forEach(function (t) {
            window.clearTimeout(t);
          });
        };
      },
      [couponSignature, shippingRateSignature],
    );

    useEffect(
      function () {
        syncShippingTotalsStrikethrough(shippingDiscount);
        syncShippingOptionStrikethrough(shippingDiscount);
      },
      [shippingDiscount],
    );

    const hasShippingDiscount =
      shippingDiscount && parseFloat(shippingDiscount.amount || 0) > 0;

    if (!kiriofFees.length && !hasShippingDiscount) {
      return null;
    }

    return createElement(
      ExperimentalOrderMeta,
      null,
      createElement(
        Fragment,
        null,
        hasShippingDiscount &&
          createElement(
            "div",
            {
              key: "kiriof-shipping-discount",
              className:
                "wc-block-components-totals-item kiriof-block-shipping-discount__row",
            },
            createElement(
              "span",
              null,
              shippingDiscount.label || "Shipping Discount",
            ),
            createElement(
              "strong",
              null,
              "-" + (shippingDiscount.formatted || ""),
            ),
          ),
        kiriofFees.map(function (fee) {
          return createElement(
            "div",
            {
              key: fee.key || fee.name,
              className:
                "wc-block-components-totals-item kiriof-block-fee-breakdown__row",
            },
            createElement("span", null, fee.name),
            createElement("strong", null, formatFeeTotal(fee)),
          );
        }),
      ),
    );
  }

  registerPlugin("kiriminaja-official-order-meta", {
    render: KiriofOrderMetaFill,
    scope: "woocommerce-checkout",
  });
})(window.wp, window.wc);
