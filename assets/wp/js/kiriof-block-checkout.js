( function( wp, wc ) {
    if ( ! wp || ! wp.plugins || ! wp.element || ! wc || ! wc.blocksCheckout ) {
        return;
    }

    const { registerPlugin } = wp.plugins;
    const { createElement, Fragment } = wp.element;
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

    function KiriofOrderMetaFill( props ) {
        const cart = props && props.cart ? props.cart : {};
        const fees = Array.isArray( cart.fees ) ? cart.fees : [];
        const kiriofFees = fees.filter( function( fee ) {
            return fee && (
                fee.key === 'insurance'
                || fee.name === 'Insurance'
                || fee.name === 'COD Fee'
            );
        } );

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
                            className: 'kiriof-block-fee-breakdown__row'
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
