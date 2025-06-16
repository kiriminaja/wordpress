<div class="tab-histories">
    
    <div class="tab-card">
        <div class="tab-header">
            <div class="tab-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-2" width="24" height="24" style="color:#5508a3;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                </svg>
            </div>
            <div class="tab-label">
                <span class="text-small"><?php _e('Semua Paket','kiriminaja'); ?></span>
            </div>
            <div class="tab-count-package">
                <span class="wrap-count"><?php echo $history->getCountService(); ?></span>
            </div>
            <a aria-current="page" href="#" class="router-link-active router-link-exact-active absolute inset-0"></a>
        </div>
    </div>

    <div class="tab-card">
        <div class="tab-header">
            <div class="tab-icon">
                <svg  style="color:#f0ac48;" width="24" height="24"  xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img" class="icon text-lg text-onWarningYellowContainer" width="1em" height="1em" viewBox="0 0 24 24"><path fill="currentColor" d="M12 11q1.65 0 2.825-1.175T16 7V4H8v3q0 1.65 1.175 2.825T12 11M5 22q-.425 0-.712-.288T4 21t.288-.712T5 20h1v-3q0-1.525.713-2.863T8.7 12q-1.275-.8-1.987-2.137T6 7V4H5q-.425 0-.712-.288T4 3t.288-.712T5 2h14q.425 0 .713.288T20 3t-.288.713T19 4h-1v3q0 1.525-.712 2.863T15.3 12q1.275.8 1.988 2.138T18 17v3h1q.425 0 .713.288T20 21t-.288.713T19 22z"></path></svg>
            </div>
            <div class="tab-label">
                <span class="text-small"><?php _e('Belum Di Request Pickup','kiriminaja'); ?></span>
            </div>
            <div class="tab-count-package">
                <span class="wrap-count"><?php echo $history->getCountService('new'); ?></span>
            </div>
            <a aria-current="page" href="#" data-status="new"  class="router-link-active router-link-exact-active absolute inset-0"></a>

        </div>
    </div>

    <div class="tab-card">
        <div class="tab-header">
            <div class="tab-icon">
                <svg width="24" height="24" style="color:#f0ac48;" data-v-e8d572f6="" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img" class="icon text-lg text-onWarningYellowContainer" width="1em" height="1em" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M4 18V7.1L2.45 3.75q-.175-.375-.025-.763t.525-.562t.763-.037t.562.512L6.2 7.05h11.6l1.925-4.15q.175-.375.563-.525t.762.05q.375.175.525.563t-.025.762L20 7.1V18q0 .825-.587 1.413T18 20H6q-.825 0-1.412-.587T4 18m6-5h4q.425 0 .713-.288T15 12t-.288-.712T14 11h-4q-.425 0-.712.288T9 12t.288.713T10 13"></path>
                </svg>
            </div>
            <div class="tab-label">
                <span class="text-small"><?php _e('Request Pickup','kiriminaja'); ?></span>
            </div>
            <div class="tab-count-package">
                <span class="wrap-count"><?php echo $history->getCountService('request_pickup'); ?></span>
            </div>
            <a aria-current="page" href="#" data-status="request_pickup"  class="router-link-active router-link-exact-active absolute inset-0"></a>

        </div>
    </div>

    <div class="tab-card">
        <div class="tab-header">
            <div class="tab-icon">
                <svg width="24" height="24" style="color:#5508a3;" data-v-e8d572f6="" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img" class="icon text-lg text-flux-primary" width="1em" height="1em" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M6 20q-1.25 0-2.125-.875T3 17H2q-.425 0-.712-.288T1 16V6q0-.825.588-1.412T3 4h12q.825 0 1.413.588T17 6v2h2q.475 0 .9.213t.7.587l2.2 2.925q.1.125.15.275t.05.325V16q0 .425-.288.713T22 17h-1q0 1.25-.875 2.125T18 20t-2.125-.875T15 17H9q0 1.25-.875 2.125T6 20m0-2q.425 0 .713-.288T7 17t-.288-.712T6 16t-.712.288T5 17t.288.713T6 18m12 0q.425 0 .713-.288T19 17t-.288-.712T18 16t-.712.288T17 17t.288.713T18 18m-1-5h4.25L19 10h-2z"></path>
                </svg>
            </div>
            <div class="tab-label">
                <span class="text-small"><?php _e('Proses Pengiriman','kiriminaja'); ?></span>
            </div>
            <div class="tab-count-package">
                <span class="wrap-count"><?php echo $history->getCountService('shipped'); ?></span>
            </div>
            <a aria-current="page" href="#" data-status="shipped" class="router-link-active router-link-exact-active absolute inset-0"></a>

        </div>
    </div>

    <div class="tab-card">
        <div class="tab-header">
            <div class="tab-icon">
                <svg width="24" height="24" style="color:red;" data-v-e8d572f6="" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img" class="icon text-lg text-flux-error" width="1em" height="1em" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10s10-4.48 10-10S17.52 2 12 2M4 12c0-4.42 3.58-8 8-8c1.85 0 3.55.63 4.9 1.69L5.69 16.9A7.9 7.9 0 0 1 4 12m8 8c-1.85 0-3.55-.63-4.9-1.69L18.31 7.1A7.9 7.9 0 0 1 20 12c0 4.42-3.58 8-8 8"></path>
                </svg>
            </div>
            <div class="tab-label">
                <span class="text-small"><?php _e('Kendala Pengiriman','kiriminaja'); ?></span>
            </div>
            <div class="tab-count-package">
                <span class="wrap-count"><?php echo $history->getCountService('pending'); ?></span>
            </div>
            <a aria-current="page" data-status="pending" href="#" class="router-link-active router-link-exact-active absolute inset-0"></a>

        </div>
    </div>

    <div class="tab-card">
        <div class="tab-header">
            <div class="tab-icon">
                <svg width="24" height="24" style="color:green;" data-v-e8d572f6="" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img" class="icon text-lg text-onSuccessContainer" width="1em" height="1em" viewBox="0 0 24 24">
                    <path fill="currentColor" d="m10.6 16.6l7.05-7.05l-1.4-1.4l-5.65 5.65l-2.85-2.85l-1.4 1.4zM12 22q-2.075 0-3.9-.788t-3.175-2.137T2.788 15.9T2 12t.788-3.9t2.137-3.175T8.1 2.788T12 2t3.9.788t3.175 2.137T21.213 8.1T22 12t-.788 3.9t-2.137 3.175t-3.175 2.138T12 22"></path>
                </svg>
            </div>
            <div class="tab-label">
                <span class="text-small"><?php _e('Sampai Tujuan','kiriminaja'); ?></span>
            </div>
            <div class="tab-count-package">
                <span class="wrap-count"><?php echo $history->getCountService('finished'); ?></span>
            </div>
            <a aria-current="page" data-status="finished" href="#" class="router-link-active router-link-exact-active absolute inset-0"></a>
        </div>
    </div>

    <div class="tab-card">
        <div class="tab-header">
            <div class="tab-icon">
                <svg width="24" height="24" style="color:red;" data-v-e8d572f6="" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img" class="icon text-lg text-flux-error" width="1em" height="1em" viewBox="0 0 24 24">
                    <path fill="currentColor" d="m8.4 17l3.6-3.6l3.6 3.6l1.4-1.4l-3.6-3.6L17 8.4L15.6 7L12 10.6L8.4 7L7 8.4l3.6 3.6L7 15.6zm3.6 5q-2.075 0-3.9-.788t-3.175-2.137T2.788 15.9T2 12t.788-3.9t2.137-3.175T8.1 2.788T12 2t3.9.788t3.175 2.137T21.213 8.1T22 12t-.788 3.9t-2.137 3.175t-3.175 2.138T12 22"></path>
                </svg>
            </div>
            <div class="tab-label">
                <span class="text-small"><?php _e('Batal Proses','kiriminaja'); ?></span>
            </div>
            <div class="tab-count-package">
                <span class="wrap-count"><?php echo $history->getCountService('canceled'); ?></span>
            </div>
            <a aria-current="page" data-status="canceled" href="#" class="router-link-active router-link-exact-active absolute inset-0"></a>

        </div>
    </div>
</div>