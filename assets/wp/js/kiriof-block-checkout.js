(function (wp, wc) {
  if (!wp || !wp.plugins || !wp.element || !wc || !wc.blocksCheckout) {
    return;
  }

  const { registerPlugin } = wp.plugins;
  const { createElement, Fragment, useEffect, useMemo, useRef, useState } = wp.element;
  const { ExperimentalOrderMeta } = wc.blocksCheckout;

  if (!ExperimentalOrderMeta) {
    return;
  }

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
    document.querySelectorAll(".kiriof-shipping-totals-original").forEach(function (n) {
      n.remove();
    });

    if (
      !discount ||
      parseFloat(discount.amount || 0) <= 0 ||
      !discount.formatted_original_cost ||
      !discount.formatted_current_cost
    ) {
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

    const del = document.createElement("del");
    del.className = "kiriof-shipping-totals-original";
    del.textContent = discount.formatted_original_cost;
    priceNode.parentNode.insertBefore(del, priceNode);
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

  function KiriofOrderMetaFill(props) {
    const cart = props && props.cart ? props.cart : {};
    const previousCouponsRef = useRef("");
    const [shippingDiscount, setShippingDiscount] = useState(null);
    const fees = Array.isArray(cart.fees) ? cart.fees : [];
    const kiriofFees = fees.filter(function (fee) {
      return (
        fee &&
        (fee.key === 'insurance' ||
          fee.name === "Insurance" ||
          fee.name === 'COD Fee')
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
        const packages = Array.isArray(cart.shippingRates) ? cart.shippingRates : [];
        return packages
          .map(function (pkg) {
            const rates = Array.isArray(pkg.shipping_rates) ? pkg.shipping_rates : [];
            return rates
              .filter(function (r) { return r && r.selected; })
              .map(function (r) { return r.rate_id || r.id || ""; })
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
      },
      [shippingDiscount],
    );

    if (!kiriofFees.length) {
      return null;
    }

    return createElement(
      ExperimentalOrderMeta,
      null,
      createElement(
        Fragment,
        null,
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
