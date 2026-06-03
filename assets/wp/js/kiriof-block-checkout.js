( function( wp, wc ) {
    if ( ! wp || ! wp.plugins || ! wp.element || ! wc || ! wc.blocksCheckout ) {
        return;
    }

    const { registerPlugin } = wp.plugins;
    const { createElement, Fragment, useEffect, useMemo, useState } = wp.element;
    const { ExperimentalOrderMeta } = wc.blocksCheckout;

    if ( ! ExperimentalOrderMeta ) {
        return;
    }

    function formatFeeTotal( fee ) {
        if ( fee && fee.totals && fee.totals.currency_prefix ) {
            const total = parseFloat( fee.totals.total || 0 );
            return fee.totals.currency_prefix + total.toLocaleString( 'id-ID' );
        }
        return '';
    }

    function buildCartDependencyKey( cart ) {
        const coupons = Array.isArray( cart.coupons ) ? cart.coupons.map( function( coupon ) {
            return coupon && ( coupon.code || coupon.label || '' );
        } ) : [];
        const shippingRates = cart.shippingRates || cart.shipping_rates || [];
        const shippingLines = Array.isArray( cart.shipping_lines ) ? cart.shipping_lines.map( function( line ) {
            return {
                method_id: line && line.method_id ? line.method_id : '',
                name: line && line.name ? line.name : '',
                total: line && line.totals ? line.totals.total : ''
            };
        } ) : [];

        return JSON.stringify( {
            coupons: coupons,
            shippingRates: shippingRates,
            shippingLines: shippingLines,
            itemsCount: cart.items_count || 0
        } );
    }

    function fetchCurrentShippingDiscount( onComplete ) {
        if ( ! window.kiriofAjax || ! window.kiriofAjax.ajaxurl ) {
            onComplete( { amount: 0, formatted: '', label: 'Shipping Discount' } );
            return function() {};
        }

        const requestBody = new URLSearchParams();
        requestBody.append( 'action', 'kiriof_get_current_shipping_discount' );
        requestBody.append( 'nonce', window.kiriofAjax.nonce || '' );

        let isActive = true;

        window.fetch( window.kiriofAjax.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: requestBody.toString()
        } )
            .then( function( response ) {
                return response.json();
            } )
            .then( function( payload ) {
                if ( ! isActive || ! payload || payload.success !== true || ! payload.data ) {
                    return;
                }

                onComplete( {
                    amount: parseFloat( payload.data.amount || 0 ),
                    formatted: payload.data.formatted || '',
                    label: payload.data.label || 'Shipping Discount'
                } );
            } )
            .catch( function() {
                if ( isActive ) {
                    onComplete( { amount: 0, formatted: '', label: 'Shipping Discount' } );
                }
            } );

        return function() {
            isActive = false;
        };
    }

    function KiriofOrderMetaFill( props ) {
        const cart = props && props.cart ? props.cart : {};
        const [ shippingDiscount, setShippingDiscount ] = useState( { amount: 0, formatted: '', label: 'Shipping Discount' } );
        const fees = Array.isArray( cart.fees ) ? cart.fees : [];
        const kiriofFees = fees.filter( function( fee ) {
            return fee && (
                fee.key === 'insurance'
                || fee.name === 'Insurance'
                || fee.name === 'COD Fee'
            );
        } );
        const cartDependencyKey = useMemo( function() {
            return buildCartDependencyKey( cart );
        }, [ cart ] );

        useEffect( function() {
            return fetchCurrentShippingDiscount( function( value ) {
                setShippingDiscount( value );
            } );
        }, [ cartDependencyKey ] );

        if ( shippingDiscount.amount <= 0 && ! kiriofFees.length ) {
            return null;
        }

        return createElement(
            ExperimentalOrderMeta,
            null,
            createElement(
                Fragment,
                null,
                shippingDiscount.amount > 0 ? createElement(
                    'div',
                    {
                        key: 'kiriof-shipping-discount',
                        className: 'wc-block-components-totals-item kiriof-block-fee-breakdown__row kiriof-block-shipping-discount-row'
                    },
                    createElement( 'span', null, shippingDiscount.label ),
                    createElement( 'strong', null, '-' + shippingDiscount.formatted )
                ) : null,
                kiriofFees.map( function( fee ) {
                    return createElement(
                        'div',
                        {
                            key: fee.key || fee.name,
                            className: 'wc-block-components-totals-item kiriof-block-fee-breakdown__row'
                        },
                        createElement( 'span', null, fee.name ),
                        createElement( 'strong', null, formatFeeTotal( fee ) )
                    );
                } )
            )
        );
    }

    registerPlugin( 'kiriminaja-official-order-meta', {
        render: KiriofOrderMetaFill,
        scope: 'woocommerce-checkout'
    } );
} )( window.wp, window.wc );
