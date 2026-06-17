(function($) {
    'use strict';

    function getText(key, fallback) {
        if (window.kiriofTracking && window.kiriofTracking.i18n && window.kiriofTracking.i18n[key]) {
            return window.kiriofTracking.i18n[key];
        }
        return fallback;
    }

    function ajaxRoute() {
        if (typeof window.kiriofAjaxRoute === 'function') {
            return window.kiriofAjaxRoute();
        }
        if (window.kiriofAjax && window.kiriofAjax.ajaxurl) {
            return window.kiriofAjax.ajaxurl;
        }
        return '';
    }

    function printValue(value, fallback) {
        return value === null || value === undefined || value === '' ? fallback : value;
    }

    function hideStateComponent() {
        $('.track-btn').addClass('kj-hidden');
        $('.state-blank').addClass('kj-hidden');
        $('.state-err').addClass('kj-hidden');
        $('.state-loading').addClass('kj-hidden');
        $('.state-success').addClass('kj-hidden');
    }

    function renderDetails(data) {
        var trackingDetails = data && data.details ? data.details : {};
        var destination = trackingDetails.destination || {};
        var trackingOrderNumber = printValue(data ? data.number_order : '', '-');

        var $group = $('<div/>', { class: 'tracking-gorup' });
        var $header = $('<div/>', { class: 'tracking-header' }).appendTo($group);

        $('<p/>').text(getText('orderNumber', 'Nomor Order') + ' : #' + trackingOrderNumber).appendTo($header);
        $('<p/>').text(getText('awbNumber', 'Nomor Resi') + ' : ' + printValue(trackingDetails.awb, '-')).appendTo($header);

        var $address = $('<div/>', { class: 'tracking-address' }).appendTo($group);
        var $inline = $('<div/>', { class: 'track-inline' }).appendTo($address);
        $('<p/>', { class: 'textprimary' }).text(printValue(destination.name, '-')).appendTo($inline);
        $('<p/>', { class: 'textseccond' }).text(printValue(destination.city, '-')).appendTo($inline);
        $('<p/>', { class: 'textseccond textbold' }).text(printValue(destination.province, '-')).appendTo($inline);

        var $courier = $('<div/>', { class: 'tracking-courier' }).appendTo($group);
        $('<div/>', { class: 'borderdashed' }).appendTo($courier);
        var $courierText = $('<div/>', { class: 'textseccond' }).appendTo($courier);
        $('<p/>').text(getText('courier', 'Kurir')).appendTo($courierText);
        $('<p/>', { class: 'fontbold' }).text(printValue(trackingDetails.service, '-')).appendTo($courierText);
        $('<div/>', { class: 'borderdashed' }).appendTo($courier);

        $('.tracking-details').empty().append($group);
    }

    function renderHistories(histories) {
        var $tbody = $('.tracking-table tbody').empty();
        $.each(histories || [], function(index, trackData) {
            $('<tr/>')
                .append($('<td/>').text(printValue(trackData.created_at, '-')))
                .append($('<td/>').text(printValue(trackData.status, '-')))
                .appendTo($tbody);
        });
    }

    function trackOrder() {
        hideStateComponent();
        $('.state-loading').removeClass('kj-hidden');

        $.ajax({
            type: 'POST',
            url: ajaxRoute(),
            data: {
                action: 'kiriof-tracking-ajax',
                order_number: $('[name="order_number"]').val()
            },
            success: function(res) {
                var response = res && res.success ? res.data : null;

                hideStateComponent();
                $('.track-btn').removeClass('kj-hidden');

                if (response && response.status === 200) {
                    $('.state-success').removeClass('kj-hidden');
                    renderDetails(response.data || {});
                    renderHistories(response.data ? response.data.histories : []);
                    return;
                }

                $('.state-err').removeClass('kj-hidden');
                $('#err_msg').text(response && response.message ? response.message : getText('notFound', 'Order tidak ditemukan'));
            },
            error: function() {
                hideStateComponent();
                $('.track-btn').removeClass('kj-hidden');
                $('.state-err').removeClass('kj-hidden');
                $('#err_msg').text(getText('notFound', 'Order tidak ditemukan'));
            }
        });
    }

    window.trackOrder = trackOrder;

    $(function() {
        $('.track-btn').on('click', function(event) {
            event.preventDefault();
            trackOrder();
        });

        var urlParams = new URLSearchParams(window.location.search);
        var orderIdToLoad = urlParams.get('order_id');

        if (orderIdToLoad) {
            $('[name="order_number"]').val(orderIdToLoad);
            trackOrder();
        }
    });
})(jQuery);
