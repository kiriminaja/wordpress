(function (wp, wc) {
  if (!wp || !wp.plugins || !wp.element || !wc || !wc.blocksCheckout) {
    return;
  }

  const { registerPlugin } = wp.plugins;
  const { createElement, Fragment, useEffect, useMemo, useRef } = wp.element;
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

    useEffect(
      function () {
        if (previousCouponsRef.current !== couponSignature) {
          previousCouponsRef.current = couponSignature;
          invalidateBlockShippingRates();
        }
      },
      [couponSignature],
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
