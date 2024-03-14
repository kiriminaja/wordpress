
<script type="text/javascript">
    jQuery(document).ready(function() {
        var transaction = JSON.stringify(<?php echo json_encode(@$transaction); ?>);
        transaction = JSON.parse(transaction);
        
        if (!transaction) { return; }

        const cod_fee = transaction?.cod_fee ? parseInt(`${transaction?.cod_fee}`) : 0;
        const insurance_cost = transaction?.insurance_cost ? parseInt(`${transaction?.insurance_cost}`) : 0;
        const shipping_cost = transaction?.shipping_cost ? parseInt(`${transaction?.shipping_cost}`) : 0;
        const transaction_value = transaction?.transaction_value ? parseInt(`${transaction?.transaction_value}`) : 0;
        const total_transaction_amt = cod_fee + insurance_cost + shipping_cost + transaction_value;
        
        /** Backup current display*/
        const overview_total = jQuery('.woocommerce-order-overview__total.total').html()
        const overview_payment_method = jQuery('.woocommerce-order-overview__payment-method.method').html()
        
        /** Remove Current*/
        jQuery('.woocommerce-order-overview__total.total').remove()
        jQuery('.woocommerce-order-overview__payment-method.method').remove()


        jQuery('.woocommerce-order-overview.woocommerce-thankyou-order-details.order_details').append(`
        <li class="woocommerce-order-overview__total total">
        Sub Total:
            <strong>
                <span class="woocommerce-Price-amount amount">
                    <bdi>
                        <span class="woocommerce-Price-currencySymbol">Rp</span>${kjMoneyFormat(transaction_value)}
                    </bdi>
                </span>
            </strong>
        </li>
        `)


        jQuery('.woocommerce-order-overview.woocommerce-thankyou-order-details.order_details').append(`
        <li class="woocommerce-order-overview__total kj-item">
        Shipping Fee:
            <strong>
                <span class="woocommerce-Price-amount amount">
                    <bdi>
                        <span class="woocommerce-Price-currencySymbol">Rp</span>${kjMoneyFormat(shipping_cost)}
                    </bdi>
                </span>
            </strong>
        </li>
        `)
        
        
        if (insurance_cost>0){
            jQuery('.woocommerce-order-overview.woocommerce-thankyou-order-details.order_details').append(`
            <li class="woocommerce-order-overview__total kj-item">
            Insurance Fee:
                <strong>
                    <span class="woocommerce-Price-amount amount">
                        <bdi>
                            <span class="woocommerce-Price-currencySymbol">Rp</span>${kjMoneyFormat(insurance_cost)}
                        </bdi>
                    </span>
                </strong>
            </li>
            `) 
        }
        
        if (cod_fee>0){
            jQuery('.woocommerce-order-overview.woocommerce-thankyou-order-details.order_details').append(`
            <li class="woocommerce-order-overview__total kj-item">
            COD Fee:
                <strong>
                    <span class="woocommerce-Price-amount amount">
                        <bdi>
                            <span class="woocommerce-Price-currencySymbol">Rp</span>${kjMoneyFormat(cod_fee)}
                        </bdi>
                    </span>
                </strong>
            </li>
            `) 
        }

        jQuery('.woocommerce-order-overview.woocommerce-thankyou-order-details.order_details').append(`
        <li class="woocommerce-order-overview__total kj-item">
        Total:
            <strong>
                <span class="woocommerce-Price-amount amount">
                    <bdi>
                        <span class="woocommerce-Price-currencySymbol">Rp</span>${kjMoneyFormat(total_transaction_amt)}
                    </bdi>
                </span>
            </strong>
        </li>
        `)
        
        
        /** Add previous deleted*/
        jQuery('.woocommerce-order-overview.woocommerce-thankyou-order-details.order_details').append(`
        <li class="woocommerce-order-overview__payment-method method">${overview_payment_method}</li>  
        `)
        
    })
</script>