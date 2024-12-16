document.addEventListener('DOMContentLoaded', () => { 

    // Active Tab Card When Clicked
    document.querySelectorAll('.tab-card').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.tab-card').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Click First Tab Filter
    const firstClick = document.querySelector('.tab-card');

    if( firstClick ){
        firstClick.click();
    }
});

jQuery(document).ready(function($){ 
    
    let html;
    var getStatusTab = 'all';

     var tableAjax = $('#tbhistory').DataTable({
        "serverSide": true,
        "processing": true,
        "scrollCollapse": true, 
        "paging": false,
        "ajax": {
            "url": kj.ajaxurl,
            "method": "POST",
            "data": function(d) {
                d.action = 'get_history_package'; 
                d.start = d.start;
                d.length = d.length; 
                d.search = d.search.value;   
                d.status = getStatusTab;           
            },
            "dataSrc": function(json) {
                return json.data;
            }
        },
        "scrollY": 400,
        "scroller": {
            "loadingIndicator": false
        }, 
        "searching":false,
        "ordering":false,
        "columns": [
            {
                "data": null, 
                "render": function(data, type, row, meta) {
                    return meta.row + 1 + meta.settings._iDisplayStart;
                },
                "title": "No"
            },
            { 
                "data": null,
                "render":function(data, type, row, meta) {                    
                    html = `<div class="tb-transaksi">
                        <div class="tb-paymentmethod ${data.payment_method}">${data.payment_method === 'cod' ? 'COD' : 'NON COD'}</div>
                        <div class="tb-oid">${data.order_id}</div>
                        <div class="tb-date">${data.created_at}</div>
                        <div class="tb-status ${data.payment_method}">${data.awb ?? '<span class="badge"> Menunggu AWB</span>'}</div>
                    </div>`;
                    return html;
                }
            },
            { 
                "data": null,
                "render":function(data, type, row, meta) {
                    html = `<div class="tb-address">
                        <div class="origin">
                            <div class="tb-person-origin">${data.origin_name} / <span class="tb-phone">${data.origin_phone}</span></div>
                            <div class="tb-origin">${data.origin_sub_district_name}</div>
                        </div>
                        <div class="destination">
                            <div class="tb-person-destination">${data.destination_name} / <span class="tb-phone">${data.destination_phone}</div>
                            <div class="tb-destination">${data.destination_sub_district}</div>
                        </div>
                    </div>`;
                    return html;
                }
            },
            { 
                "data": null,
                "render":function(data, type, row, meta) {
                    html = `<div class="tb-expedisi">
                        <div class="tb-shippingname">${data.shipping_method}</div>`;                        
                        
                        if( data.insurance_cost != 0 ){
                            html +=`<div class="tb-insurance-cost">Asuransi : ${formatRupiah(data.insurance_cost)}</div>`;                         
                        }

                        if( data.payment_method === 'cod' ){
                            html +=`<div class="tb-cod-cost">COD : ${formatRupiah(data.cod_fee)}</div>`;                         
                        }


                        html +=`<div class="tb-resi">Resi : ${data.awb ?? '-'}</div>                        
                    </div>`;

                    return html;
                }
            },
            { 
                "data": null,
                "render":function(data, type, row, meta) {
                    html = `<div class="tb-isipaket">
                        <div class="tb-products-name">${data.products_name}</div>
                        <div class="tb-products-total">${formatRupiah(data.subtotal_order)}</div>
                        <p>Berat : <span class="tb-text-weight">${data.weight} gram</span></p>
                        <p>Dimensi : <span class="tb-text-weight">${data.width} x ${data.height} x ${data.length} ${data.dimension_unit} </span></p>
                    </div>`;
                    return html;
                }
            }
        ],
        "stateSave": false
    });

    var scrollBody = $('#tbhistory').closest('.dataTables_scroll').find('.dataTables_scrollBody');
    $('#tbhistory').on('processing.dt', function (e, settings, processing) {
        if (processing) {
            scrollBody.addClass('loading');
        } else {
            scrollBody.removeClass('loading');
        }
    });
    
    // Reload data dengan parameter baru
    function reloadData(newStatus) {
        getStatusTab = newStatus; // Update parameter status
        tableAjax.ajax.reload(null, false); // false = tidak reset pagination
    }

    ajaxFilterTabHistory();
    function ajaxFilterTabHistory(){
        let parentTab = $('.tab-histories');
        let getStatusTab,status;

        parentTab.find('.tab-card').on('click',function(e){
            getStatusTab = $(this).find('.tab-header > a').data('status');
            status = getStatusTab == null ? 'all' : getStatusTab;
            reloadData(status);
        });
    }

    function formatRupiah(angka) {
        let numberString = angka.toString();
        
        let sisa = numberString.length % 3;
        let rupiah = numberString.substr(0, sisa);
        let ribuan = numberString.substr(sisa).match(/\d{3}/g);
    
        if (ribuan) {
            let separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }
    
        return "Rp" + rupiah;
    }

    
});