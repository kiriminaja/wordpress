( function( wp, wc ) {
    if ( ! wp || ! wp.plugins || ! wp.element || ! wc || ! wc.blocksCheckout ) {
        return;
    }

    const { registerPlugin } = wp.plugins;
    const { createElement, Fragment, useEffect, useMemo, useRef, useState } = wp.element;
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
            onComplete( { amount: 0, formatted: '', label: 'Shipping Discount', rate_label: '', formatted_current_cost: '', formatted_original_cost: '' } );
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
                    label: payload.data.label || 'Shipping Discount',
                    rate_label: payload.data.rate_label || '',
                    formatted_current_cost: payload.data.formatted_current_cost || '',
                    formatted_original_cost: payload.data.formatted_original_cost || ''
                } );
            } )
            .catch( function() {
                if ( isActive ) {
                    onComplete( { amount: 0, formatted: '', label: 'Shipping Discount', rate_label: '', formatted_current_cost: '', formatted_original_cost: '' } );
                }
            } );

        return function() {
            isActive = false;
        };
    }

    function fetchShippingRateMeta( onComplete ) {
        if ( ! window.kiriofAjax || ! window.kiriofAjax.ajaxurl ) {
            onComplete( {} );
            return function() {};
        }

        const requestBody = new URLSearchParams();
        requestBody.append( 'action', 'kiriof_get_shipping_rate_meta' );
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

                onComplete( payload.data.rates || {} );
            } )
            .catch( function() {
                if ( isActive ) {
                    onComplete( {} );
                }
            } );

        return function() {
            isActive = false;
        };
    }

    function invalidateBlockShippingRates() {
        if ( ! wp || ! wp.data || ! wp.data.dispatch ) {
            return;
        }

        try {
            const cartDispatch = wp.data.dispatch( 'wc/store/cart' );
            if ( cartDispatch && typeof cartDispatch.invalidateResolutionForStoreSelector === 'function' ) {
                cartDispatch.invalidateResolutionForStoreSelector( 'getShippingRates' );
            }
            if ( cartDispatch && typeof cartDispatch.invalidateResolutionForStore === 'function' ) {
                cartDispatch.invalidateResolutionForStore();
            }
        } catch ( e ) {}

        try {
            const coreDataDispatch = wp.data.dispatch( 'core/data' );
            if ( coreDataDispatch && typeof coreDataDispatch.invalidateResolution === 'function' ) {
                coreDataDispatch.invalidateResolution( 'wc/store/cart', 'getShippingRates', [] );
            }
        } catch ( e ) {}
    }

    function scheduleShippingDecorationRefresh( onDiscount, onRateMeta ) {
        const retryDelays = [ 0, 250, 800, 1600 ];
        let cancelled = false;
        const timers = [];

        retryDelays.forEach( function( delay ) {
            const timer = window.setTimeout( function() {
                if ( cancelled ) {
                    return;
                }

                fetchCurrentShippingDiscount( function( value ) {
                    if ( ! cancelled ) {
                        onDiscount( value );
                    }
                } );

                fetchShippingRateMeta( function( rates ) {
                    if ( ! cancelled ) {
                        onRateMeta( rates || {} );
                    }
                } );
            }, delay );

            timers.push( timer );
        } );

        return function() {
            cancelled = true;
            timers.forEach( function( timer ) {
                window.clearTimeout( timer );
            } );
        };
    }

    function getSelectedRateMeta( cart, rateMetaMap ) {
        if ( ! rateMetaMap || typeof rateMetaMap !== 'object' ) {
            return null;
        }

        const selectedInput = document.querySelector( '.wc-block-components-radio-control__input:checked' );
        if ( selectedInput && selectedInput.value && rateMetaMap[ selectedInput.value ] ) {
            return rateMetaMap[ selectedInput.value ];
        }

        const shippingRates = cart && ( cart.shippingRates || cart.shipping_rates ) ? ( cart.shippingRates || cart.shipping_rates ) : [];
        for ( let i = 0; i < shippingRates.length; i += 1 ) {
            const pkg = shippingRates[ i ];
            const rates = pkg && pkg.shipping_rates ? pkg.shipping_rates : [];
            for ( let j = 0; j < rates.length; j += 1 ) {
                if ( rates[ j ] && rates[ j ].selected && rates[ j ].rate_id && rateMetaMap[ rates[ j ].rate_id ] ) {
                    return rateMetaMap[ rates[ j ].rate_id ];
                }
            }
        }

        const shippingLines = Array.isArray( cart && cart.shipping_lines ) ? cart.shipping_lines : [];
        for ( let k = 0; k < shippingLines.length; k += 1 ) {
            const methodId = shippingLines[ k ] && shippingLines[ k ].method_id ? shippingLines[ k ].method_id : '';
            if ( methodId && rateMetaMap[ methodId ] ) {
                return rateMetaMap[ methodId ];
            }
        }

        return null;
    }

    function syncShippingSummaryLine( cart, shippingDiscount, rateMetaMap ) {
        const selectedRateMeta = getSelectedRateMeta( cart, rateMetaMap );
        const existing = document.querySelector( '.kiriof-block-shipping-rate-details' );
        if ( existing ) {
            existing.remove();
        }

        if ( ! selectedRateMeta || ! selectedRateMeta.label ) {
            return;
        }

        const labels = Array.from( document.querySelectorAll( '.wc-block-components-totals-item__label, .wc-block-components-order-summary-item__description, .wc-block-components-totals-shipping__via, .wc-block-components-totals-shipping__label' ) );
        let shippingLabelNode = labels.find( function( node ) {
            return node && node.textContent && node.textContent.indexOf( selectedRateMeta.label ) !== -1;
        } );

        if ( ! shippingLabelNode ) {
            const rows = Array.from( document.querySelectorAll( '.wc-block-components-totals-item, .wc-block-components-order-summary-item' ) );
            shippingLabelNode = rows.find( function( node ) {
                return node && node.textContent && node.textContent.indexOf( selectedRateMeta.label ) !== -1;
            } );
        }

        if ( ! shippingLabelNode ) {
            return;
        }

        const row = shippingLabelNode.closest( '.wc-block-components-totals-item, .wc-block-components-order-summary-item' ) || shippingLabelNode.parentElement;
        if ( ! row ) {
            return;
        }

        const valueNode = row.querySelector( '.wc-block-formatted-money-amount, .wc-block-components-formatted-money-amount, .wc-block-components-totals-item__value, .wc-block-components-order-summary-item__total' );
        if ( valueNode && selectedRateMeta.formatted_cost ) {
            valueNode.textContent = selectedRateMeta.formatted_cost;
        }

        if ( ! shippingDiscount || shippingDiscount.amount <= 0 || parseFloat( selectedRateMeta.discount_amount || 0 ) <= 0 ) {
            return;
        }

        const detail = document.createElement( 'div' );
        detail.className = 'kiriof-block-shipping-rate-details';
        detail.innerHTML =
            '<span class="kiriof-block-shipping-rate-pricing">' +
                '<del>' + ( selectedRateMeta.formatted_original_cost || selectedRateMeta.formatted_cost || '' ) + '</del>' +
            '</span>';

        if ( valueNode && valueNode.parentElement === row ) {
            row.insertBefore( detail, valueNode );
        } else {
            row.appendChild( detail );
        }
    }

    function getShippingOptionLayoutHost( optionNode ) {
        return optionNode.querySelector( '.wc-block-components-radio-control__option-layout, .wc-block-components-shipping-rates-control__package-list-item, label' ) || optionNode.firstElementChild || optionNode;
    }

    function decorateShippingOptions( rateMetaMap ) {
        document.querySelectorAll( '.kiriof-block-shipping-option-meta' ).forEach( function( node ) {
            node.remove();
        } );

        if ( ! rateMetaMap || typeof rateMetaMap !== 'object' ) {
            return;
        }

        const options = document.querySelectorAll( '.wc-block-components-radio-control__option, .wc-block-components-shipping-rates-control__package-list-item' );
        options.forEach( function( optionNode ) {
            const input = optionNode.querySelector( 'input[type="radio"]' );
            if ( ! input || ! input.value || ! rateMetaMap[ input.value ] ) {
                return;
            }

            const meta = rateMetaMap[ input.value ];
            optionNode.classList.add( 'kiriof-block-shipping-option-card' );
            optionNode.classList.toggle( 'kiriof-block-shipping-option-selected', !!input.checked );
            const layoutHost = getShippingOptionLayoutHost( optionNode );
            const priceNode = optionNode.querySelector( '.wc-block-formatted-money-amount, .wc-block-components-formatted-money-amount, .wc-block-components-radio-control__secondary-label' );

            if ( priceNode && meta.formatted_cost ) {
                priceNode.textContent = meta.formatted_cost;
            }

            if ( ! input.checked ) {
                return;
            }

            const detail = document.createElement( 'div' );
            detail.className = 'kiriof-block-shipping-option-meta';

            if ( meta.eta ) {
                const eta = document.createElement( 'div' );
                eta.className = 'kiriof-block-shipping-option-eta';
                eta.textContent = meta.eta;
                detail.appendChild( eta );
            }

            if ( meta.description ) {
                const description = document.createElement( 'div' );
                description.className = 'kiriof-block-shipping-option-description';
                description.textContent = meta.description;
                detail.appendChild( description );
            }

            if ( parseFloat( meta.discount_amount || 0 ) > 0 && meta.formatted_original_cost && meta.formatted_cost ) {
                const pricing = document.createElement( 'div' );
                pricing.className = 'kiriof-block-shipping-option-pricing';
                pricing.innerHTML =
                    '<del>' + meta.formatted_original_cost + '</del>' +
                    '<ins>' + meta.formatted_cost + '</ins>';
                detail.appendChild( pricing );
            }

            if ( detail.childNodes.length === 0 ) {
                return;
            }

            if ( layoutHost && layoutHost.parentNode === optionNode ) {
                optionNode.insertBefore( detail, layoutHost.nextSibling );
                return;
            }

            optionNode.appendChild( detail );
        } );
    }

    function KiriofOrderMetaFill( props ) {
        const cart = props && props.cart ? props.cart : {};
        const [ shippingDiscount, setShippingDiscount ] = useState( { amount: 0, formatted: '', label: 'Shipping Discount', rate_label: '', formatted_current_cost: '', formatted_original_cost: '' } );
        const [ shippingRateMeta, setShippingRateMeta ] = useState( {} );
        const previousCouponsRef = useRef( '' );
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
        const couponSignature = useMemo( function() {
            const coupons = Array.isArray( cart.coupons ) ? cart.coupons.map( function( coupon ) {
                return coupon && ( coupon.code || coupon.label || '' );
            } ) : [];
            return coupons.join( '|' );
        }, [ cart ] );

        useEffect( function() {
            if ( previousCouponsRef.current !== couponSignature ) {
                previousCouponsRef.current = couponSignature;
                invalidateBlockShippingRates();
            }
        }, [ couponSignature ] );

        useEffect( function() {
            return scheduleShippingDecorationRefresh(
                function( value ) {
                    setShippingDiscount( value );
                },
                function( rates ) {
                    setShippingRateMeta( rates || {} );
                }
            );
        }, [ cartDependencyKey ] );

        useEffect( function() {
            syncShippingSummaryLine( cart, shippingDiscount, shippingRateMeta );
        }, [ cart, shippingDiscount, shippingRateMeta, cartDependencyKey ] );

        useEffect( function() {
            decorateShippingOptions( shippingRateMeta );
        }, [ shippingRateMeta, cartDependencyKey ] );

        if ( ! kiriofFees.length ) {
            return null;
        }

        return createElement(
            ExperimentalOrderMeta,
            null,
            createElement(
                Fragment,
                null,
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
