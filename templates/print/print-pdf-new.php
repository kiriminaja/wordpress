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
foreach ($packages as $index => $package){
    echo '<table  style="width: 100%; height: 95%; border-collapse: collapse; margin-top: .25rem" border="1">
            <tr>
                <td style="border-right: 0; padding: .5rem">
                    <img src="https://kiriminaja.com/assets/home/2.png" height="25px" alt="">
                </td>
                 <td style="border-left: 0;text-align: right; font-size: 1rem; font-weight: 700; padding: .5rem">
                     '.(0===0 ? 'COD Rp'.localMoneyFormat(1000000) : 'NON-COD').'
                </td>
            </tr>
            
            <tr>
                <td style="border-right: 0;padding: .5rem; position: relative">
                    <div style="position: relative">
                        <strong style="display: block;margin-top: 0.5rem">OID-XXXXXXXXX</strong>
                    </div>
                    <div style="display: inline-block; margin-top: 1.25rem">
                        <div style="display: inline-block;border: 1px solid #000;padding: .15rem">
                            <img src="https://kiriminaja-static-file.imgix.net/home-v3/logistics/jne.png" height="20px" alt="">
                        </div>
                        <div style="display: inline-block;padding: .15rem .5rem">
                            Tipe Layanan
                            <br>
                            <strong>YES</strong>
                        </div>
                    </div>
                </td>
                <td style="border-left: 0;padding: .5rem; position: relative">
                    <div style="position: relative; text-align: center">
                        <img src="data:image/png;base64,'.base64_encode(generate_barcode()->getBarcode(strtoupper('KRMJA1163362760117825536'),generate_barcode()::TYPE_CODE_128_A)).'" id="card-{{$package->id}}" alt="{{$package->awb}}" style="width: 95%;height: 30px" class="package-awb">
                        <div style="text-align: center; font-weight: 700; margin-top: .5rem">KRMJA1163362760117825536</div
                    </div>
                </td>
            </tr>
            
            <tr>
                <td colspan="2" style="padding: .5rem">
                    <div style="margin-top: .75rem">
                        <div style="width: 30%;display: inline-block">
                            Asuransi
                            <br>
                            <strong>-</strong>
                        </div>
                         <div style="width: 30%;display: inline-block">
                            Berat
                            <br>
                            <strong>500gr</strong>        
                        </div>
                         <div style="width: 30%;display: inline-block">
                            Quantity
                            <br>
                            <strong>1Pcs</strong>
                        </div>
                    </div>
                </td>
            </tr>
            
            <tr>
                <td style="padding: .5rem; width: 50%; border-right: 0">
                    Penerima
                    <br>
                    <strong style="font-size: .75rem;">Gemah Ripah</strong>
                    <br>
                    Lorem Ipsum Dolor. Lorem Ipsum Dolor. Lorem Ipsum Dolor. Lorem Ipsum Dolor. Lorem Ipsum Dolor.
                    <br>
                    082082082082
                </td>
                <td style="padding: .5rem; border-left: 0">
                    Dari
                    <br>
                    <strong style="font-size: .75rem;">Gemah Ripah</strong>
                    <br>
                    Lorem Ipsum Dolor. Lorem Ipsum Dolor. Lorem Ipsum Dolor. Lorem Ipsum Dolor. Lorem Ipsum Dolor.
                    <br>
                    082082082082
                </td>
            </tr>
            
          
            
            <tr>
                <td colspan="2" style="padding: .5rem; text-align: center; background-color: black;color: white">
                    <div style="margin-top: .25rem">
                        <div style="width: 30%; display: inline-block">
                        <strong style="font-size: .75rem;">JOG1000</strong>
                        </div>
                        <div style="width: 30%; display: inline-block">
                        <strong style="font-size: .75rem;">to</strong>
                        </div>
                        <div style="width: 30%; display: inline-block">
                        <strong style="font-size: .75rem;">JKT1000</strong>
                        </div>
                    </div>
                </td>
            </tr>
            
          
            
            <tr>
                <td colspan="2" style="padding: .5rem;">
                    Isi Paket:
                    <br>
                    <strong>Paket</strong>
                </td>
            </tr>
           
            
            <tr>
                <td colspan="2" style="padding: .5rem; padding-bottom: 1rem">
                    Catatan:
                    <br>
                    <strong>'.(nl2br('$package->description')).'</strong>
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
