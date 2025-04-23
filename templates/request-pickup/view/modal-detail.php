<!--Modal-->
<div id="request-pickup-detail-modal" class="kj-hidden">
    <div class="modal-container">
        <div style="background-color: #f0f0f1" tabindex="0" class="media-modal" role="dialog">
            <div class="media-modal-container">
                <div class="closebtn-container" onclick="document.getElementById('request-pickup-detail-modal').classList.add('kj-hidden');">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M16.0659 8.99481C16.3588 8.70191 16.3588 8.22704 16.0659 7.93415C15.773 7.64125 15.2981 7.64125 15.0052 7.93415L12 10.9393L8.99482 7.93415C8.70192 7.64125 8.22705 7.64125 7.93416 7.93415C7.64126 8.22704 7.64126 8.70191 7.93416 8.99481L10.9394 12L7.93415 15.0052C7.64125 15.2981 7.64125 15.773 7.93415 16.0659C8.22704 16.3588 8.70191 16.3588 8.99481 16.0659L12 13.0607L15.0052 16.0659C15.2981 16.3588 15.773 16.3588 16.0659 16.0659C16.3588 15.773 16.3588 15.2981 16.0659 15.0052L13.0607 12L16.0659 8.99481Z" fill="black"/>
                    </svg>
                </div>
                <div class="content-header" style="background-color: white" >
                    <h1>Request Pickup Detail</h1>
                </div>
                <div class="content-body">

                    <div class="kj-modal-loader">
                        <div style="width: 100%;height: 10rem;position: relative; display: flex">
                            <div class="kj-loader" style="margin: auto"></div>
                        </div>
                    </div>

                    <div class="kj-modal-content kj-hidden">
                        <div class="row-divider" style="margin-top: .75rem"></div>
                        <div>
                            <div class="row gx-2">
                                <div class="col">
                                    <div style="border:1px solid #dadadc;padding: .5rem .75rem; background-color: #ffffff">
                                        <div style="font-weight: 600;"><span id="package-count">10.0000</span></div>
                                        <div class="row-divider" style="margin-top: .5rem"></div>
                                        <div>Total Paket</div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div style="border:1px solid #dadadc;padding: .5rem .75rem; background-color: #ffffff">
                                        <div style="font-weight: 600;"><span id="package-cod-count">10.0000</span></div>
                                        <div class="row-divider" style="margin-top: .5rem"></div>
                                        <div>Paket Cash on Delivery</div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div style="border:1px solid #dadadc;padding: .5rem .75rem; background-color: #ffffff">
                                        <div style="font-weight: 600;"><span id="package-non-cod-count">10.0000</span></div>
                                        <div class="row-divider" style="margin-top: .5rem"></div>
                                        <div>Paket Non-COD</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--TOP-->
                        <div class="row-divider" style="margin-top: .75rem"></div>
                        <div style="display: none" class="container-fluid p-0">
                            <div class="row">
                                <div class="col">
                                    <!--Month Search-->
                                    <div style="display: flex;width: 100%; gap: 2px">
                                        <select style="width: 100%; max-width: 12.5rem" name="month_search_2" id="month_search_2">
                                            <option selected="selected" value="">All Dates</option>
                                            <option value="2024-02">2024 February</option><option value="2024-01">2024 January</option><option value="2023-12">2023 December</option><option value="2023-11">2023 November</option><option value="2023-10">2023 October</option>                                                    </select>
                                        <button class="button-wp-secondary" type="button" onclick="applySearch('month',document.getElementById('month_search_2').value)">
                                            <div style="display: flex">
                                                <div style="margin: auto">
                                                    <span>Apply</span>
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                                <div class="col">
                                    <!--Pagination-->
                                    <div style="display: flex;justify-content: end;align-items: center;justify-items: center;gap: 6px">
                                        <span style="font-weight: 700;">3 items</span>
                                        <div>
                                            <button disabled="" style="position: relative" class="button-wp-blank" type="button">
                                                <div style="display: flex">
                                                    <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M11.1998 4L7.1998 8L11.1998 12L10.3998 13.6L4.7998 8L10.3998 2.4L11.1998 4Z" fill="black"></path>
                                                        </svg>
                                                    </div>
                                                </div>
                                            </button>
                                        </div>
                                        <span style="font-weight: 700;"> 1 of 2 </span>
                                        <div>
                                            <button style="position: relative" class="button-wp-blank" type="button">
                                                <a href="#" class="inset-absolute"></a>
                                                <div style="display: flex">
                                                    <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M4.7998 12L8.7998 8L4.7998 4L5.5998 2.4L11.1998 8L5.5998 13.6L4.7998 12Z" fill="black"></path>
                                                        </svg>
                                                    </div>
                                                </div>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!--TABLE-->
                        <div class="row-divider" style="margin-top: .75rem"></div>
                        <table class="wp-list-table widefat fixed striped table-view-list posts">
                            <thead>
                            <tr>
                                <th style="width: 4rem;" scope="col" class="manage-column column-thumb">No</th>
                                <th scope="col" class="manage-column column-thumb">Package</th>
                                <th scope="col" class="manage-column column-thumb">Shipment</th>
                                <th scope="col" class="manage-column column-thumb">Fees</th>
                                <th scope="col" class="manage-column column-thumb">Status</th>
                                <th scope="col" class="manage-column column-thumb"><span style="float: right">Action</span></th>
                            </tr>
                            </thead>
                            <tbody id="the-list">
    
                                <tr class="">
                                    <td style="font-weight: 700;" class="thumb column-thumb">1</td>
                                    <td class="manage-column column-thumb">
                                        <div style="display: flex">
                                            <div style="font-weight: 700;padding: 0.2rem 0.5rem;color: #3c82ba;border: 2px solid #3c82ba;border-radius: 5px;">
                                                COD
                                            </div>
                                        </div>
                                        <div class="row-divider" style="margin-top: .25rem"></div>
                                        <div style="font-weight: 700">XID-0000000004</div>
                                        <div style="font-size: 12px;">Gama Antika Hariadi</div>
                                    </td>
                                    <td class="manage-column column-thumb">
                                        <div style="font-weight: 700">141441001001</div>
                                        <div style="font-weight: 700">JNE – CTC23</div>
                                        <div style="font-size: 12px;">Last Update: 2024/01/12 09:20</div>
                                    </td>
                                    <td class="manage-column column-thumb">
                                        <div style="font-weight: 700">Rp300.000</div>
                                    </td>
                                    <td class="manage-column column-thumb">
                                        <div class="kj-badge warning">
                                            <span>Pending</span>
                                        </div>
                                    </td>
                                    <td class="manage-column column-thumb">
                                        <div style="display: flex;justify-content: end;gap: 4px; flex-wrap: wrap">
                                            <button class="button-wp" type="button" onclick="showPaymentForm(`XID-0000000002`)">
                                                <div style="display: flex">
                                                    <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                        <div style="position: relative; top: 1px">
                                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <path d="M9.59961 8.79998H5.59961V9.59998H9.59961V8.79998ZM10.3996 12H5.59961V12.8H10.3996V12ZM7.99961 10.4H5.59961V11.2H7.99961V10.4ZM13.5996 4.79998H11.9996V1.59998H3.99961V4.79998H2.39961C1.91961 4.79998 1.59961 5.11998 1.59961 5.59998V9.59998C1.59961 10.08 1.91961 10.4 2.39961 10.4H3.99961V14.4H11.9996V10.4H13.5996C14.0796 10.4 14.3996 10.08 14.3996 9.59998V5.59998C14.3996 5.11998 14.0796 4.79998 13.5996 4.79998ZM11.1996 13.6H4.79961V7.99998H11.1996V13.6ZM11.1996 4.79998H4.79961V2.39998H11.1996V4.79998ZM12.7996 7.19998H11.9996V6.39998H12.7996V7.19998Z" fill="white"/>
                                                            </svg>
                                                            
                                                        </div>
                                                        <span style="margin-left: 6px">Print</span>
                                                    </div>
                                                </div>
                                            </button>
                                            <button class="button-wp-secondary" type="button" onclick="showDetail(`XID-0000000004`)">
                                                <div style="display: flex">
                                                    <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                        <span>Detail</span>
                                                    </div>
                                                </div>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
             
                        </table>
                        
                        <!--BOTTOM-->
                        <div class="row-divider" style="margin-top: .75rem"></div>
                        <div style="display: none" class="container-fluid p-0">
                            <div class="row">
                                <div class="col">
                                    <!--Month Search-->
                                    <div style="display: flex;width: 100%; gap: 2px">
                                        <select style="width: 100%; max-width: 12.5rem" name="month_search_2" id="month_search_2">
                                            <option selected="selected" value="">All Dates</option>
                                            <option value="2024-02">2024 February</option><option value="2024-01">2024 January</option><option value="2023-12">2023 December</option><option value="2023-11">2023 November</option><option value="2023-10">2023 October</option>                                                    </select>
                                        <button class="button-wp-secondary" type="button" onclick="applySearch('month',document.getElementById('month_search_2').value)">
                                            <div style="display: flex">
                                                <div style="margin: auto">
                                                    <span>Apply</span>
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                                <div class="col">
                                    <!--Pagination-->
                                    <div style="display: flex;justify-content: end;align-items: center;justify-items: center;gap: 6px">
                                        <span style="font-weight: 700;">3 items</span>
                                        <div>
                                            <button disabled="" style="position: relative" class="button-wp-blank" type="button">
                                                <div style="display: flex">
                                                    <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M11.1998 4L7.1998 8L11.1998 12L10.3998 13.6L4.7998 8L10.3998 2.4L11.1998 4Z" fill="black"></path>
                                                        </svg>
                                                    </div>
                                                </div>
                                            </button>
                                        </div>
                                        <span style="font-weight: 700;"> 1 of 2 </span>
                                        <div>
                                            <button style="position: relative" class="button-wp-blank" type="button">
                                                <a href="#" class="inset-absolute"></a>
                                                <div style="display: flex">
                                                    <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M4.7998 12L8.7998 8L4.7998 4L5.5998 2.4L11.1998 8L5.5998 13.6L4.7998 12Z" fill="black"></path>
                                                        </svg>
                                                    </div>
                                                </div>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="kj-err-container" style="padding: 2.5rem; text-align: center">
                        <p style="margin-bottom: 1.5rem">Terjadi Kesalahan !</p>
                        <button style="background-color: #009b1e; border: 1px solid #009b1e" class="button-primary woocommerce-save-button" type="button" onclick="refreshShowDetail()">Refresh</button>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <div class="media-modal-backdrop"></div>
</div>