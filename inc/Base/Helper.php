<?php

namespace Inc\Base;

class Helper{
    public function transactionStatusLabel($status = ''){
        switch ($status){
            case "new":
                return "Baru";
            break;
            case "request_pickup":
                return "Req Pickup";
            break;
            case "pending":
                return "Pending";
            break;
            case "finished":
                return "Paket Terkirim";
            break;
            case "shipped":
                return "Proses Pengiriman";
            break;
            case "return":
                return "Proses Pengembalian";
            break;
            case "returned":
                return "Paket Selesai Dikembalikan";
            break;
            case "rejected":
                return "Paket Ditolak";
            break;
            default;
            return "-";
        }
    }
    public function transactionStatusClass($status = ''){


        /* label color
- Completed | .kj-badge.success
    background: #c8d7e1;
    color: #2e4453;
    
- Processing | .kj-badge.processing
    background: #c6e1c6;
    color: #5b841b;
    
- On Hold | .kj-badge.warning
    background: #f8dda7;
    color: #94660c;
    
- Cancelled / Blank | .kj-badge
    color: #777;
    background: #e5e5e5;
*/
        
        switch ($status){
            case "new":
                return "kj-badge warning";
                break;
            case "request_pickup":
                return "kj-badge warning";
                break;
            case "pending":
                return "kj-badge warning";
                break;
            case "finished":
                return "kj-badge success";
                break;
            case "shipped":
                return "kj-badge processing";
                break;
            case "return":
                return "kj-badge";
                break;
            case "returned":
                return "kj-badge";
                break;
            case "rejected":
                return "kj-badge";
                break;
            default;
            return "kj-badge processing";
        }
    }
}