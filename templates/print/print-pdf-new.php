<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require __DIR__ . "/../../vite.render.php";
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <style>
        @page {
            margin: 1rem;
        }
        body, html {
            font-family: "Arial", sans-serif;
            border: 0;
            line-height: .8rem;
            font-size: .7rem;
        }
        .page-break {page-break-after: always;}
        br {
            display: block;
            margin: .25rem 0;
        }
    </style>
</head>
<body>
<?php



foreach ($transactions as $index => $transaction){

    $transactionCost = 0;
    $transactionCost += intval($transaction->shipping_cost ?? 0)+intval($transaction->insurance_cost ?? 0);
    if ($transaction->cod_fee > 0){
        $transactionCost += intval($transaction->cod_fee ?? 0)+intval($transaction->transaction_value ?? 0);
    }
    $destinationData = (object) json_decode($transaction->shipping_info);

    echo '<table  style="width: 100%; height: 95%; border-collapse: collapse; margin-top: .25rem" border="1">
            <tr>
                <td style="border-right: 0; padding: .5rem">';
                    echo '<img src="' . esc_url('https://kiriminaja.com/assets/home/2.png') . '" height="25px" alt="">'; // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
                '</td>
                 <td style="border-left: 0;text-align: right; font-size: 1rem; font-weight: 700; padding: .5rem">
                     '.(esc_html($transaction->cod_fee) > 0 ? 'COD Rp'. esc_html(localMoneyFormat($transactionCost)) : 'NON-COD').'
                </td>
            </tr>
            
            <tr>
                <td style="border-right: 0;padding: .5rem; position: relative">
                    <div style="position: relative">
                        <strong style="display: block;margin-top: 0.5rem">'.esc_html($transaction?->order_id).'</strong>
                    </div>
                    <div style="display: inline-block; margin-top: 1.25rem">
                        <div style="display: inline-block;border: 1px solid #000;padding: .15rem">
                            <img src="' . esc_url('https://kiriminaja-static-file.imgix.net/home-v3/logistics/' . $transaction->service . '.png') . '" height="20px" alt="">'; // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
                        '</div>
                        <div style="display: inline-block;padding: .15rem .5rem">
                            Tipe Layanan
                            <br>
                            <strong>'.esc_html($transaction?->service_name).'</strong>
                        </div>
                    </div>
                </td>
                <td style="border-left: 0;padding: .5rem; position: relative">
                    <div style="position: relative; text-align: center">
                        <img src="data:image/png;base64,'.esc_html(base64_encode(KJ_GENERATE_BARCODE()->getBarcode(strtoupper(esc_html($transaction?->awb)),KJ_GENERATE_BARCODE()::TYPE_CODE_128_A)) ).'" style="width: 95%;height: 30px" class="package-awb">'; // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
                        '<div style="text-align: center; font-weight: 700; margin-top: .5rem">'.esc_html($transaction?->awb).'</div>
                    </div>
                </td>
            </tr>
            
            <tr>
                <td colspan="2" style="padding: .5rem">
                    <div style="margin-top: .75rem">
                        <div style="width: 30%;display: inline-block">
                            Asuransi
                            <br>
                            <strong>'.(esc_html($transaction->insurance_cost) > 0 ? 'Rp.'.esc_html( localMoneyFormat($transaction->insurance_cost) ) : '-').'</strong>
                        </div>
                         <div style="width: 30%;display: inline-block">
                            Berat
                            <br>
                            <strong>'.(esc_html($transaction->weight) > 0 ? esc_html(localMoneyFormat($transaction->weight)).'gr' : '-').'</strong>        
                        </div>
                         <div style="width: 30%;display: inline-block">
                            Quantity
                            <br>
                            <strong>'.(esc_html($transaction->item_count) ?? '-').'</strong>
                        </div>
                    </div>
                </td>
            </tr>
            
            <tr>
                <td style="padding: .5rem; width: 50%; border-right: 0">
                    Penerima
                    <br>
                    <strong style="font-size: .75rem;">'.(esc_html($destinationData->_shipping_first_name) ?? esc_html($destinationData->_billing_first_name) ).' '.(esc_html($destinationData->_shipping_last_name) ?? esc_html($destinationData->_billing_last_name)).'</strong>
                    <br>
                    '.(esc_html($destinationData->_shipping_address_1) ?? esc_html($destinationData->_billing_address_1)).' '.(esc_html($destinationData->_shipping_address_2) ?? esc_html($destinationData->_billing_address_2)).' '.esc_html($transaction->destination_sub_district).' '.(esc_html($destinationData->_shipping_postcode) ?? esc_html($destinationData->_billing_postcode)).'
                    <br>
                    '.esc_html($destinationData->_billing_phone).'
                </td>
                <td style="padding: .5rem; border-left: 0">
                    Dari
                    <br>
                    <strong style="font-size: .75rem;">'.esc_html($originDataArr['origin_name']).'</strong>
                    <br>
                    '.esc_html($originDataArr['origin_address']).' '.esc_html($originDataArr['origin_sub_district_name']).' '.esc_html($originDataArr['origin_zip_code']).'
                    <br>
                    '.esc_html($originDataArr['origin_phone']).'
                </td>
            </tr>
            
            <tr>
                <td colspan="2" style="padding: .5rem;">
                    Isi Paket:
                    <br>
                    <strong>Lain-lain</strong>
                </td>
            </tr>
           
            
            <tr>
                <td colspan="2" style="padding: .5rem; padding-bottom: 1rem">
                    Catatan:
                    <br>
                    <strong>'.(esc_html($transaction->checkout_note) ?? '-').'</strong>
                </td>
            </tr>
            
            <tr>
                <td
                    colspan="2"
                    style="padding: 0.75rem; padding-bottom: 1rem; font-style: italic"
                >
                    * Pengirim wajib meminta bukti serah terima paket ke kurir.
                    <br />
                    * Jika paket ini retur, pengirim tetap dikenakan biaya keberangkatan
                    dan biaya retur sesuai ekspedisi.
                </td>
            </tr>
            
            
            
    </table>';
}
?>
</body>
</html>
